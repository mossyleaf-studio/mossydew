import SwiftUI
import WebKit
import WidgetKit

@main
struct MossyDewApp: App {
    var body: some Scene {
        WindowGroup {
            ContentView()
        }
    }
}

struct ContentView: View {
    @StateObject private var browser = Browser()
    @Environment(\.scenePhase) private var scenePhase

    var body: some View {
        BrowserView(webView: browser.webView)
            .ignoresSafeArea(.keyboard)
            .background(browser.background.ignoresSafeArea())
            .preferredColorScheme(browser.dark ? .dark : .light)
            .onOpenURL { url in
                if url.host() == "today" {
                    browser.openToday()
                }
            }
            .onChange(of: scenePhase) { _, phase in
                if phase == .background {
                    WidgetCenter.shared.reloadAllTimelines()
                }
                if phase == .active {
                    browser.reloadIfBlank()
                    browser.issueTokenIfNeeded()
                }
            }
    }
}

struct BrowserView: UIViewRepresentable {
    let webView: WKWebView

    func makeUIView(context: Context) -> WKWebView {
        webView
    }

    func updateUIView(_ webView: WKWebView, context: Context) {}
}
