---
trigger: always_on
---

- IoT system monitoring oyster mushroom cultivation at CotSU. ESP32 sends sensor data to Laravel, stored in MySQL and Firebase. Vue.js dashboard shows live readings, automates actuators, sends SMS alerts, and generates cycle reports.

# IoT Mushroom Monitoring System — Project Rules

## Project Identity
- Project name: IoT-Based Environmental Monitoring System for Oyster Mushroom Cultivation
- Institution: Cotabato State University — BS Agriculture Program
- Mushroom variety: Gray Oyster Mushroom (Pleurotus sajor-caju)
- Growing environment: Indoor enclosed box (PVC frame, plywood/glass walls)

## Tech Stack — Never Deviate From This

### Frontend
- Framework: Vue.js 3 (Composition API, <script setup> syntax only)
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
- Real-time: Firebase Realtime Database (latest sensor values only — not history)

### Hardware
- Microcontroller: ESP32 WROOM-32U 38-pin (CP2102, Type-C)
- Programming: Arduino IDE, C++
- Communication: HTTP POST to Laravel API every 30 seconds

## System Architecture

```
ESP32 (sensors + actuators)
        │
        │ HTTP POST /api/sensor-data every 30 seconds
        ▼
Laravel API (SINGLE entry point — all data flows through here)
        │
        ├── Save to MySQL (permanent storage)
        └── Push latest values to Firebase (real-time dashboard)
                │
                ▼
        Vue.js Dashboard (via Inertia.js)
                │
                ├── Live sensor cards ← Firebase
                └── Historical charts ← MySQL via Laravel API
```

## Sensors and GPIO Pins
- DHT22 (temperature + humidity) → GPIO 4
- MQ-135 (CO2 / air quality) → GPIO 34
- BH1750 (light level, I2C) → SDA: GPIO 21, SCL: GPIO 22
- Capacitive soil moisture → GPIO 35

## Actuators and Relay Channels
- Cooling fan (5V USB) → Relay N4 → GPIO 26
- Humidifier (ultrasonic mist maker) → Relay N3 → GPIO 14
- LED grow light strip (5V USB) → Relay N2 → GPIO 27
- All actuators use separate power rails — ESP32 only sends relay signal
- Relay is active LOW (LOW = ON, HIGH = OFF)

## Threshold Values (Gray Oyster Mushroom — Pleurotus sajor-caju)
- Temperature: 28–32°C (alert below 28 or above 32)
- Humidity: 80–95% (alert below 80)
- CO2: below 1000 ppm during fruiting (alert above 1000)
- Light: 50–1000 lux during fruiting, dark during colonization
- Soil moisture dry: below 30% (warning SMS)
- Soil moisture critical: below 20% (auto-trigger pump + urgent SMS)

## Automation Logic
- Humidity < 80% → auto-activate humidifier via relay + send SMS alert
- Humidity >= 90% → auto-deactivate humidifier
- Soil moisture < 30% → warning SMS only (no actuator)
- Soil moisture < 20% (critical) → urgent SMS only (no actuator)
- CO2 > 1000 ppm → auto-activate cooling fan via relay + send SMS
- LED grow light → controlled by schedule (12hr on / 12hr off) set by admin
- All actuator activations logged to actuator_logs table

## User Roles
- Admin: full access — user management, settings, thresholds, all logs
- Faculty: start/end cycles, log measurements, upload growth photos, control actuators, generate reports, receive SMS
- Student: view dashboard, view growth docs, log measurements, analyze historical data

## Database Tables (MySQL)
- users (id, name, email, password, role, contact_number, timestamps)
- growing_cycles (id, name, mushroom_variety, substrate_type, start_date, end_date, status, notes, timestamps)
- sensor_readings (id, growing_cycle_id, temperature, humidity, co2_raw, light_level, soil_moisture, soil_status, recorded_at, timestamps)
- mushroom_measurements (id, growing_cycle_id, user_id, observed_date, flush_number, height_cm, cap_diameter_cm, fruiting_body_count, photo_path, notes, timestamps)
- actuator_logs (id, actuator, action, trigger, triggered_by, triggered_at, timestamps)
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
    "pump": "off",
    "led": "off",
    "fan": "off"
  }
}
```

## Laravel API Endpoints
- POST   /api/sensor-data           (ESP32 sends readings)
- GET    /api/actuator-commands      (ESP32 polls for pending commands)
- POST   /api/camera/snapshot        (stores CCTV snapshot)
- GET    /api/sensor-readings        (historical data for charts)
- GET    /api/growing-cycles         (cycle list)
- POST   /api/growing-cycles         (create cycle)
- GET    /api/growing-cycles/{id}    (cycle detail)
- PUT    /api/growing-cycles/{id}    (update cycle)
- POST   /api/measurements           (add measurement)
- GET    /api/measurements           (measurement list)
- POST   /api/actuators/command      (manual actuator toggle from dashboard)
- GET    /api/alert-logs             (SMS alert history)
- GET    /api/user-logs              (user activity — admin only)
- GET    /api/settings               (system settings)
- POST   /api/settings               (update settings)
- GET    /api/reports/{cycleId}      (generate and download PDF report)

## Mushroom Growth Documentation
- Camera: CCTV (not ESP32-CAM)
- Upload method: Manual — faculty uploads photos daily via dashboard
- Upload frequency: Once per day (daily documentation)
- Each upload linked to: active growing cycle + current date + flush number
- Stored in: Laravel storage/app/public/snapshots/
- Displayed as: chronological photo timeline per growing cycle
- No automatic snapshot capture — all manual uploads by faculty

## Pages (Vue.js via Inertia.js)
- /login → LoginView
- /dashboard → DashboardView (live monitoring)
- /historical → HistoricalView (charts + data table)
- /cycles → GrowingCyclesView (list)
- /cycles/{id} → CycleDetailView (detail + report)
- /measurements → MeasurementsView
- /camera → CameraView (growth photo timeline)
- /actuators → ActuatorView (control + logs)
- /alerts → AlertLogsView
- /user-logs → UserLogsView (admin only)
- /settings → SettingsView (admin only)

## Code Style Rules
- Vue: always use <script setup lang="ts"> syntax
- Vue: use Composition API only — no Options API
- Laravel: use Eloquent relationships — no raw SQL queries
- Always use Laravel's Http facade for external API calls (Semaphore, Firebase)
- Never expose API keys in frontend code — always use Laravel .env
- All monetary or sensitive config values go in .env
- Use Laravel's built-in Storage facade for all file operations
- Always return JSON responses from API endpoints