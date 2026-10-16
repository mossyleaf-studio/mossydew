package studio.mossyleaf.mossydew

import android.content.ActivityNotFoundException
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.RenderProcessGoneDetail
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.graphics.Insets
import androidx.core.graphics.toColorInt
import androidx.core.net.toUri
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.lifecycle.Lifecycle
import java.io.IOException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import org.json.JSONException
import org.json.JSONObject

private val appScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
private var minting: Job? = null
@Volatile private var signIns = 0

class MainActivity : ComponentActivity() {
    private lateinit var root: FrameLayout
    private lateinit var webView: WebView
    private val baseHost = BuildConfig.BASE_URL.toUri().host
    private var lastHost: String? = null
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    @Volatile private var signingOut = false
    private var failed = false
    private var dark = false
    private val statusBar = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT) { dark }
    private val navigationBar = SystemBarStyle.auto(LIGHT_SCRIM, DARK_SCRIM) { dark }

    private val back = object : OnBackPressedCallback(false) {
        override fun handleOnBackPressed() = webView.goBack()
    }

    private val chooseFile = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        fileCallback?.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(result.resultCode, result.data))
        fileCallback = null
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(statusBar, navigationBar)
        webView = WebView(this)
        root = FrameLayout(this)
        root.addView(webView)
        setContentView(root)
        ViewCompat.setOnApplyWindowInsetsListener(root) { view, insets ->
            val bars = insets.getInsets(BARS)
            view.setPadding(bars.left, bars.top, bars.right, bars.bottom)
            WindowInsetsCompat.Builder(insets).setInsets(BARS, Insets.NONE).build()
        }
        paint(DEFAULT_BACKGROUND)
        onBackPressedDispatcher.addCallback(this, back)
        webView.setBackgroundColor(Color.TRANSPARENT)
        with(webView.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            userAgentString = "$userAgentString MossyDewApp/${BuildConfig.VERSION_NAME}"
        }
        webView.webViewClient = Client()
        webView.webChromeClient = Chrome()
        webView.addJavascriptInterface(Bridge(), "MossyDewApp")
        webView.loadUrl(BuildConfig.BASE_URL + "/")
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        val uri = webView.url.orEmpty().toUri()
        if (intent.getBooleanExtra(SHOW_TODAY, false) && uri.host == baseHost && uri.path != "/") {
            webView.loadUrl(BuildConfig.BASE_URL + "/")
        }
    }

    override fun onResume() {
        super.onResume()
        if (failed) webView.reload() else mintIfSignedIn(webView.url.orEmpty())
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    override fun onStop() {
        super.onStop()
        appScope.launch { refreshWidgets(applicationContext) }
    }

    override fun onDestroy() {
        root.removeView(webView)
        webView.destroy()
        super.onDestroy()
    }

    private fun paint(color: Int) {
        root.setBackgroundColor(color)
        dark = Color.luminance(color) < 0.24f
        enableEdgeToEdge(statusBar, navigationBar)
    }

    private fun repaint(view: WebView, fallback: Int?) = view.evaluateJavascript(THEME_BACKGROUND) { value ->
        (runCatching { value.orEmpty().trim('"', ' ').toColorInt() }.getOrNull() ?: fallback)?.let(::paint)
    }

    private fun mintIfSignedIn(url: String) {
        val uri = url.toUri()
        if (uri.host == baseHost && uri.path?.startsWith("/login") != true && Store.current(this).token == null) mintToken()
    }

    private fun mintToken() {
        if (minting?.isActive == true) return
        val cookie = CookieManager.getInstance().getCookie(BuildConfig.BASE_URL) ?: return
        val signIn = signIns
        minting = appScope.launch {
            val token = try {
                val (status, body) = Api.mintToken(cookie)
                if (status == 201) JSONObject(body).getString("token") else null
            } catch (e: IOException) {
                null
            } catch (e: JSONException) {
                null
            }
            if (token != null) {
                var saved = false
                Store.update(applicationContext) {
                    saved = signIns == signIn
                    if (saved) it.copy(token = token) else it
                }
                if (saved) refreshWidgets(applicationContext) else runCatching { Api.revokeToken(token) }
            }
        }
    }

    private fun Uri.isLogout() = host == baseHost && path == "/logout"

    private fun signOut() {
        signIns++
        var token: String? = null
        Store.update(this) {
            token = it.token
            Snapshot()
        }
        appScope.launch {
            refreshWidgets(applicationContext)
            token?.let { runCatching { Api.revokeToken(it) } }
        }
    }

    private fun retrySoon(view: WebView) {
        failed = true
        view.postDelayed({ if (failed && lifecycle.currentState.isAtLeast(Lifecycle.State.RESUMED)) view.reload() }, 5000)
    }

    private inner class Client : WebViewClient() {
        override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
            val host = request.url.host
            if (request.isForMainFrame && host == baseHost && request.url.path == "/login/check") signOut()
            if (!request.isForMainFrame || host == baseHost || host == ACCOUNTS_HOST) return false
            runCatching { startActivity(Intent(Intent.ACTION_VIEW, request.url)) }
            return true
        }

        override fun shouldInterceptRequest(view: WebView, request: WebResourceRequest): WebResourceResponse? {
            if (request.isForMainFrame && request.method == "POST" && request.url.isLogout()) signingOut = true
            return null
        }

        override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) {
            failed = false
        }

        override fun onPageFinished(view: WebView, url: String) {
            val uri = url.toUri()
            if (uri.host == baseHost && lastHost == ACCOUNTS_HOST) {
                view.clearHistory()
                back.isEnabled = view.canGoBack()
            }
            lastHost = uri.host
            repaint(view, DEFAULT_BACKGROUND)
            if (uri.host == baseHost) view.evaluateJavascript(WATCH_THEME, null)
            mintIfSignedIn(url)
        }

        override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
            if (request.isForMainFrame) retrySoon(view)
        }

        override fun onReceivedHttpError(view: WebView, request: WebResourceRequest, errorResponse: WebResourceResponse) {
            if (request.isForMainFrame && errorResponse.statusCode >= 500) retrySoon(view)
        }

        override fun doUpdateVisitedHistory(view: WebView, url: String, isReload: Boolean) {
            back.isEnabled = view.canGoBack()
            repaint(view, null)
            if (signingOut) {
                signingOut = false
                if (!url.toUri().isLogout()) signOut()
            }
        }

        override fun onRenderProcessGone(view: WebView, detail: RenderProcessGoneDetail): Boolean {
            recreate()
            return true
        }
    }

    private inner class Bridge {
        @JavascriptInterface
        fun theme(background: String) = runOnUiThread { runCatching { background.trim().toColorInt() }.getOrNull()?.let(::paint) }
    }

    private inner class Chrome : WebChromeClient() {
        override fun onShowFileChooser(view: WebView, callback: ValueCallback<Array<Uri>>, params: FileChooserParams): Boolean {
            fileCallback?.onReceiveValue(null)
            fileCallback = callback
            try {
                chooseFile.launch(params.createIntent())
            } catch (e: ActivityNotFoundException) {
                fileCallback = null
                callback.onReceiveValue(null)
            }
            return true
        }
    }

    companion object {
        const val SHOW_TODAY = "studio.mossyleaf.mossydew.SHOW_TODAY"
        private const val ACCOUNTS_HOST = "accounts.mossyleaf.studio"
        private const val THEME_BACKGROUND = "getComputedStyle(document.documentElement).getPropertyValue('--theme-background')"
        private const val WATCH_THEME = "new MutationObserver(() => MossyDewApp.theme($THEME_BACKGROUND)).observe(document.documentElement, { attributes: true, attributeFilter: ['style'] })"
        private const val DEFAULT_BACKGROUND = 0xFFF5F5F3.toInt()
        private const val LIGHT_SCRIM = 0xE6FFFFFF.toInt()
        private const val DARK_SCRIM = 0x801B1B1B.toInt()
        private val BARS = WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.displayCutout() or WindowInsetsCompat.Type.ime()
    }
}
