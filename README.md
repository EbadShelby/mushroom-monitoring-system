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

## Threshold Values & Automation Logic (Gray Oyster Mushroom)

- **Temperature**: 28–32°C (Alert below 28 or above 32).
- **Humidity**: 80–95% (Alert below 80).
    - _Logic_: `< 80%` → Auto-activate humidifier & send SMS alert. `>= 90%` → Auto-deactivate humidifier.
- **CO2**: Below 1000 ppm during fruiting (Alert above 1000).
    - _Logic_: `> 1000 ppm` → Auto-activate cooling fan & send SMS alert. `< 1000 ppm` and temp `< 30°C` → Auto-deactivate cooling fan.
- **Light**: 50–1000 lux during fruiting, dark during colonization.
    - _Logic_: LED grow light controlled by a schedule (12hr ON / 12hr OFF) set by the admin.
- **Soil Moisture**:
    - _Warning_: `< 30%` → Warning SMS only (No actuator).
    - _Critical_: `< 20%` → Urgent SMS only (No actuator).

## User Roles

- **Admin**: Full access — User management, settings, thresholds, and all logs.
- **Faculty**: Start/end cycles, log measurements, upload growth photos, control actuators, generate reports, and receive SMS alerts.
- **Student**: View dashboard, view growth documentation, log measurements, and analyze historical data.

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

7. **Start Development Servers**
    ```bash
    php artisan serve
    npm run dev
    ```
