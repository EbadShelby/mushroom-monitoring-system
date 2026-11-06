#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <Wire.h>
#include <BH1750.h>

// ─── WiFi ───────────────────────────────────────────
const char* ssid     = "wifi_username";
const char* password = "wifi_password";

// ─── Server URLs ────────────────────────────────────
const char* sensorUrl   = "http://YOUR_PC_IP:8080/api/sensor-data";
const char* actuatorUrl = "http://YOUR_PC_IP:8080/api/actuator-commands";

// ─── Pin Definitions ────────────────────────────────
#define DHTPIN    4
#define DHTTYPE   DHT22
#define MQ135_PIN 34
#define SOIL_PIN  35

// ─── Relay Pin Assignments ─────────────────────────
// N1 = Fan, N3 = Humidifier, N2 = LED Light
#define RELAY_FAN        26   // N1
#define RELAY_HUMIDIFIER 14   // N3
#define RELAY_LED        27   // N2

// ─── Soil Calibration ───────────────────────────────
#define DRY_VALUE     2559
#define WET_VALUE     1354
#define SOIL_CRITICAL 20
#define SOIL_DRY      30

// ─── Objects ───────────────────────────────────────
DHT    dht(DHTPIN, DHTTYPE);
BH1750 lightMeter;

// ─── Timing ─────────────────────────────────────────
unsigned long lastSend = 0;
const unsigned long SEND_INTERVAL = 10000; // 10 seconds for testing
                                           // change to 30000 for production

void sendSensorData(float temp, float hum, int co2,
                    float light, int soil, String soilStatus);
void pollActuatorCommands();

void setup() {
  Serial.begin(115200);
  Serial.println("=== IoT Mushroom Monitoring System ===");
  Serial.println("Gray Oyster Mushroom — Pleurotus sajor-caju");
  Serial.println("Relay: N1=Fan | N3=Humidifier | N2=LED");

  pinMode(RELAY_FAN,        OUTPUT); digitalWrite(RELAY_FAN,        HIGH);
  pinMode(RELAY_HUMIDIFIER, OUTPUT); digitalWrite(RELAY_HUMIDIFIER, HIGH);
  pinMode(RELAY_LED,        OUTPUT); digitalWrite(RELAY_LED,        HIGH);
  Serial.println("✅ Relays initialized — all OFF");

  dht.begin();
  Serial.println("✅ DHT22 ready");

  Wire.begin(21, 22);
  if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
    Serial.println("✅ BH1750 ready");
  } else {
    Serial.println("❌ BH1750 failed — check wiring");
  }

  Serial.println("✅ MQ-135 ready");
  Serial.println("✅ Soil sensor ready");

  Serial.println("⏳ Warming up MQ-135 (30 seconds)...");
  for (int i = 30; i > 0; i--) {
    Serial.printf("   %d seconds remaining...\n", i);
    delay(1000);
  }
  Serial.println("✅ MQ-135 warmup complete");

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  Serial.print("📶 Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  WiFi.setSleep(false);

  Serial.println("\n✅ WiFi connected!");
  Serial.println("📍 IP: " + WiFi.localIP().toString());
  Serial.println("======================================");
  Serial.print("   WiFi IP: ");
}

