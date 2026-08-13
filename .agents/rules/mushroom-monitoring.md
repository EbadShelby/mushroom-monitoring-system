

- IoT system monitoring oyster mushroom cultivation at CotSU. ESP32 sends sensor data to Laravel, stored in MySQL and Firebase. Vue.js dashboard shows live readings, automates actuators (with manual override), sends SMS alerts, and generates cycle reports.

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
- Real-time: Firebase Realtime Database (latest sensor values + current actuator commands only — not history)

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
        ├── ThresholdService::evaluate() — pure, no side-effects
        │       └── Returns: { alerts[], commands{humidifier, fan} }
        ├── SmsService::sendAlert() — sends SMS for each alert
        └── Apply actuator commands to Firebase (if no override active)

ESP32 polls GET /api/actuator-commands every ~10 seconds
        └── Reads current actuator states from Firebase → flips physical relays

Vue.js Dashboard (via Inertia.js + Firebase SDK)
        ├── Live sensor cards ← Firebase real-time listener
        ├── Live actuator states ← Firebase real-time listener
        └── Historical charts ← MySQL via Laravel API
```

## Growing Stages
Each cycle has a `growing_stage` of either `colonization` or `fruiting`. The active cycle's stage determines which threshold set is used for automation and alerts.

- **Colonization** — Spawn running. Mycelium spreads through substrate. Dark, warm, high CO₂ tolerated.
- **Fruiting** — Mushrooms forming. Needs indirect light, fresh air, high humidity, cooler temps.

New cycles default to `colonization`. Faculty/Admin can switch the stage via the Cycles page.

## Sensors and GPIO Pins
- DHT22 (temperature + humidity) → GPIO 4
- MQ-135 (CO₂ / air quality) → GPIO 34
- BH1750 (light level, I2C) → SDA: GPIO 21, SCL: GPIO 22
- Capacitive soil moisture → GPIO 35

## Actuators and Relay Channels
- Humidifier (ultrasonic mist maker) → **Relay 1** → GPIO 14
- LED grow light strip (5V USB) → **Relay 2** → GPIO 27
- Cooling fan (5V USB) → **Relay 3** → GPIO 26
- All actuators use separate power rails — ESP32 only sends relay signal
- Relay is active LOW (LOW = ON, HIGH = OFF)

## Threshold Values — Colonization Stage
- Temperature: 24–28°C (ideal 25–27°C) — alert below 24°C or above 28°C
- Humidity: 70–80% RH — alert below 70%, auto-deactivate humidifier at 80%
- CO₂: below 5,000 ppm (high CO₂ acceptable during colonization) — alert above 5,000 ppm
- Light: 0–100 lux (keep dark/very dim) — alert if above 100 lux
- Substrate moisture: warning below 55%, critical below 50%

## Threshold Values — Fruiting Stage
- Temperature: 20–24°C (ideal 22–24°C) — alert below 20°C or above 24°C
- Humidity: 85–95% RH — alert below 85%, auto-deactivate humidifier at 95%
- CO₂: below 1,000 ppm (fresh air essential for fruiting) — alert above 1,000 ppm
- Light: 200–800 lux indirect — alert below 200 lux or above 800 lux
- Substrate moisture: warning below 55%, critical below 50%

All thresholds are configurable per-stage in the Settings page and stored in the `settings` table with keys like `threshold_col_temp_min`, `threshold_fruit_humidity_low`, etc.

## Automation Logic (Stage-Aware)

`ThresholdService::evaluate()` is the heart of the automation. It is **pure** — it reads Firebase (to determine current relay states) and returns a result; it does **not** write to Firebase or the database. All writes are handled by `SensorController::store()`.

### Return shape
```php
[
  'alerts'   => [...],                       // list of alert arrays for SMS dispatch
  'commands' => [
    'humidifier' => 'on'|'off'|null,         // null = no change
    'fan'        => 'on'|'off'|null,
  ],
]
```

### Humidifier (Relay 1)
- Humidity **< low threshold** AND humidifier currently OFF → command `humidifier: on` + SMS alert
- Humidity **≥ high threshold** AND humidifier currently ON → command `humidifier: off`
- Redundant writes are suppressed: ON is only commanded if the humidifier isn't already on

### Fan (Relay 3)
Fan state is resolved in a **single unified block** after all sensors are evaluated — not per-sensor:

```
$needsFan = ($tempHigh || $co2High) && $fanAllowed

