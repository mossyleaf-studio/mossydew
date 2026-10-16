import SwiftUI
import WebKit
import WidgetKit

@MainActor
final class Browser: NSObject, ObservableObject, WKNavigationDelegate, WKScriptMessageHandler {
    private static let themeScript = """
        {
            const post = () => {
                const style = getComputedStyle(document.documentElement);
                webkit.messageHandlers.theme.postMessage(['--theme-background', 'color-scheme'].map((name) => style.getPropertyValue(name).trim()));
            };
            post();
            new MutationObserver(post).observe(document.documentElement, { attributes: true, attributeFilter: ['style'] });
        }
        """

    @Published private(set) var background = Color(hex: Theme.moss.background)
    @Published private(set) var dark = false
    let webView: WKWebView
    private var issuing = false
    private var signIns = 0
    private var committedHost: String?

    override init() {
        let configuration = WKWebViewConfiguration()
        configuration.websiteDataStore = .default()
        configuration.applicationNameForUserAgent = "Mobile/15E148 MossyDewApp/\(Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "")"
        configuration.userContentController.addUserScript(WKUserScript(source: Self.themeScript, injectionTime: .atDocumentEnd, forMainFrameOnly: true, in: .defaultClient))
        webView = WKWebView(frame: .zero, configuration: configuration)
        super.init()
        webView.configuration.userContentController.add(self, contentWorld: .defaultClient, name: "theme")
        webView.navigationDelegate = self
        webView.allowsBackForwardNavigationGestures = true
        #if DEBUG
        webView.isInspectable = true
        #endif
        webView.load(URLRequest(url: MossyDew.site))
    }

    func openToday() {
        guard webView.url?.host() == MossyDew.site.host(), webView.url?.path() != "/" else { return }
        webView.load(URLRequest(url: MossyDew.site))
    }

    func reloadIfBlank() {
        if webView.url == nil {
            webView.load(URLRequest(url: MossyDew.site))
        }
    }

    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        guard let values = message.body as? [String], values.count == 2 else { return }
        background = Color(hex: values[0].isEmpty ? Theme.moss.background : values[0])
        dark = values[1] == "dark"
    }

    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping @MainActor @Sendable (WKNavigationActionPolicy) -> Void) {
        guard let url = action.request.url, url.scheme == "https" || url.scheme == "http", action.targetFrame?.isMainFrame != false else {
            return decisionHandler(.allow)
        }
        switch url.host() {
        case MossyDew.site.host():
            if url.path() == "/logout" && action.request.httpMethod == "POST" || url.path() == "/login/check" {
                forgetToken()
            }
        case MossyDew.accountsHost:
            if action.navigationType == .backForward, committedHost == MossyDew.site.host() {
                return decisionHandler(.cancel)
            }
        default:
            UIApplication.shared.open(url)
            return decisionHandler(.cancel)
        }
        if action.targetFrame == nil {
            webView.load(action.request)
            return decisionHandler(.cancel)
        }
        decisionHandler(.allow)
    }

    func webView(_ webView: WKWebView, didCommit navigation: WKNavigation!) {
        committedHost = webView.url?.host()
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        issueTokenIfNeeded()
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        guard webView.url == nil, error is URLError else { return }
        Task {
            try? await Task.sleep(for: .seconds(5))
            reloadIfBlank()
        }
    }

    func issueTokenIfNeeded() {
        guard !webView.isLoading, let url = webView.url, url.host() == MossyDew.site.host(), !url.path().hasPrefix("/login"), !issuing, DeviceToken.load() == nil else { return }
        issuing = true
        let signIn = signIns
        Task {
            await issueToken(for: signIn)
            if signIn == signIns { issuing = false }
        }
    }

    func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
        webView.reload()
    }

    private func issueToken(for signIn: Int) async {
        let host = MossyDew.site.host() ?? ""
        let cookies = await webView.configuration.websiteDataStore.httpCookieStore.allCookies().filter {
            $0.domain == host || $0.domain.hasPrefix(".") && ".\(host)".hasSuffix($0.domain)
        }
        var request = URLRequest(url: MossyDew.site.appending(path: "api/widget/token"))
        request.httpMethod = "POST"
        request.httpShouldHandleCookies = false
        request.allHTTPHeaderFields = HTTPCookie.requestHeaderFields(with: cookies)
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        guard let (data, response) = try? await URLSession.shared.data(for: request),
              (response as? HTTPURLResponse)?.statusCode == 201,
              let token = try? JSONDecoder().decode([String: String].self, from: data)["token"] else { return }
        guard signIn == signIns else {
            await WidgetAPI.send("DELETE", "widget/token", token: token)
            return
        }
        DeviceToken.save(token)
        WidgetCenter.shared.reloadAllTimelines()
    }

    private func forgetToken() {
        signIns += 1
        issuing = false
        if let token = DeviceToken.load() {
            Task { await WidgetAPI.send("DELETE", "widget/token", token: token) }
        }
        DeviceToken.delete()
        TodayCache.clear()
        WidgetCenter.shared.reloadAllTimelines()
    }
}
