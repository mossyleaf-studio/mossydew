package studio.mossyleaf.mossydew

import androidx.compose.ui.graphics.Color
import org.json.JSONException
import org.json.JSONObject

data class Palette(
    val background: Color,
    val text: Color,
    val muted: Color,
    val accent: Color,
    val quadrants: Map<String, Color>,
)

data class TaskRow(
    val id: String,
    val title: String,
    val done: Boolean,
    val nested: Boolean,
    val locked: Boolean,
    val quadrant: String?,
    val dot: Color?,
)

data class TodayList(val palette: Palette, val rows: List<TaskRow>) {
    val left: Int get() = rows.count { !it.done }
}

fun todayList(json: String?, pending: Map<String, Boolean>): TodayList {
    val root = try {
        json?.let(::JSONObject)
    } catch (e: JSONException) {
        null
    }
    val palette = palette(root?.optJSONObject("theme"))
    val tasks = root?.optJSONArray("tasks")
    val rows = (0 until (tasks?.length() ?: 0)).mapNotNull { tasks?.optJSONObject(it) }.map { task ->
        val id = task.optString("id")
        val quadrant = task.opt("quadrant") as? String
        TaskRow(
            id = id,
            title = task.optString("title"),
            done = pending[id] ?: task.optBoolean("done"),
            nested = task.optBoolean("nested"),
            locked = task.optBoolean("locked"),
            quadrant = quadrant,
            dot = quadrant?.let { palette.quadrants[it] },
        )
    }
    return TodayList(palette, rows)
}

fun palette(theme: JSONObject?): Palette {
    val accent = color(theme?.optString("accent")) ?: Color(0xFF5B7F3A)
    return if (theme?.optBoolean("dark") == true) {
        Palette(
            background = color(theme.optString("background")) ?: Color(0xFF1D201B),
            text = Color(0xFFECEDE8),
            muted = Color(0xFFA3A59D),
            accent = accent,
            quadrants = mapOf("do_first" to Color(0xFFFB8274), "schedule" to accent, "delegate" to Color(0xFF5EADE2), "eliminate" to Color(0xFFF3AE58)),
        )
    } else {
        Palette(
            background = Color.White,
            text = Color(0xFF1D1E1B),
            muted = Color(0xFF6B6D66),
            accent = accent,
            quadrants = mapOf("do_first" to Color(0xFFA13029), "schedule" to accent, "delegate" to Color(0xFF0E6A9B), "eliminate" to Color(0xFFAA6A00)),
        )
    }
}

private fun color(hex: String?): Color? =
    hex?.removePrefix("#")?.takeIf { it.length == 6 }?.toLongOrNull(16)?.let { Color(0xFF000000 or it) }
