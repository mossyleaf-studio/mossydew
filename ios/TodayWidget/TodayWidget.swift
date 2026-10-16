import SwiftUI
import WidgetKit

@main
struct TodayWidget: Widget {
    var body: some WidgetConfiguration {
        StaticConfiguration(kind: "Today", provider: Provider()) { entry in
            TodayView(entry: entry)
        }
        .configurationDisplayName("widget.today")
        .description("widget.description")
        .supportedFamilies([.systemMedium, .systemLarge, .systemExtraLarge])
    }
}

struct TodayEntry: TimelineEntry {
    enum Content {
        case today(Today, staleSince: Date?)
        case signedOut
        case offline
    }

    var date = Date.now
    var content: Content

    static var sample: TodayEntry {
        TodayEntry(content: .today(Today(theme: nil, tasks: [
            TodayTask(id: "1", title: String(localized: "sample.water"), quadrant: "do_first", done: false, nested: false, locked: false),
            TodayTask(id: "2", title: String(localized: "sample.repot"), quadrant: "schedule", done: false, nested: false, locked: true),
            TodayTask(id: "3", title: String(localized: "sample.soil"), quadrant: nil, done: false, nested: true, locked: false),
            TodayTask(id: "4", title: String(localized: "sample.mist"), quadrant: "delegate", done: true, nested: false, locked: false),
        ]), staleSince: nil))
    }

    static func fetch() async -> TodayEntry {
        guard let token = DeviceToken.load() else { return TodayEntry(content: .signedOut) }
        switch await WidgetAPI.send("GET", "widget/today", token: token) {
        case (let json, 200)?:
            if let today = try? JSONDecoder().decode(Today.self, from: json) {
                TodayCache.save(json)
                return TodayEntry(content: .today(today, staleSince: nil))
            }
        case (_, 401)?:
            return TodayEntry(content: .signedOut)
        default:
            break
        }
        guard let cached = TodayCache.load() else { return TodayEntry(content: .offline) }
        return TodayEntry(content: .today(cached.today, staleSince: cached.fetchedAt))
    }
}

struct Provider: TimelineProvider {
    func placeholder(in context: Context) -> TodayEntry {
        .sample
    }

    func getSnapshot(in context: Context, completion: @escaping @Sendable (TodayEntry) -> Void) {
        guard !context.isPreview else { return completion(.sample) }
        Task { completion(await TodayEntry.fetch()) }
    }

    func getTimeline(in context: Context, completion: @escaping @Sendable (Timeline<TodayEntry>) -> Void) {
        Task {
            let entry = await TodayEntry.fetch()
            if case .signedOut = entry.content {
                return completion(Timeline(entries: [entry], policy: .never))
            }
            let tomorrow = Calendar.current.date(byAdding: .day, value: 1, to: Calendar.current.startOfDay(for: entry.date))!
            completion(Timeline(entries: [entry], policy: .after(min(entry.date.addingTimeInterval(30 * 60), tomorrow.addingTimeInterval(60)))))
        }
    }
}
