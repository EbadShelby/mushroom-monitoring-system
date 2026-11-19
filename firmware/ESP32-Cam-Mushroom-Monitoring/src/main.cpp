#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include "esp_camera.h"

// ─── WiFi ───────────────────────────────────────────
const char* ssid     = "wifi_username";
const char* password = "wifi_password";

// ─── Server ─────────────────────────────────────────
const char* snapshotUrl = "https://mycosense.online/api/camera-snapshot";
const char* commandUrl  = "https://mycosense.online/api/camera-command";
const char* cameraToken = "CHANGE_ME_LONG_RANDOM_TOKEN";   // must match CAMERA_TOKEN in Laravel .env

// ─── Timing ─────────────────────────────────────────
const unsigned long RETRY_INTERVAL        = 30UL * 1000UL;   // retry after a failed upload
const unsigned long WIFI_TIMEOUT_MS       = 20UL * 1000UL;
const unsigned long COMMAND_POLL_INTERVAL = 5UL * 1000UL;    // check Laravel for scheduled or manual captures

unsigned long lastCommandPoll = 0;
unsigned long retryAt = 0;
bool pendingCapture = false;
bool pendingFlash = false;

// Onboard bright white flash LED on AI-Thinker ESP32-CAM
#define FLASH_LED_PIN 4

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

  sensor_t* s = esp_camera_sensor_get();
  if (s != nullptr) {
    s->set_hmirror(s, 1); // Fix left-right mirror
    // s->set_vflip(s, 1); // Uncomment if mounted upside down
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
bool captureAndUpload(bool useFlash) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠️  WiFi disconnected — attempting reconnect...");
    WiFi.reconnect();
    return false;
  }

  if (useFlash) {
    digitalWrite(FLASH_LED_PIN, HIGH);
    delay(250);
  }

  // Discard a few frames so auto-exposure settles after idling
  for (int i = 0; i < 3; i++) {
    camera_fb_t* warmup = esp_camera_fb_get();
    if (warmup) esp_camera_fb_return(warmup);
    delay(150);
  }

  camera_fb_t* fb = esp_camera_fb_get();
  digitalWrite(FLASH_LED_PIN, LOW);

  if (!fb) {
    Serial.println("❌ Camera capture failed");
    return false;
  }

  Serial.printf("📸 Captured %u bytes — uploading to Laravel...\n", fb->len);

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  http.setTimeout(10000);
  http.begin(client, snapshotUrl);
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
  pinMode(FLASH_LED_PIN, OUTPUT);
  digitalWrite(FLASH_LED_PIN, LOW);

  Serial.begin(115200);
  Serial.println("\n=== MycoSense ESP32-CAM ===");

  if (!initCamera()) {
    Serial.println("Restarting in 5 seconds...");
    delay(5000);
    ESP.restart();
  }

  connectWiFi();
}

// Asks Laravel whether a scheduled or manual capture is pending
bool checkCameraCommand(bool& useFlash) {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  http.setTimeout(5000);
  http.begin(client, commandUrl);
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Camera-Token", cameraToken);

  int code = http.GET();
  bool requested = false;
  useFlash = false;

  if (code == 200) {
    String payload = http.getString();
    requested = payload.indexOf("\"capture\":true") >= 0;
    useFlash  = payload.indexOf("\"flash\":true") >= 0;
  }

  http.end();
  return requested;
}

void loop() {
  unsigned long now = millis();

  if (now - lastCommandPoll >= COMMAND_POLL_INTERVAL) {
    lastCommandPoll = now;

    bool useFlash = false;
    if (checkCameraCommand(useFlash)) {
      Serial.printf("📸 Capture requested by Laravel (flash: %s)\n", useFlash ? "ON" : "OFF");
      pendingCapture = true;
      pendingFlash = useFlash;
      retryAt = 0;
    }
  }

  if (pendingCapture && now >= retryAt) {
    if (captureAndUpload(pendingFlash)) {
      pendingCapture = false;
      pendingFlash = false;
      retryAt = 0;
    } else {
      retryAt = now + RETRY_INTERVAL;
    }
  }

  delay(500);
}