# IoT-Based Environmental Monitoring System for Oyster Mushroom Cultivation

An IoT system designed for monitoring and automating the cultivation environment of Gray Oyster Mushrooms (_Pleurotus sajor-caju_). This system was developed for Cotabato State University — BS Agriculture Program.

The system uses an ESP32 microcontroller to collect environmental data from various sensors (temperature, humidity, CO2, light, and soil moisture) and automatically controls actuators (cooling fan, humidifier, grow light) based on specific thresholds. The data is sent to a Laravel backend, where it is stored in MySQL for historical tracking and pushed to a Firebase Realtime Database for live dashboard monitoring built with Vue.js.

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

## Tech Stack

### Frontend

- **Framework**: Vue.js 3 (Composition API, `<script setup>`)
- **Routing**: Inertia.js v3
- **Styling**: Tailwind CSS v4
- **UI Components**: Reka UI + Lucide Icons (`@lucide/vue`)
- **Charts**: ApexCharts (`vue3-apexcharts`)
- **State Management**: Pinia
- **HTTP Client**: Axios
- **Notifications**: Vue Sonner
- **Real-time**: Firebase Realtime Database JS SDK
- **Language**: TypeScript

### Backend

- **Framework**: Laravel 13
- **Language**: PHP 8.5
- **Authentication**: Laravel Fortify
- **PDF Generation**: `barryvdh/laravel-dompdf`
- **SMS API**: Semaphore API (PH-based)
- **Task Scheduling**: Laravel Task Scheduler (`artisan schedule`)
- **File Storage**: Laravel Storage (Local disk, public)

### Database

- **Primary**: MySQL (Permanent data — readings, cycles, measurements, logs)
- **Real-time**: Firebase Realtime Database (Latest sensor values)

### Hardware

- **Microcontroller**: ESP32 WROOM-32U 38-pin (CP2102, Type-C)
- **Programming**: Arduino IDE / PlatformIO VSCODE extension (C++)
- **Communication**: HTTP POST to Laravel API (Every 30 seconds)

## Sensors & GPIO Pins

- **DHT22** (Temperature + Humidity) → GPIO 4
- **MQ-135** (CO2 / Air Quality) → GPIO 34
- **BH1750** (Light Level, I2C) → SDA: GPIO 21, SCL: GPIO 22
- **Capacitive Soil Moisture** → GPIO 35

## Actuators & Relay Channels

_All actuators use separate power rails — ESP32 only sends a relay signal. Relay is active LOW (LOW = ON, HIGH = OFF)._

- **Cooling Fan** (5V USB) → Relay N4 → GPIO 26
- **Humidifier** (Ultrasonic Mist Maker) → Relay N3 → GPIO 14
- **LED Grow Light Strip** (5V USB) → Relay N2 → GPIO 27

## Threshold Values & Stage-Based Automation Logic (Gray Oyster Mushroom)

The system automatically switches environmental target profiles based on the active cycle's **Growth Stage**:

### 1. Colonization Stage (Spawn Running Phase)

_Mycelium spreads throughout the substrate bag — requires warmth, darkness, and high CO₂ tolerance._

| Environmental Factor      | Target Range                 | Sensor     | System Automation & Alert Logic                                                   |
| :------------------------ | :--------------------------- | :--------- | :-------------------------------------------------------------------------------- |
| 🌡️ **Temperature**        | **24–28°C** (Ideal: 25–27°C) | DHT22      | `< 24°C` → Low temp alert. `> 28°C` → Auto-activate intake fan & alert.           |
| 💧 **Humidity**           | **70–80% RH**                | DHT22      | `< 70%` → Auto-activate humidifier & SMS alert. `>= 80%` → Deactivate humidifier. |
| 🌬️ **CO₂ Level**          | **2,000–5,000 ppm**          | MQ-135     | `> 5,000 ppm` → Auto-activate intake fan for fresh air & SMS alert.               |
| 💡 **Light Level**        | **0–50 lux** (Dark/Dim)      | BH1750     | `> 100 lux` → Alert (spawn running requires darkness). Grow lights OFF.           |
| 🌱 **Substrate Moisture** | **60–65%**                   | Capacitive | `< 55%` → Warning SMS alert. `< 50%` → Critical SMS alert.                        |

---

### 2. Fruiting Stage (Mushroom Formation & Growth Phase)

_Bags opened — requires cooler temps, high humidity, fresh air, and indirect light._

