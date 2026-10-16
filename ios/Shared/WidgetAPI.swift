import Foundation
import SwiftUI

enum MossyDew {
    static let site = URL(string: "https://mossydew.mossyleaf.studio/")!
    static let accountsHost = "accounts.mossyleaf.studio"
    static let appGroup = Bundle.main.object(forInfoDictionaryKey: "MossyDewAppGroup") as! String
    static var defaults: UserDefaults? { UserDefaults(suiteName: appGroup) }
}

struct Today: Decodable {
    var theme: Theme?
    var tasks: [TodayTask]
}

struct Theme: Decodable {
    static let moss = Theme(background: "#f5f5f3", accent: "#5b7f3a", dark: false)

    var background: String
    var accent: String
    var dark: Bool
}

struct TodayTask: Decodable, Identifiable {
    var id: String
    var title: String
    var quadrant: String?
    var done: Bool
    var nested: Bool
    var locked: Bool
}

enum DeviceToken {
    static func load() -> String? {
        MossyDew.defaults?.string(forKey: "deviceToken")
    }

    static func save(_ token: String) {
        MossyDew.defaults?.set(token, forKey: "deviceToken")
    }

    static func delete() {
        MossyDew.defaults?.removeObject(forKey: "deviceToken")
    }
}

enum TodayCache {
    static func save(_ json: Data) {
        MossyDew.defaults?.set(json, forKey: "today")
        MossyDew.defaults?.set(Date.now, forKey: "fetchedAt")
    }

    static func load() -> (today: Today, fetchedAt: Date)? {
        guard let json = MossyDew.defaults?.data(forKey: "today"),
              let fetchedAt = MossyDew.defaults?.object(forKey: "fetchedAt") as? Date,
              Calendar.current.isDateInToday(fetchedAt),
              let today = try? JSONDecoder().decode(Today.self, from: json) else { return nil }
        return (today, fetchedAt)
    }

    static func clear() {
        MossyDew.defaults?.removeObject(forKey: "today")
    }
}

enum WidgetAPI {
    @discardableResult
    static func send(_ method: String, _ path: String, token: String) async -> (data: Data, status: Int)? {
        var request = URLRequest(url: MossyDew.site.appending(path: path), timeoutInterval: 10)
        request.httpMethod = method
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        guard let (data, response) = try? await URLSession.shared.data(for: request),
              let status = (response as? HTTPURLResponse)?.statusCode else { return nil }
        if status == 401, DeviceToken.load() == token {
            DeviceToken.delete()
            TodayCache.clear()
        }
        return (data, status)
    }
}

extension Color {
    init(hex: String) {
        let rgb = Int(hex.dropFirst(), radix: 16) ?? 0
        self.init(red: Double(rgb >> 16 & 0xFF) / 255, green: Double(rgb >> 8 & 0xFF) / 255, blue: Double(rgb & 0xFF) / 255)
    }
}