if humidifierWillBeOn && fanCurrentlyOn  → command fan: off  (interlock)
elif $needsFan && !fanCurrentlyOn        → command fan: on
elif !$needsFan && !humidifierWillBeOn && fanCurrentlyOn → command fan: off
else → null (no change)
```

**Fan interlock** — fan is blocked (`$fanAllowed = false`) when:
- Humidifier is currently on, OR
- Humidifier will be turned on this evaluation cycle, OR
- Humidifier was turned off within the last 5 minutes (DB query on `actuator_logs`)

This prevents the fan from blowing mist away before humidity builds up.

### LED (Relay 2)
- Controlled exclusively by the `led:schedule` Artisan cron (runs every minute)
- Admin sets `led_on_hour` and `led_off_hour` in Settings; cron reads these from DB
- Supports overnight ranges (e.g. 22:00 → 06:00)
- Only writes Firebase / logs if the state actually needs to change (dedup check)

### Manual Override
Each relay can be locked to manual control via the Actuator Control page (admin/faculty only):
- Sets `override_{actuator}` = `'1'` in the `settings` table
- `SensorController` skips any automatic command for that relay while override is active
- `EnforceLedSchedule` cron skips LED writes while `override_led = '1'`
- Override is **persistent** (survives restarts) and must be cleared manually
- Manual ON/OFF buttons always work regardless of override state — override only blocks automation

## User Roles
- Admin: full access — user management, settings, thresholds, all logs, actuator override
- Faculty: start/end cycles, switch cycle stage, log measurements, upload growth photos, control actuators, set override, generate reports, receive SMS
- Student: view dashboard, view growth docs, view measurements (read-only — cannot log or delete measurements), analyze historical data

## Settings Table Keys
Threshold keys use prefix `threshold_col_*` for colonization and `threshold_fruit_*` for fruiting:
- `threshold_col_temp_min`, `threshold_col_temp_max`
- `threshold_col_humidity_low`, `threshold_col_humidity_high`
- `threshold_col_co2_max`, `threshold_col_light_max`
- `threshold_col_soil_warning`, `threshold_col_soil_critical`
- `threshold_fruit_temp_min`, `threshold_fruit_temp_max`
- `threshold_fruit_humidity_low`, `threshold_fruit_humidity_high`
- `threshold_fruit_co2_max`, `threshold_fruit_light_min`, `threshold_fruit_light_max`
- `threshold_fruit_soil_warning`, `threshold_fruit_soil_critical`

LED schedule:
- `led_on_hour`, `led_off_hour` (integer hours 0–23)

Manual override (set to `'1'` when active, `'0'` when cleared):
- `override_humidifier`, `override_fan`, `override_led`

## Database Tables (MySQL)
- users (id, name, email, password, role, contact_number, timestamps)
- growing_cycles (id, name, mushroom_variety, substrate_type, start_date, end_date, status, growing_stage, notes, timestamps)
  - `status`: active | completed | cancelled
  - `growing_stage`: colonization | fruiting (default: colonization)
- sensor_readings (id, growing_cycle_id, temperature, humidity, co2_raw, light_level, soil_moisture, soil_status, recorded_at, timestamps)
- mushroom_measurements (id, growing_cycle_id, user_id, observed_date, flush_number, weight_g, height_cm, cap_diameter_cm, fruiting_body_count, notes, timestamps)
- actuator_logs (id, actuator, action, trigger, triggered_by, triggered_at, timestamps)
  - `trigger`: `'automatic'` | `'manual'` | `'schedule'`
  - `triggered_by`: user name string or `'system'`
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

## Web Routes (authenticated)
- POST  `api/actuators/toggle`    → manual relay ON/OFF (admin/faculty)
- POST  `api/actuators/override`  → set/clear manual override per relay (admin/faculty)
- PUT   `api/actuators/schedule`  → update LED on/off hours (admin/faculty)

## Laravel API Endpoints (ESP32)
- POST  `/api/sensor-data`        (ESP32 sends readings — triggers threshold evaluation)
- GET   `/api/actuator-commands`  (ESP32 polls current relay states from Firebase)

## Artisan Commands
- `led:schedule` — runs every minute via scheduler; enforces LED on/off hours; skips if `override_led = '1'`

## Pages (Vue.js via Inertia.js)
- /login → LoginView
- /dashboard → DashboardView (live monitoring — shows current stage badge)
- /historical → HistoricalView (charts + data table)
- /cycles → GrowingCyclesView (list — stage badge + switch-stage button)
- /cycles/{id} → CycleDetailView (detail + report)
- /measurements → MeasurementsView
- /camera → CameraView (growth photo timeline)
- /actuators → ActuatorView (control + logs + manual override per relay)
- /alerts → AlertLogsView
- /user-logs → UserLogsView (admin only)
- /settings → SettingsView (admin only — per-stage threshold sections)

## Code Style Rules
- Vue: always use `<script setup lang="ts">` syntax
- Vue: use Composition API only — no Options API
- Laravel: use Eloquent relationships — no raw SQL queries
- Always use Laravel's Http facade for external API calls (Semaphore, Firebase)
- Never expose API keys in frontend code — always use Laravel .env
- All monetary or sensitive config values go in .env
- Use Laravel's built-in Storage facade for all file operations
- Always return JSON responses from API endpoints
- `ThresholdService::evaluate()` must remain pure — no Firebase writes, no DB logs inside it
- All actuator Firebase writes and actuator_logs entries must go through `SensorController::store()` for automatic commands, or `ActuatorController::toggle()` for manual commands