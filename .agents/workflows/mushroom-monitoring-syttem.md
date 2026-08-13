---
description: IoT system monitoring oyster mushroom cultivation at CotSU. ESP32 sends sensor data to Laravel, stored in MySQL and Firebase. Vue.js dashboard shows live readings, automates actuators (with manual override), sends SMS alerts, and generates cycle reports.
---

# IoT Mushroom Monitoring System — Project Rules

## Project Identity
- Project name: IoT-Based Environmental Monitoring System for Oyster Mushroom Cultivation
- Institution: Cotabato State University — BS Agriculture Program
- Mushroom variety: Gray Oyster Mushroom (Pleurotus sajor-caju)
- Growing environment: Indoor enclosed box (PVC frame, plywood/glass walls)

## Tech Stack — Never Deviate From This

### Frontend
- Framework: Vue.js 3 (Composition API, `<script setup>` syntax only)
- Routing: Inertia.js v3 (no Vue Router — Inertia handles all navigation)
- Styling: Tailwind CSS v4 (utility classes only — no custom CSS unless absolutely necessary)
- UI Components: Reka UI + Lucide Icons (@lucide/vue)
- Charts: ApexCharts via vue3-apexcharts
- State management: Pinia
- HTTP client: Axios
- Notifications: Vue Sonner (toast notifications)
- Real-time: Firebase Realtime Database JS SDK
- Language: TypeScript

