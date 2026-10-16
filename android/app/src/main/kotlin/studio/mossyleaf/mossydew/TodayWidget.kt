package studio.mossyleaf.mossydew

import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.SystemClock
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.glance.ColorFilter
import androidx.glance.GlanceId
import androidx.glance.GlanceModifier
import androidx.glance.Image
import androidx.glance.ImageProvider
import androidx.glance.action.Action
import androidx.glance.action.actionParametersOf
import androidx.glance.action.clickable
import androidx.glance.appwidget.CheckBox
import androidx.glance.appwidget.CheckboxDefaults
import androidx.glance.appwidget.GlanceAppWidget
import androidx.glance.appwidget.GlanceAppWidgetManager
import androidx.glance.appwidget.GlanceAppWidgetReceiver
import androidx.glance.appwidget.action.actionRunCallback
import androidx.glance.appwidget.action.actionStartActivity
import androidx.glance.appwidget.lazy.LazyColumn
import androidx.glance.appwidget.lazy.items
import androidx.glance.appwidget.provideContent
import androidx.glance.appwidget.updateAll
import androidx.glance.background
import androidx.glance.layout.Alignment
import androidx.glance.layout.Column
import androidx.glance.layout.Row
import androidx.glance.layout.fillMaxSize
import androidx.glance.layout.fillMaxWidth
import androidx.glance.layout.padding
import androidx.glance.layout.size
import androidx.glance.semantics.contentDescription
import androidx.glance.semantics.semantics
import androidx.glance.text.FontWeight
import androidx.glance.text.Text
import androidx.glance.text.TextAlign
import androidx.glance.text.TextDecoration
import androidx.glance.text.TextStyle
import androidx.glance.unit.ColorProvider
import java.io.IOException
import java.time.LocalDate
import java.util.concurrent.atomic.AtomicLong
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class TodayWidget : GlanceAppWidget() {
    override suspend fun provideGlance(context: Context, id: GlanceId) {
        provideContent {
            LaunchedEffect(Unit) {
                if (SystemClock.elapsedRealtime() >= freshUntil) withContext(Dispatchers.IO) { fetchToday(context) }
            }
            val snapshot by Store.flow(context).collectAsState()
            Today(context, snapshot)
        }
    }
}

class TodayWidgetReceiver : GlanceAppWidgetReceiver() {
    override val glanceAppWidget: GlanceAppWidget = TodayWidget()
}

suspend fun refreshWidgets(context: Context) {
    if (GlanceAppWidgetManager(context).getGlanceIds(TodayWidget::class.java).isEmpty()) return
    withContext(Dispatchers.IO) { fetchToday(context) }
    TodayWidget().updateAll(context)
}

private val fetches = AtomicLong()
private var newestStored = 0L
@Volatile private var freshUntil = 0L

private fun fetchToday(context: Context) {
    val token = Store.current(context).token ?: return
    val fetch = fetches.incrementAndGet()
    val response = try {
        Api.today(token)
    } catch (e: IOException) {
        null
    }
    Store.update(context) { snapshot ->
        when {
            snapshot.token != token || fetch < newestStored -> snapshot
            response?.first == 200 -> snapshot.copy(today = response.second, offline = false, fetchedOn = LocalDate.now().toString()).also {
                newestStored = fetch
                freshUntil = SystemClock.elapsedRealtime() + 30_000
            }
            response?.first == 401 -> Snapshot()
            else -> snapshot.copy(offline = true)
        }
    }
}

@Composable
private fun Today(context: Context, snapshot: Snapshot) {
    val today = snapshot.today.takeIf { snapshot.fetchedOn == LocalDate.now().toString() }
    val list = todayList(today, snapshot.pending)
    val palette = list.palette
    val muted = TextStyle(color = ColorProvider(palette.muted), fontSize = 13.sp)
    val openToday = actionStartActivity(Intent(context, MainActivity::class.java).putExtra(MainActivity.SHOW_TODAY, true))
    val hint = when {
        snapshot.token == null -> null
        snapshot.offline -> context.getString(R.string.widget_offline)
        list.left > 0 -> context.resources.getQuantityString(R.plurals.widget_left, list.left, list.left)
        else -> null
    }
    Column(
        GlanceModifier.fillMaxSize()
            .background(ImageProvider(R.drawable.widget_background), colorFilter = ColorFilter.tint(ColorProvider(palette.background)))
            .clickable(openToday)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Row(GlanceModifier.fillMaxWidth().padding(bottom = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(
                context.getString(R.string.widget_title),
                style = TextStyle(color = ColorProvider(palette.accent), fontSize = 16.sp, fontWeight = FontWeight.Bold),
                maxLines = 1,
            )
            if (hint != null) Text(hint, GlanceModifier.defaultWeight(), muted.copy(textAlign = TextAlign.End), maxLines = 1)
        }
        when {
            snapshot.token == null -> Text(context.getString(R.string.widget_signed_out), style = muted)
            today != null -> {
                if (list.left == 0) Text(context.getString(R.string.widget_nothing_left), style = muted)
                LazyColumn(GlanceModifier.fillMaxWidth()) {
                    items(list.rows, itemId = { it.id.hashCode().toLong() }) { Task(context, it, palette, openToday) }
                }
            }
        }
    }
}

@Composable
private fun Task(context: Context, row: TaskRow, palette: Palette, openToday: Action) {
    Row(
        GlanceModifier.fillMaxWidth().padding(start = if (row.nested) 16.dp else 0.dp).clickable(openToday),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (row.locked) {
            Image(
                ImageProvider(R.drawable.ring),
                context.getString(R.string.widget_locked),
                GlanceModifier.size(32.dp).padding(7.dp),
                colorFilter = ColorFilter.tint(ColorProvider(palette.muted)),
            )
            Text(row.title, GlanceModifier.defaultWeight(), TextStyle(color = ColorProvider(palette.text), fontSize = 14.sp), maxLines = 2)
        } else {
            val tick = actionRunCallback<TickAction>(actionParametersOf(TickAction.TASK to row.id))
            val style = TextStyle(
                color = ColorProvider(if (row.done) palette.muted else palette.text),
                fontSize = 14.sp,
                textDecoration = if (row.done) TextDecoration.LineThrough else TextDecoration.None,
            )
            val colors = CheckboxDefaults.colors(checkedColor = palette.accent, uncheckedColor = palette.muted)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                CheckBox(row.done, tick, GlanceModifier.defaultWeight(), row.title, style, colors, maxLines = 2)
            } else {
                CheckBox(row.done, tick, GlanceModifier.semantics { contentDescription = row.title }, colors = colors)
                Text(row.title, GlanceModifier.defaultWeight(), style, maxLines = 2)
            }
        }
        row.dot?.let {
            val quadrant = when (row.quadrant) {
                "do_first" -> R.string.quadrant_do_first
                "schedule" -> R.string.quadrant_schedule
                "delegate" -> R.string.quadrant_delegate
                "eliminate" -> R.string.quadrant_eliminate
                else -> null
            }
            Image(
                ImageProvider(R.drawable.dot),
                quadrant?.let { id -> context.getString(id) },
                GlanceModifier.size(8.dp),
                colorFilter = ColorFilter.tint(ColorProvider(it)),
            )
        }
    }
}
