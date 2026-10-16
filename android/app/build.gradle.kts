import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.plugin.compose")
}

val keystore: File = rootProject.file("keystore.properties")
val code = providers.gradleProperty("versionCode").orNull?.toInt() ?: 1

android {
    namespace = "studio.mossyleaf.mossydew"
    compileSdk = 36

    defaultConfig {
        applicationId = "studio.mossyleaf.mossydew"
        minSdk = 26
        targetSdk = 36
        versionCode = code
        versionName = code.toString()
        buildConfigField("String", "BASE_URL", "\"${providers.gradleProperty("mossydewUrl").getOrElse("https://mossydew.mossyleaf.studio")}\"")
    }

    signingConfigs {
        if (keystore.exists()) {
            create("release") {
                val properties = Properties().apply { keystore.inputStream().use(::load) }
                storeFile = rootProject.file(properties.getProperty("storeFile"))
                storePassword = properties.getProperty("storePassword")
                keyAlias = properties.getProperty("keyAlias")
                keyPassword = properties.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.findByName("release")
        }
    }

    buildFeatures {
        buildConfig = true
        compose = true
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}

dependencies {
    implementation("androidx.activity:activity:1.13.0")
    implementation("androidx.glance:glance-appwidget:1.2.0")
    implementation("androidx.work:work-runtime:2.12.0")
    testImplementation("junit:junit:4.13.2")
    testImplementation("org.json:json:20260814")
}