| Environmental Factor      | Target Range                 | Sensor     | System Automation & Alert Logic                                                   |
| :------------------------ | :--------------------------- | :--------- | :-------------------------------------------------------------------------------- |
| 🌡️ **Temperature**        | **20–24°C** (Ideal: 22–24°C) | DHT22      | `< 20°C` → Low temp alert. `> 24°C` → Auto-activate intake fan & alert.           |
| 💧 **Humidity**           | **85–95% RH**                | DHT22      | `< 85%` → Auto-activate humidifier & SMS alert. `>= 95%` → Deactivate humidifier. |
| 🌬️ **CO₂ Level**          | **600–1,000 ppm**            | MQ-135     | `> 1,000 ppm` → Auto-activate intake fan for fresh air & SMS alert.               |
| 💡 **Light Level**        | **200–800 lux** (Indirect)   | BH1750     | `< 200 lux` → Alert too dark. `> 800 lux` → Alert too bright. LED on schedule.    |
| 🌱 **Substrate Moisture** | **55–65%**                   | Capacitive | `< 55%` → Warning SMS alert. `< 50%` → Critical SMS alert.                        |

---

### ⚡ Actuator Interlock, Safety & Override Logic

- **Humidifier Interlock**: When the **Humidifier** is active (or recently turned off), the **Intake Fan** is automatically overridden to **OFF** to prevent mist from being blown out of the chamber before humidity builds.
- **Hysteresis Deadband**: To prevent the fan from rapidly cycling on and off at threshold boundaries, it only turns off when the temperature drops 1°C below the maximum, and CO₂ drops 100 ppm below the maximum.
- **Manual Override**: Faculty/Admin can manually lock any relay via the Actuator Control page. When a relay is locked, automation and schedules are paused for that specific relay.
- **Smart SMS Alerts**: Non-actionable alerts are suppressed (e.g. if the fan is blocked by the humidifier, it won't spam SMS). Humidifier alerts only trigger on activation, not while recovering.
- **Automatic Stage Switch**: Users can switch growth stages on the dashboard or cycles page, instantly updating live gauges, target indicators, Pinia status evaluators, and backend automation logic.

## User Roles

- **Admin**: Full access — User management, settings, thresholds, and all logs.
- **Faculty**: Start/end cycles, log measurements, upload growth photos, control actuators, generate reports, and receive SMS alerts.
- **Student**: View dashboard, view growth documentation, view measurements (read-only — cannot log or delete), and analyze historical data.

## System Requirements

### Software Requirements

| Requirement | Minimum Version | Notes |
| :----------------------------------- | :-------------- | :--------------------------------------------------------------- |
| **PHP** | 8.2+ | PHP 8.5 recommended. Extensions: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `openssl` |
| **Composer** | 2.x | PHP dependency manager |
| **Node.js** | 18.x LTS+ | Node 20 LTS recommended |
| **npm** | 9.x+ | Bundled with Node.js |
| **MySQL** | 8.0+ | MariaDB 10.6+ also compatible |
| **Apache / Nginx** | Any recent | Or use `php artisan serve` for local development |
| **Git** | 2.x+ | For cloning the repository |
| **Arduino IDE** | 2.x | Or PlatformIO (VS Code extension) for ESP32 firmware flashing |

> **Windows users**: [XAMPP](https://www.apachefriends.org/) (PHP 8.2+, MySQL 8) or [Laragon](https://laragon.org/) satisfies the Apache + MySQL + PHP stack in a single installer.

> **Linux users**: Use your distribution's package manager (e.g., `apt`, `dnf`) or [Laravel Herd](https://herd.laravel.com/linux) for a managed PHP environment.

---

### Host Machine Hardware Requirements

| Component | Minimum | Recommended |
| :---------- | :------------- | :------------- |
| **CPU** | Dual-core 1.5 GHz | Quad-core 2.0 GHz+ |
| **RAM** | 4 GB | 8 GB |
| **Storage** | 5 GB free | 20 GB free (for growth photo uploads over multiple cycles) |
| **OS** | Windows 10, Ubuntu 20.04, macOS 12 | Windows 11, Ubuntu 22.04/24.04, macOS 14 |
| **Wi-Fi** | 802.11n (2.4 GHz) | 802.11ac or better |

---

### Network Requirements

- The host machine and the **ESP32 must be on the same local Wi-Fi network** (same subnet / router) for the microcontroller's HTTP POST calls to reach the Laravel server.
- Laravel must be served with `--host=0.0.0.0` so it binds to the LAN interface, not just `localhost`.
- Outbound internet access is required for:
  - **Firebase Realtime Database** — real-time sensor value sync
  - **Semaphore SMS API** — push alerts to registered contacts
  - **Composer / npm** — pulling dependencies on first install

---

### Firebase Requirements

- A **Firebase project** with **Realtime Database** enabled (Spark plan is sufficient).
- A **service account** JSON key for server-side writes (Laravel backend).
- A **web app** registered in the Firebase project for client-side SDK config (Vue.js dashboard).
- Realtime Database rules must allow authenticated reads; unauthenticated writes are locked down — the Laravel service account bypasses rules via Admin SDK.

---

### ESP32 Firmware Dependencies (Arduino / PlatformIO)

| Library | Purpose |
| :------------------------------ | :--------------------------------- |
| `DHT sensor library` (Adafruit) | DHT22 temperature + humidity |
| `Adafruit Unified Sensor` | Required by DHT library |
| `BH1750` (Christopher Laws) | Light level sensor (I2C) |
| `ArduinoJson` | Serialize sensor payload to JSON |
| `WiFi.h` | Built-in ESP32 Wi-Fi stack |
| `HTTPClient.h` | Built-in ESP32 HTTP client |

> All libraries are available through the Arduino Library Manager or `platformio.ini` `lib_deps`.

---

## Setup & Installation (Local Development)

1. **Clone the repository**

    ```bash
    git clone https://github.com/your-org/mushroom-monitoring-system.git
    cd mushroom-monitoring-system
    ```

2. **Install PHP dependencies**

    ```bash
    composer install
    ```

3. **Install Node dependencies**

    ```bash
    npm install
    ```

4. **Environment Configuration**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    _Update your `.env` file with the correct MySQL credentials, Firebase credentials, and Semaphore API key._

5. **Run Database Migrations & Seeders**

    ```bash
    php artisan migrate --seed
    ```

6. **Link Storage**

    ```bash
    php artisan storage:link
    ```

7. **Start Development Servers & System Services**

    To run the complete system with ESP32 hardware and automated schedules, keep **3 separate terminals** open:

    - **Terminal 1 — Laravel Server (LAN Listener)**

        ```bash
        php artisan serve --host=0.0.0.0 --port=8080
        ```

        _Note: `--host=0.0.0.0` is required so the ESP32 on your Wi-Fi network can POST data._

    - **Terminal 2 — Composer compiler**

        ```bash
        composer run dev
        ```

    - **Terminal 3 — Task Scheduler**
        ```bash
        php artisan schedule:work
        ```
        _Note: Enforces actuator schedules (e.g., LED lights) every minute._

---

## 🍄 Operational Guide: Cold Start to Live Data

Follow these steps when powering up the physical mushroom monitoring setup:

1. **Start System Server & Database (Apache & MySQL)**
    - **Windows**: Open **XAMPP Control Panel** (or Laragon) and click **Start** for Apache and MySQL.
    - **Linux**: Run `sudo systemctl start httpd mysqld php-fpm`.

2. **Verify Host IP Address**
    - **Linux / macOS**:
        ```bash
        hostname -I
        ```
    - **Windows (Command Prompt / PowerShell)**:
        ```cmd
        ipconfig
        ```
        _(Find the `IPv4 Address` for your connected Wi-Fi network)._

    _If your host IP is not `192.168.254.138` (or the IP configured in your C++ code), update the endpoint URL in the ESP32 Arduino sketch and re-upload to the microcontroller._

3. **Start the 3 Required Terminals**
    - **Terminal 1 — Laravel API Server**: `php artisan serve --host=0.0.0.0 --port=8080`
    - **Terminal 2 — Composer Compiler**: `composer run dev`
    - **Terminal 3 — Task Scheduler**: `php artisan schedule:work`

4. **Access the Dashboard**
    - Open browser: `http://localhost:8080` (or `http://192.168.254.138:8080`)
    - Log in with your credentials.

5. **Power On ESP32**
    - Plug in the ESP32 board.
    - ESP32 connects to Wi-Fi (`GlobeAtHome_50B8D`).
    - MQ-135 sensor warms up for ~30 seconds.
    - Microcontroller begins posting readings every 10–30 seconds.
    - _(Optional)_ Monitor live status via Arduino IDE/PlatformIO Serial Monitor at **115200 baud**.

6. **Live Dashboard Verified ✅**
    - After ~35 seconds, the dashboard at `/dashboard` displays live temperature, humidity, CO₂, light, and soil moisture metrics updated via Firebase.
