#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include "esp_camera.h"

// ─── WiFi ───────────────────────────────────────────
const char* ssid     = "wifi_username";
const char* password = "wifi_password";

// ─── Server ─────────────────────────────────────────
const char* snapshotUrl = "http://192.168.254.148:8080/api/camera-snapshot";
const char* cameraToken = "CHANGE_ME_LONG_RANDOM_TOKEN";   // must match CAMERA_TOKEN in Laravel .env

// ─── Timing ─────────────────────────────────────────
const unsigned long CAPTURE_INTERVAL = 3UL * 60UL * 60UL * 1000UL;  // 3 hours
const unsigned long RETRY_INTERVAL   = 30UL * 1000UL;               // retry after a failed upload
const unsigned long WIFI_TIMEOUT_MS  = 20UL * 1000UL;

unsigned long lastCapture = 0;
unsigned long retryAt = 0;
bool firstCaptureDone = false;

// ─── AI-Thinker ESP32-CAM Pinout ────────────────────
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27

#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

bool initCamera() {
  camera_config_t config = {};
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer   = LEDC_TIMER_0;
  config.pin_d0       = Y2_GPIO_NUM;
  config.pin_d1       = Y3_GPIO_NUM;
  config.pin_d2       = Y4_GPIO_NUM;
  config.pin_d3       = Y5_GPIO_NUM;
  config.pin_d4       = Y6_GPIO_NUM;
  config.pin_d5       = Y7_GPIO_NUM;
  config.pin_d6       = Y8_GPIO_NUM;
  config.pin_d7       = Y9_GPIO_NUM;
  config.pin_xclk     = XCLK_GPIO_NUM;
  config.pin_pclk     = PCLK_GPIO_NUM;
  config.pin_vsync    = VSYNC_GPIO_NUM;
  config.pin_href     = HREF_GPIO_NUM;
  config.pin_sccb_sda = SIOD_GPIO_NUM;
  config.pin_sccb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn     = PWDN_GPIO_NUM;
  config.pin_reset    = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  config.grab_mode    = CAMERA_GRAB_LATEST;

  if (psramFound()) {
    config.frame_size   = FRAMESIZE_SVGA;   // 800x600
    config.jpeg_quality = 12;               // lower = higher quality
    config.fb_count     = 2;
    config.fb_location  = CAMERA_FB_IN_PSRAM;
  } else {
    config.frame_size   = FRAMESIZE_VGA;    // 640x480 fallback
    config.jpeg_quality = 14;
    config.fb_count     = 1;
    config.fb_location  = CAMERA_FB_IN_DRAM;
  }

  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("❌ Camera init failed with error 0x%x\n", err);
    return false;
  }

  Serial.println("✅ OV2640 Camera ready");
  return true;
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.begin(ssid, password);

  Serial.print("📶 Connecting to WiFi");
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    if (millis() - start > WIFI_TIMEOUT_MS) {
      Serial.println("\n❌ WiFi timeout — restarting...");
      delay(1000);
      ESP.restart();
    }
    delay(500);
    Serial.print(".");
  }
  WiFi.setSleep(false);

  Serial.println("\n✅ WiFi connected!");
  Serial.println("📍 IP: " + WiFi.localIP().toString());
}

// Returns true only if Laravel accepted the upload (HTTP 2xx)
bool captureAndUpload() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️  WiFi disconnected — attempting reconnect...");
    WiFi.reconnect();
    return false;
  }

  // Discard a few frames so auto-exposure settles after idling
  for (int i = 0; i < 3; i++) {
    camera_fb_t* warmup = esp_camera_fb_get();
    if (warmup) esp_camera_fb_return(warmup);
    delay(150);
  }

  camera_fb_t* fb = esp_camera_fb_get();
  if (!fb) {
    Serial.println("❌ Camera capture failed");
    return false;
  }

  Serial.printf("📸 Captured %u bytes — uploading to Laravel...\n", fb->len);

  HTTPClient http;
  http.setTimeout(10000);
  http.begin(snapshotUrl);
  http.addHeader("Content-Type", "image/jpeg");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Camera-Token", cameraToken);

  int code = http.POST(fb->buf, fb->len);
  Serial.printf("   Response: HTTP %d\n", code);
  if (code > 0) {
    Serial.println("   Body: " + http.getString());
  }

  http.end();
  esp_camera_fb_return(fb);

  return code >= 200 && code < 300;
}

void setup() {
  Serial.begin(115200);
  Serial.println("\n=== MycoSense ESP32-CAM ===");

  if (!initCamera()) {
    Serial.println("Restarting in 5 seconds...");
    delay(5000);
    ESP.restart();
  }

  connectWiFi();
}

void loop() {
  unsigned long now = millis();

  bool due = !firstCaptureDone || (now - lastCapture >= CAPTURE_INTERVAL);

  if (due && now >= retryAt) {
    if (captureAndUpload()) {
      firstCaptureDone = true;
      lastCapture = now;
      retryAt = 0;
    } else {
      retryAt = now + RETRY_INTERVAL;   // try again in 30 s instead of waiting 3 h
    }
  }

  delay(1000);
}