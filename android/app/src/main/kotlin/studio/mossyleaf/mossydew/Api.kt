package studio.mossyleaf.mossydew

import android.net.Uri
import java.net.HttpURLConnection
import java.net.URI

object Api {
    fun mintToken(cookie: String) = call("POST", "/api/widget/token", "Cookie", cookie)

    fun today(token: String) = call("GET", "/widget/today", "Authorization", "Bearer $token")

    fun tick(token: String, id: String, done: Boolean) =
        call("POST", "/widget/tasks/${Uri.encode(id)}/${if (done) "complete" else "reopen"}", "Authorization", "Bearer $token")

    fun revokeToken(token: String) = call("DELETE", "/widget/token", "Authorization", "Bearer $token")

    private fun call(method: String, path: String, header: String, value: String): Pair<Int, String> {
        val connection = URI(BuildConfig.BASE_URL + path).toURL().openConnection() as HttpURLConnection
        try {
            connection.requestMethod = method
            connection.connectTimeout = 10_000
            connection.readTimeout = 10_000
            connection.setRequestProperty("Accept", "application/json")
            connection.setRequestProperty(header, value)
            val status = connection.responseCode
            return status to if (status in 200..299) connection.inputStream.bufferedReader().use { it.readText() } else ""
        } finally {
            connection.disconnect()
        }
    }
}