### Backend
- Framework: Laravel 13
- Language: PHP
- Auth: Laravel Fortify (already configured via starter kit)
- PDF: barryvdh/laravel-dompdf
- SMS: Semaphore API (PH-based, endpoint: https://api.semaphore.co/api/v4/messages)
- Task scheduling: Laravel Task Scheduler (artisan schedule)
- File storage: Laravel Storage (local disk, public)

### Database
- Primary: MySQL (all permanent data — readings, cycles, measurements, logs)
- Real-time: Firebase Realtime Database (latest sensor values + current actuator states only — not history)

### Hardware
- Microcontroller: ESP32 WROOM-32U 38-pin (CP2102, Type-C)
- Programming: Arduino IDE, C++
- Communication: HTTP POST to Laravel API every ~10 seconds

## System Architecture

```
ESP32 (sensors + actuators)
        │
        │ HTTP POST /api/sensor-data every ~10 seconds
        ▼
Laravel API (SINGLE entry point — all data flows through here)
        │
        ├── Save to MySQL (permanent storage)
        ├── Push latest sensor values to Firebase (real-time)
        ├── ThresholdService::evaluate() — PURE, no side-effects
        │       └── Returns: { alerts[], commands{humidifier, fan} }
        ├── SmsService::sendAlert() — sends SMS for each alert
        └── Apply actuator commands to Firebase (if no override active)
                └── Skips any relay with override_{actuator} = '1'

ESP32 polls GET /api/actuator-commands every ~10 seconds
        └── Reads current actuator states from Firebase → flips physical relays

Vue.js Dashboard (via Inertia.js + Firebase SDK)
        ├── Live sensor cards ← Firebase real-time listener
        ├── Live actuator states ← Firebase real-time listener
        └── Historical charts ← MySQL via Laravel API
```

## Growing Stages
Each cycle has a `growing_stage` of either `colonization` or `fruiting`. The active cycle's stage determines which threshold set is used.

- **Colonization** — Spawn running. Dark, warm, high CO₂ tolerated.
- **Fruiting** — Mushrooms forming. Needs indirect light, fresh air, high humidity, cooler temps.

New cycles default to `colonization`. Faculty/Admin switch stage via the Cycles page.

## Sensors and GPIO Pins
- DHT22 (temperature + humidity) → GPIO 4
- MQ-135 (CO₂ / air quality) → GPIO 34
- BH1750 (light level, I2C) → SDA: GPIO 21, SCL: GPIO 22
- Capacitive soil moisture → GPIO 35

## Actuators and Relay Channels
- **Relay 1** — Humidifier (ultrasonic mist maker) → GPIO 14
- **Relay 2** — LED grow light strip (5V USB) → GPIO 27
- **Relay 3** — Cooling fan (5V USB) → GPIO 26
- All actuators use separate power rails — ESP32 only sends relay signal
- Relay is active LOW (LOW = ON, HIGH = OFF)

## Threshold Values — Colonization Stage
- Temperature: 24–28°C — alert below 24°C or above 28°C
- Humidity: 70–80% RH — alert below 70%, auto-deactivate humidifier at 80%
- CO₂: below 5,000 ppm — alert above 5,000 ppm
- Light: 0–100 lux (keep dark) — alert if above 100 lux
- Substrate moisture: warning below 55%, critical below 50%

## Threshold Values — Fruiting Stage
- Temperature: 20–24°C — alert below 20°C or above 24°C
- Humidity: 85–95% RH — alert below 85%, auto-deactivate humidifier at 95%
- CO₂: below 1,000 ppm — alert above 1,000 ppm
- Light: 200–800 lux indirect — alert below 200 lux or above 800 lux
- Substrate moisture: warning below 55%, critical below 50%

All thresholds are configurable per-stage in Settings and stored with keys like `threshold_col_temp_min`, `threshold_fruit_humidity_low`.

## Automation Logic (Stage-Aware)

`ThresholdService::evaluate()` is **pure** — reads Firebase for current relay states, returns decisions, performs no writes. All Firebase writes and DB logs happen in `SensorController::store()`.

### Humidifier (Relay 1)
- Humidity **< low threshold** AND humidifier currently OFF → command `on` + SMS alert
- Humidity **≥ high threshold** AND humidifier currently ON → command `off` (no SMS — normal operation)
- If humidity still below threshold AND humidifier already on → **no SMS**. Alert already sent when it turned on.

### Fan (Relay 3) — Unified Resolution with Hysteresis
Fan state resolved once after all sensors evaluated. Hysteresis deadband prevents rapid cycling:

```
FAN_HYSTERESIS = { temp: 1.0°C, co2: 100 ppm }

$tempHigh  = temp > temp_max
$tempClear = temp ≤ (temp_max - 1°C)       ← hysteresis deadband
$co2High   = co2 > co2_max
$co2Clear  = co2 ≤ (co2_max - 100 ppm)    ← hysteresis deadband

$needsFan = ($tempHigh || $co2High) && $fanAllowed
$allClear = $tempClear && $co2Clear

if humidifierWillBeOn && fanCurrentlyOn → command fan: off  (interlock)
elif $needsFan && !fanCurrentlyOn       → command fan: on
elif $allClear && !humidifier && fanCurrentlyOn → command fan: off
else → null (no change)
```

**Fan interlock** — fan blocked when:
- Humidifier is currently on
- Humidifier will be turned on this cycle
- Humidifier was turned off within the last 5 minutes

### SMS Alert Rules
- **Humidifier**: SMS only when humidifier is commanded ON (not while already running and recovering)
- **Fan (temp/CO₂)**: SMS only when `fanAllowed = true`. If fan is blocked by interlock, no SMS — it's non-actionable
- **Humidifier reaching high threshold**: no SMS — it's doing its job correctly
- **Cooldown**: 15-minute per-sensor window in `SmsService`

### LED (Relay 2)
- Controlled by `led:schedule` cron (every minute)
- Admin sets `led_on_hour` / `led_off_hour` in Settings
- Supports overnight ranges (e.g. 22:00 → 06:00)
- Skips write if already in correct state (dedup)

### Manual Override
- Each relay can be locked via Actuator Control page (admin/faculty only)
- Sets `override_{actuator}` = `'1'` in settings table
- SensorController skips automation commands for overridden relays
- LED cron skips if `override_led = '1'`
- Override is persistent (survives restarts), cleared manually
- Manual ON/OFF buttons always work regardless of override

## User Roles
- Admin: full access — user management, settings, thresholds, all logs, actuator override
- Faculty: start/end cycles, switch stage, log measurements, upload photos, control actuators, set override, generate reports, receive SMS
- Student: view-only — dashboard, growth docs, measurements, historical data

## Settings Table Keys
Override keys (set to `'1'` when active):
- `override_humidifier`, `override_fan`, `override_led`

LED schedule:
- `led_on_hour`, `led_off_hour`

Threshold keys — colonization (`col`):
- `threshold_col_temp_min/max`, `threshold_col_humidity_low/high`, `threshold_col_co2_max`
- `threshold_col_light_max`, `threshold_col_soil_warning/critical`

Threshold keys — fruiting (`fruit`):
- `threshold_fruit_temp_min/max`, `threshold_fruit_humidity_low/high`, `threshold_fruit_co2_max`
- `threshold_fruit_light_min/max`, `threshold_fruit_soil_warning/critical`

## Database Tables (MySQL)
- users (id, name, email, password, role, contact_number, timestamps)
- growing_cycles (id, name, mushroom_variety, substrate_type, start_date, end_date, status, growing_stage, notes, timestamps)
- sensor_readings (id, growing_cycle_id, temperature, humidity, co2_raw, light_level, soil_moisture, soil_status, recorded_at, timestamps)
- mushroom_measurements (id, growing_cycle_id, user_id, observed_date, flush_number, weight_g, height_cm, cap_diameter_cm, fruiting_body_count, notes, timestamps)
- actuator_logs (id, actuator, action, trigger, triggered_by, triggered_at, timestamps)
  - `trigger`: `'automatic'` | `'manual'` | `'schedule'`
- alert_logs (id, sensor, value_at_alert, threshold_exceeded, recipient_number, message, status, sent_at, timestamps)
- user_logs (id, user_id, action, details, ip_address, performed_at, timestamps)
- camera_snapshots (id, growing_cycle_id, file_path, file_name, captured_at, timestamps)
- settings (id, key, value, timestamps)

## Firebase Structure (latest values only)
```json
{
  "sensors": {
    "temperature": 0.0,
    "humidity": 0.0,
    "co2_raw": 0,
    "light_level": 0.0,
    "soil_moisture": 0,
    "soil_status": "moist",
    "last_updated": ""
  },
  "actuators": {
    "humidifier": "off",
    "led": "off",
    "fan": "off"
  }
}
```

## Web Routes (authenticated, admin/faculty)
- POST  `api/actuators/toggle`    → manual relay ON/OFF
- POST  `api/actuators/override`  → set/clear manual override per relay
- PUT   `api/actuators/schedule`  → update LED on/off hours

## Laravel API Endpoints (ESP32)
- POST  `/api/sensor-data`        — ESP32 sends readings; triggers threshold evaluation
- GET   `/api/actuator-commands`  — ESP32 polls current relay states from Firebase

## Artisan Commands
- `led:schedule` — runs every minute; enforces LED schedule; skips if `override_led = '1'`

## Pages (Vue.js via Inertia.js)
- /login → LoginView
- /dashboard → DashboardView (live monitoring — current stage badge)
- /historical → HistoricalView (charts + data table)
- /cycles → GrowingCyclesView (stage badge + switch-stage)
- /cycles/{id} → CycleDetailView (detail + report)
- /measurements → MeasurementsView
- /camera → CameraView (growth photo timeline)
- /actuators → ActuatorView (relay control + manual override + log history)
- /alerts → AlertLogsView
- /user-logs → UserLogsView (admin only)
- /settings → SettingsView (admin only — per-stage threshold sections)

## Code Style Rules
- Vue: always use `<script setup lang="ts">` syntax
- Vue: use Composition API only — no Options API
- Laravel: use Eloquent relationships — no raw SQL queries
- Always use Laravel's Http facade for external API calls (Semaphore, Firebase)
- Never expose API keys in frontend code — always use Laravel .env
- Use Laravel's built-in Storage facade for all file operations
- Always return JSON responses from API endpoints
- **`ThresholdService::evaluate()` must remain pure** — no Firebase writes, no DB logs inside it
- All automatic actuator writes go through `SensorController::store()`
- All manual actuator writes go through `ActuatorController::toggle()`
