package studio.mossyleaf.mossydew

import android.content.Context
import androidx.glance.GlanceId
import androidx.glance.action.ActionParameters
import androidx.glance.appwidget.action.ActionCallback
import androidx.glance.appwidget.action.ToggleableStateKey
import androidx.glance.appwidget.updateAll
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.Data
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequest
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import java.io.IOException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class TickAction : ActionCallback {
    override suspend fun onAction(context: Context, glanceId: GlanceId, parameters: ActionParameters) {
        val id = parameters[TASK] ?: return
        val done = parameters[ToggleableStateKey] ?: return
        Store.update(context) { it.copy(pending = it.pending + (id to done)) }
        WorkManager.getInstance(context).enqueueUniqueWork(
            "tick-$id",
            ExistingWorkPolicy.APPEND_OR_REPLACE,
            OneTimeWorkRequest.Builder(TickWorker::class.java)
                .setInputData(Data.Builder().putString(TickWorker.TASK, id).putBoolean(TickWorker.DONE, done).build())
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .build(),
        )
        TodayWidget().updateAll(context)
    }

    companion object {
        val TASK = ActionParameters.Key<String>("task")
    }
}

class TickWorker(context: Context, parameters: WorkerParameters) : CoroutineWorker(context, parameters) {
    override suspend fun doWork(): Result {
        val id = inputData.getString(TASK) ?: return Result.failure()
        val done = inputData.getBoolean(DONE, false)
        val token = Store.current(applicationContext).token
        val status = try {
            token?.let { withContext(Dispatchers.IO) { Api.tick(it, id, done).first } }
        } catch (e: IOException) {
            return Result.retry()
        }
        when (status) {
            401 -> Store.update(applicationContext) { if (it.token == token) Snapshot() else it }
            408, 429, in 500..599 -> if (runAttemptCount < 5) return Result.retry()
        }
        try {
            refreshWidgets(applicationContext)
        } finally {
            Store.update(applicationContext) { snapshot ->
                snapshot.copy(pending = snapshot.pending.filterNot { it.key == id && it.value == done })
            }
        }
        return Result.success()
    }

    companion object {
        const val TASK = "task"
        const val DONE = "done"
    }
}