void loop() {
  float humidity    = dht.readHumidity();
  float temperature = dht.readTemperature();

  float lux = lightMeter.readLightLevel();

  int co2Raw = analogRead(MQ135_PIN);

  int soilRaw      = analogRead(SOIL_PIN);
  int soilMoisture = map(soilRaw, DRY_VALUE, WET_VALUE, 0, 100);
  soilMoisture     = constrain(soilMoisture, 0, 100);

  String soilStatus;
  if (soilMoisture < SOIL_CRITICAL) {
    soilStatus = "critical";
  } else if (soilMoisture < SOIL_DRY) {
    soilStatus = "dry";
  } else {
    soilStatus = "moist";
  }

  String airQuality;
  if (co2Raw < 1000) {
    airQuality = "Good";
  } else if (co2Raw < 2000) {
    airQuality = "Moderate";
  } else {
    airQuality = "Poor";
  }

  String lightCondition;
  if (lux < 10) {
    lightCondition = "Dark";
  } else if (lux < 200) {
    lightCondition = "Dim";
  } else if (lux < 1000) {
    lightCondition = "Moderate";
  } else {
    lightCondition = "Bright";
  }

  Serial.println("========================================");
  Serial.println("       SENSOR READINGS SUMMARY          ");
  Serial.println("========================================");

  if (!isnan(temperature) && !isnan(humidity)) {
    Serial.printf("🌡️  Temperature : %.1f °C\n", temperature);
    Serial.printf("💧 Humidity    : %.1f %% \n", humidity);
  } else {
    Serial.println("❌ DHT22: Failed to read");
  }

  Serial.printf("☀️  Light       : %.1f lux (%s)\n", lux, lightCondition.c_str());
  Serial.printf("💨 CO2 Raw     : %d (%s)\n", co2Raw, airQuality.c_str());
  Serial.printf("🌱 Soil        : %d%% | Raw: %d | Status: %s\n",
                soilMoisture, soilRaw, soilStatus.c_str());

  Serial.println("----------------------------------------");
  Serial.printf("🔌 Fan (N1)        : %s\n",
    digitalRead(RELAY_FAN) == LOW ? "ON" : "OFF");
  Serial.printf("💦 Humidifier (N3) : %s\n",
    digitalRead(RELAY_HUMIDIFIER) == LOW ? "ON" : "OFF");
  Serial.printf("💡 LED Light (N2)  : %s\n",
    digitalRead(RELAY_LED) == LOW ? "ON" : "OFF");
  Serial.println("========================================");

  unsigned long now = millis();
  if (now - lastSend >= SEND_INTERVAL) {
    lastSend = now;

    if (!isnan(temperature) && !isnan(humidity) &&
        WiFi.status() == WL_CONNECTED) {
      sendSensorData(temperature, humidity, co2Raw,
                     lux, soilMoisture, soilStatus);
      delay(1000);
      pollActuatorCommands();
    } else {
      Serial.println("⚠️  Skipping send — bad reading or no WiFi");
    }
  }

  delay(2000); // DHT22 minimum interval
}

void sendSensorData(float temp, float hum, int co2,
                    float light, int soil, String soilStatus) {
  HTTPClient http;
  http.begin(sensorUrl);        // <-- CHANGED: was `http.begin(sensorUrl)`

  // http.begin(sensorUrl);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  String body = "{";
  body += "\"temperature\":"   + String(temp, 1)  + ",";
  body += "\"humidity\":"      + String(hum, 1)   + ",";
  body += "\"co2_raw\":"       + String(co2)       + ",";
  body += "\"light_level\":"   + String(light, 1) + ",";
  body += "\"soil_moisture\":" + String(soil)      + ",";
  body += "\"soil_status\":\"" + soilStatus        + "\"";
  body += "}";

  Serial.println("📡 Sending to Laravel...");
  Serial.println("   Payload: " + body);

  int code = http.POST(body);
  Serial.printf("   Response: HTTP %d\n", code);
  if (code > 0) {
    Serial.println("   Body: " + http.getString());
  }
  http.end();
}

void pollActuatorCommands() {
  HTTPClient http;
  http.begin(actuatorUrl);

  // http.begin(actuatorUrl);
  http.addHeader("Accept", "application/json");

  Serial.println("🔁 Polling actuator commands...");

  int code = http.GET();
  if (code == 200) {
    String payload = http.getString();
    Serial.println("   Commands: " + payload);

    if (payload.indexOf("\"fan\":\"on\"") >= 0) {
      digitalWrite(RELAY_FAN, LOW);
      Serial.println("   🌀 Fan (N1) → ON");
    } else if (payload.indexOf("\"fan\":\"off\"") >= 0) {
      digitalWrite(RELAY_FAN, HIGH);
      Serial.println("   🌀 Fan (N1) → OFF");
    }

    if (payload.indexOf("\"humidifier\":\"on\"") >= 0) {
      digitalWrite(RELAY_HUMIDIFIER, LOW);
      Serial.println("   💦 Humidifier (N3) → ON");
    } else if (payload.indexOf("\"humidifier\":\"off\"") >= 0) {
      digitalWrite(RELAY_HUMIDIFIER, HIGH);
      Serial.println("   💦 Humidifier (N3) → OFF");
    }

    if (payload.indexOf("\"led\":\"on\"") >= 0) {
      digitalWrite(RELAY_LED, LOW);
      Serial.println("   💡 LED (N2) → ON");
    } else if (payload.indexOf("\"led\":\"off\"") >= 0) {
      digitalWrite(RELAY_LED, HIGH);
      Serial.println("   💡 LED (N2) → OFF");
    }

  } else {
    Serial.printf("   ⚠️  HTTP %d — no commands\n", code);
  }
  http.end();
}
