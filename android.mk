# Android app targets of the Mossyleaf apps that ship one (android/).
# Synced from ~/Sites/mossyleaf-studio/toolchain: edit it there, then run its ./sync.
# The project Makefile sets APK_ALIAS (keystore alias) and APK_CN (certificate name) before including it.

ANDROID_HOME ?= $(HOME)/Library/Android/sdk
ANDROID_TOOLS_ZIP = commandlinetools-mac_arm64-15859902_latest.zip
ANDROID_TOOLS_SHA256 = 835b62a26162b229b441d1f6d4680383815a270809eb33522c0d480fa5002c4e
SDKMANAGER = $(ANDROID_HOME)/cmdline-tools/latest/bin/sdkmanager
ANDROID_ENV = JAVA_HOME=$(shell java -XshowSettings:properties -version 2>&1 | sed -n 's/^ *java.home = //p') ANDROID_HOME=$(ANDROID_HOME)
GRADLEW = cd android && $(ANDROID_ENV) ./gradlew
APK = android/app/build/outputs/apk/release/app-release.apk

.PHONY: android-sdk android-test apk apk-install

android-sdk: ## Install the Android SDK on the Mac (command-line tools 22.0 for arm64, then platform and build tools, in ANDROID_HOME)
	@test -x $(SDKMANAGER) || ( tmp=$$(mktemp -d) && cd $$tmp && \
		curl -fsSLO -C - --retry 5 --retry-all-errors --speed-limit 10000 --speed-time 30 https://dl.google.com/android/repository/$(ANDROID_TOOLS_ZIP) && \
		echo "$(ANDROID_TOOLS_SHA256)  $(ANDROID_TOOLS_ZIP)" | shasum -a 256 -c - && \
		unzip -q $(ANDROID_TOOLS_ZIP) && mkdir -p $(ANDROID_HOME)/cmdline-tools && \
		mv cmdline-tools $(ANDROID_HOME)/cmdline-tools/latest && rm -rf $$tmp )
	yes | $(ANDROID_ENV) $(SDKMANAGER) --licenses
	$(ANDROID_ENV) $(SDKMANAGER) "platforms;android-36" "build-tools;36.0.0" platform-tools

android-test: ## Android app JVM unit tests and lint
	$(GRADLEW) test lintRelease

apk: ## Build the signed Android release APK (creates android/keystore.jks and keystore.properties once: back them up, updates need the same key)
	@[ -f android/keystore.jks ] || [ -f android/keystore.properties ] || ( password=$$(openssl rand -hex 24) && umask 077 && \
		KEYSTORE_PASSWORD=$$password keytool -genkeypair -keystore android/keystore.jks -storetype PKCS12 \
			-alias $(APK_ALIAS) -keyalg RSA -keysize 4096 -validity 10000 -dname CN=$(APK_CN) \
			-storepass:env KEYSTORE_PASSWORD -keypass:env KEYSTORE_PASSWORD && \
		printf 'storeFile=keystore.jks\nstorePassword=%s\nkeyAlias=$(APK_ALIAS)\n' $$password > android/keystore.properties )
	@[ -f android/keystore.jks ] && [ -f android/keystore.properties ] || { echo 'android/keystore.jks and android/keystore.properties go together: restore the missing one from your backup' >&2; exit 1; }
	$(GRADLEW) assembleRelease -PversionCode=$$(git rev-list --count HEAD)

apk-install: ## Install the release APK on the phone adb sees (USB or Wi-Fi pairing), over the installed version
	$(ANDROID_HOME)/platform-tools/adb install -r $(APK)
