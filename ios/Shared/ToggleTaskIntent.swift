import AppIntents

struct ToggleTaskIntent: AppIntent {
    static let title: LocalizedStringResource = "intent.title"
    static let isDiscoverable = false

    @Parameter(title: "intent.task") var id: String
    @Parameter(title: "intent.done") var done: Bool

    init() {}

    init(id: String, done: Bool) {
        self.id = id
        self.done = done
    }

    func perform() async throws -> some IntentResult {
        if let token = DeviceToken.load() {
            await WidgetAPI.send("POST", "widget/tasks/\(id)/\(done ? "complete" : "reopen")", token: token)
        }
        return .result()
    }
}
