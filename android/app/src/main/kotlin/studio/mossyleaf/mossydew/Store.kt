package studio.mossyleaf.mossydew

import android.content.Context
import androidx.core.content.edit
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import org.json.JSONObject

data class Snapshot(
    val token: String? = null,
    val today: String? = null,
    val pending: Map<String, Boolean> = emptyMap(),
    val offline: Boolean = false,
    val fetchedOn: String? = null,
)

object Store {
    private const val TOKEN = "token"
    private const val TODAY = "today"
    private const val PENDING = "pending"
    private const val OFFLINE = "offline"
    private const val FETCHED_ON = "fetchedOn"

    private var state: MutableStateFlow<Snapshot>? = null

    @Synchronized
    fun flow(context: Context): StateFlow<Snapshot> = state ?: MutableStateFlow(load(context)).also { state = it }

    fun current(context: Context): Snapshot = flow(context).value

    @Synchronized
    fun update(context: Context, change: (Snapshot) -> Snapshot) {
        val next = change(current(context))
        preferences(context).edit {
            putString(TOKEN, next.token)
            putString(TODAY, next.today)
            putString(PENDING, JSONObject(next.pending).toString())
            putBoolean(OFFLINE, next.offline)
            putString(FETCHED_ON, next.fetchedOn)
        }
        state?.value = next
    }

    private fun load(context: Context): Snapshot {
        val preferences = preferences(context)
        val pending = JSONObject(preferences.getString(PENDING, null) ?: "{}")
        return Snapshot(
            token = preferences.getString(TOKEN, null),
            today = preferences.getString(TODAY, null),
            pending = pending.keys().asSequence().associateWith(pending::getBoolean),
            offline = preferences.getBoolean(OFFLINE, false),
            fetchedOn = preferences.getString(FETCHED_ON, null),
        )
    }

    private fun preferences(context: Context) = context.applicationContext.getSharedPreferences("mossydew", Context.MODE_PRIVATE)
}
