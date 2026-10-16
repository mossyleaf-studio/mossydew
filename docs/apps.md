# Android and iPad apps

Both apps show https://mossydew.mossyleaf.studio in a WebView and add a home-screen **Today** widget that talks to `/widget/*` with a device token. Rules: [business/apps.md](business/apps.md). The widget needs the `/widget/*` endpoints in production: deploy the server first.

## Android (`android/`)

Kotlin, Jetpack Glance widget, minSdk 26 (Android 8). Built on the Mac, outside Docker, with a JDK 17 to 26 whose `java` is on the PATH (Gradle 9.7 does not run on 27, which `brew install openjdk` installs): `brew install openjdk@21`, then `sudo ln -sfn "$(brew --prefix openjdk@21)/libexec/openjdk.jdk" /Library/Java/JavaVirtualMachines/openjdk-21.jdk`.

```bash
make android-sdk     # once: Android command-line tools, platform 36, build and platform tools in ~/Library/Android/sdk
make apk             # signed release APK → android/app/build/outputs/apk/release/app-release.apk
make apk-install     # install or update it on the phone adb sees
make android-test    # JVM unit tests + lint
```

- **Signing key**: the first `make apk` creates `android/keystore.jks` and `android/keystore.properties` (git-ignored); it stops when only one of them is there. Back both up (a password manager): Android installs an update only when it is signed with the same key. Lost key = uninstall, install the new APK, sign in again.
- **Installing**: on the phone, turn on Developer options › USB debugging, plug it in and run `make apk-install`. Over Wi-Fi: Developer options › Wireless debugging, then `~/Library/Android/sdk/platform-tools/adb pair <ip:port>` and `adb connect <ip:port>` with the codes it shows. Without adb, copy the APK to the phone and open it, allowing the file manager to install unknown apps. Updates install over the app and keep the session.
- **Widget**: long-press the home screen › Widgets › MossyDew › Today. It stays signed out until the app has been opened and signed in once.
- `versionCode` is the commit count, so commit before building an update.

## iPad (`ios/`)

SwiftUI + WKWebView, interactive WidgetKit widget (iPadOS 17+; also runs on iPhone). `ios/project.yml` is the XcodeGen spec; the Xcode project, Info.plists and entitlements are generated and git-ignored.

1. Install Xcode from the App Store, open it once, then `sudo xcode-select -s /Applications/Xcode.app/Contents/Developer` and `brew install xcodegen`.
2. Xcode › Settings › Accounts › + › your Apple ID: creates your **Personal Team** (free).
3. If Xcode says `studio.mossyleaf.mossydew` is unavailable, change `MOSSYDEW_BUNDLE_ID` and `MOSSYDEW_APP_GROUP` in `ios/project.yml` to your own prefix (`fr.<you>.mossydew`, `group.fr.<you>.mossydew`).
4. `make ios` generates and opens the project. Pick your team in Signing & Capabilities for both targets (MossyDew and TodayWidget), or keep it across regenerations with `echo 'DEVELOPMENT_TEAM = <team id>' > ios/Team.xcconfig` (the `OU=` of `security find-certificate -c "Apple Development" -p | openssl x509 -noout -subject`, available after the first signed build).
5. Plug in the iPad, unlock it, tap Trust. On the iPad: Settings › Privacy & Security › Developer Mode, restart, confirm.
6. Choose the iPad as destination and press ⌘R. The first launch is refused: Settings › General › VPN & Device Management › your Apple ID › Trust, then ⌘R again.
7. Sign in inside the app, then long-press the Home Screen › Edit › Add Widget › MossyDew › Today.

- **Free Apple ID**: the signature lasts **7 days**; after that the app and its widget no longer open until you plug the iPad in and press ⌘R again (data and session are kept). Limits: 3 sideloaded apps per device, 10 App IDs per 7 days (this project uses 2). A paid Apple Developer Program team ($99/year) signs for a year: put its team id in `ios/Team.xcconfig`.
- `make ios-typecheck` typechecks both targets against the Mac Catalyst SDK with only the Command Line Tools, no Xcode needed.

## Limits of the WebView apps

- **Passkeys** don't work in the apps (they need Associated Domains on iOS and a Digital Asset Links file on accounts.mossyleaf.studio): sign in with the password. **Google sign-in** is refused by Google inside WebViews.
- Links to MossyDew, such as friend invites (`/friends/join/…`), open in the browser, not in the app (no App Links or universal links): the invitee signs in there to accept, and the friend then shows up in the app.
- No level-up reveal or celebration for tasks ticked in the widget; the app shows the new level next time it opens.
- The iPad app has no offline page (WKWebView runs the site's service worker only for App-Bound Domains): offline it stays blank and reloads once the network is back. A widget tick that cannot reach MossyDew is undone on the iPad, kept and sent later on Android.
- iPadOS does not run widget toggles while the iPad is locked. After Android's Force stop (some battery savers do it), the widget ignores taps until the app is opened once or its 30-minute refresh runs.
