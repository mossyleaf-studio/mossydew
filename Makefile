# Dev stack: app on http://localhost:8090, Vite on :5174, mock mossyleaf accounts on :8091.
# Shared targets live in common.mk and android.mk (synced from ~/Sites/mossyleaf-studio/toolchain).

IMAGE ?= docker.io/injust/mossydew
DEPLOY_HOST ?= debian@duprat.cloud
DEPLOY_DIR ?= /mnt/mossydew
APK_ALIAS = mossydew
APK_CN = MossyDew
# The case generator's time budget runs without coverage (crap skips the timing group), after the other checks.
CHECKS_AFTER = test-timing

include common.mk
include android.mk

IOS_SDK = $(shell xcrun --show-sdk-path)
IOS_TYPECHECK = swiftc -typecheck -swift-version 6 -parse-as-library -target arm64-apple-ios17.0-macabi -sdk $(IOS_SDK) -F $(IOS_SDK)/System/iOSSupport/System/Library/Frameworks -I $(IOS_SDK)/System/iOSSupport/usr/lib/swift

.PHONY: ios ios-typecheck test-timing

test-timing: ## Wall-clock budgets (#[Group('timing')]), without coverage
	$(PHP) php bin/phpunit --group timing

ios: ## Generate and open the iPad/iPhone Xcode project (needs Xcode and brew install xcodegen; signing team in ios/Team.xcconfig)
	cd ios && xcodegen generate && open MossyDew.xcodeproj

ios-typecheck: ## Typecheck the iOS app and its widget against the Mac Catalyst SDK (the Command Line Tools are enough)
	cd ios && $(IOS_TYPECHECK) -module-name MossyDew MossyDew/*.swift Shared/*.swift && $(IOS_TYPECHECK) -module-name TodayWidget -application-extension TodayWidget/*.swift Shared/*.swift
