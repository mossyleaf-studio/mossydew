import AppIntents
import SwiftUI
import WidgetKit

struct TodayView: View {
    let entry: TodayEntry
    @Environment(\.widgetFamily) private var family

    private var today: Today? {
        guard case .today(let today, _) = entry.content else { return nil }
        return today
    }

    var body: some View {
        let palette = Palette(today?.theme ?? .moss)
        let open = today?.tasks.filter { !$0.done }.count ?? 0
        VStack(alignment: .leading, spacing: 6) {
            HStack(alignment: .firstTextBaseline) {
                Text("widget.today")
                    .font(.headline.bold())
                    .foregroundStyle(palette.accent)
                    .widgetAccentable()
                Spacer(minLength: 8)
                if open > 0 {
                    Text("widget.left \(open)")
                        .font(.caption)
                        .foregroundStyle(palette.muted)
                }
            }
            switch entry.content {
            case .today(let today, let staleSince):
                tasks(today, open: open, staleSince: staleSince, palette: palette)
            case .signedOut:
                message("widget.signedOut", palette)
            case .offline:
                message("widget.offline", palette)
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .containerBackground(palette.background, for: .widget)
        .widgetURL(URL(string: "mossydew://today"))
    }

    private func message(_ key: LocalizedStringKey, _ palette: Palette) -> some View {
        Text(key)
            .font(.subheadline)
            .foregroundStyle(palette.muted)
    }

    @ViewBuilder
    private func tasks(_ today: Today, open: Int, staleSince: Date?, palette: Palette) -> some View {
        let perColumn = (family == .systemMedium ? 3 : 8) - (open == 0 ? 1 : 0)
        let columns = family == .systemExtraLarge ? 2 : 1
        let shown = Array(today.tasks.prefix(perColumn * columns))
        let hidden = today.tasks.count - shown.count
        if open == 0 {
            message("widget.empty", palette)
        }
        HStack(alignment: .top, spacing: 16) {
            ForEach(0..<columns, id: \.self) { column in
                VStack(alignment: .leading, spacing: 4) {
                    ForEach(shown.dropFirst(column * perColumn).prefix(perColumn)) { task in
                        TaskRow(task: task, palette: palette, lines: family == .systemMedium ? 1 : 2)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
        }
        .font(family == .systemMedium ? .footnote : .subheadline)
        Spacer(minLength: 0)
        if hidden > 0 || staleSince != nil {
            HStack {
                if hidden > 0 {
                    Text("widget.more \(hidden)")
                }
                Spacer(minLength: 8)
                if let staleSince {
                    Text("widget.updated \(staleSince, format: .dateTime.hour().minute())")
                }
            }
            .font(.caption2)
            .foregroundStyle(palette.muted)
        }
    }
}

struct TaskRow: View {
    let task: TodayTask
    let palette: Palette
    let lines: Int

    var body: some View {
        HStack(spacing: 6) {
            if task.locked {
                TaskLabel(symbol: "circle.dashed", tint: palette.muted, done: false, palette: palette, lines: lines) {
                    Text(task.title)
                }
                .accessibilityElement(children: .ignore)
                .accessibilityLabel(Text(task.title))
                .accessibilityValue(Text("widget.locked"))
            } else {
                Toggle(isOn: task.done, intent: ToggleTaskIntent(id: task.id, done: !task.done)) {
                    Text(task.title)
                }
                .toggleStyle(CheckStyle(palette: palette, lines: lines))
            }
            Spacer(minLength: 0)
            if let quadrant = palette.quadrant(task.quadrant) {
                Circle()
                    .fill(quadrant.color)
                    .frame(width: 6, height: 6)
                    .accessibilityElement()
                    .accessibilityLabel(Text(quadrant.name))
            }
        }
        .padding(.leading, task.nested ? 20 : 0)
    }
}

struct CheckStyle: ToggleStyle {
    let palette: Palette
    let lines: Int

    func makeBody(configuration: Configuration) -> some View {
        TaskLabel(symbol: configuration.isOn ? "checkmark.circle.fill" : "circle", tint: palette.accent, done: configuration.isOn, palette: palette, lines: lines) {
            configuration.label
        }
    }
}

struct TaskLabel<Title: View>: View {
    let symbol: String
    let tint: Color
    let done: Bool
    let palette: Palette
    let lines: Int
    @ViewBuilder let title: Title

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 6) {
            Image(systemName: symbol)
                .foregroundStyle(tint)
                .widgetAccentable()
            title
                .lineLimit(lines)
                .strikethrough(done)
                .foregroundStyle(done ? palette.muted : palette.text)
        }
    }
}

struct Palette {
    let dark: Bool
    let background: Color
    let accent: Color
    let text: Color
    let muted: Color

    init(_ theme: Theme) {
        dark = theme.dark
        background = theme.dark ? Color(hex: theme.background) : .white
        accent = Color(hex: theme.accent)
        text = Color(hex: theme.dark ? "#ecede8" : "#1d1e1b")
        muted = Color(hex: theme.dark ? "#a3a59d" : "#6b6d66")
    }

    func quadrant(_ quadrant: String?) -> (color: Color, name: LocalizedStringKey)? {
        switch quadrant {
        case "do_first": (Color(hex: dark ? "#fb8274" : "#a13029"), "quadrant.doFirst")
        case "schedule": (accent, "quadrant.schedule")
        case "delegate": (Color(hex: dark ? "#5eade2" : "#0e6a9b"), "quadrant.delegate")
        case "eliminate": (Color(hex: dark ? "#f3ae58" : "#aa6a00"), "quadrant.eliminate")
        default: nil
        }
    }
}
