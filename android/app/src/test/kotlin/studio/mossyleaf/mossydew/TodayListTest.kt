package studio.mossyleaf.mossydew

import androidx.compose.ui.graphics.Color
import org.junit.Assert.assertEquals
import org.junit.Test

class TodayListTest {
    private val today = """
        {"date": "2026-10-06", "theme": null, "tasks": [
            {"id": "W", "title": "Water", "quadrant": "do_first", "done": false, "nested": false, "locked": false},
            {"id": "D", "title": "Drawing", "quadrant": "schedule", "done": false, "nested": false, "locked": true},
            {"id": "S", "title": "Sketch", "quadrant": null, "done": false, "nested": true, "locked": false},
            {"id": "B", "title": "Call the bank", "quadrant": null, "done": true, "nested": false, "locked": false}
        ]}
    """

    @Test
    fun theRowsKeepTheServerOrderInTheDefaultMossTheme() {
        val list = todayList(today, emptyMap())

        assertEquals(
            listOf(
                TaskRow("W", "Water", done = false, nested = false, locked = false, quadrant = "do_first", dot = Color(0xFFA13029)),
                TaskRow("D", "Drawing", done = false, nested = false, locked = true, quadrant = "schedule", dot = Color(0xFF5B7F3A)),
                TaskRow("S", "Sketch", done = false, nested = true, locked = false, quadrant = null, dot = null),
                TaskRow("B", "Call the bank", done = true, nested = false, locked = false, quadrant = null, dot = null),
            ),
            list.rows,
        )
        assertEquals(3, list.left)
        assertEquals(Color.White, list.palette.background)
        assertEquals(Color(0xFF1D1E1B), list.palette.text)
    }

    @Test
    fun aDarkThemePaintsTheWidgetWithItsBackgroundAndAccent() {
        val night = today.replace("\"theme\": null", "\"theme\": {\"background\": \"#1d201b\", \"accent\": \"#93bb6c\", \"dark\": true}")

        val list = todayList(night, emptyMap())

        assertEquals(Color(0xFF1D201B), list.palette.background)
        assertEquals(Color(0xFFECEDE8), list.palette.text)
        assertEquals(Color(0xFF93BB6C), list.palette.accent)
        assertEquals(listOf(Color(0xFFFB8274), Color(0xFF93BB6C), null, null), list.rows.map { it.dot })
    }

    @Test
    fun aLightThemeKeepsAWhiteWidgetAndItsAccent() {
        val terracotta = today.replace("\"theme\": null", "\"theme\": {\"background\": \"#f7f2ec\", \"accent\": \"#b0532c\", \"dark\": false}")

        val list = todayList(terracotta, emptyMap())

        assertEquals(Color.White, list.palette.background)
        assertEquals(Color(0xFFB0532C), list.rows[1].dot)
    }

    @Test
    fun aTickWaitingForTheServerShowsAtOnce() {
        val list = todayList(today, mapOf("W" to true, "B" to false, "gone" to true))

        assertEquals(listOf(true, false, false, false), list.rows.map { it.done })
        assertEquals(3, list.left)
    }

    @Test
    fun nothingCachedOrAnUnreadableAnswerListsNoTask() {
        assertEquals(emptyList<TaskRow>(), todayList(null, emptyMap()).rows)
        assertEquals(emptyList<TaskRow>(), todayList("<html>", emptyMap()).rows)
    }
}
