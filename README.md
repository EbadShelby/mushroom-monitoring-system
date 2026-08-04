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
*Mycelium spreads throughout the substrate bag — requires warmth, darkness, and high CO₂ tolerance.*

| Environmental Factor | Target Range | Sensor | System Automation & Alert Logic |
| :--- | :--- | :--- | :--- |
| 🌡️ **Temperature** | **24–28°C** (Ideal: 25–27°C) | DHT22 | `< 24°C` → Low temp alert. `> 28°C` → Auto-activate intake fan & alert. |
| 💧 **Humidity** | **70–80% RH** | DHT22 | `< 70%` → Auto-activate humidifier & SMS alert. `>= 80%` → Deactivate humidifier. |
| 🌬️ **CO₂ Level** | **2,000–5,000 ppm** | MQ-135 | `> 5,000 ppm` → Auto-activate intake fan for fresh air & SMS alert. |
| 💡 **Light Level** | **0–50 lux** (Dark/Dim) | BH1750 | `> 100 lux` → Alert (spawn running requires darkness). Grow lights OFF. |
| 🌱 **Substrate Moisture** | **60–65%** | Capacitive | `< 55%` → Warning SMS alert. `< 50%` → Critical SMS alert. |

---

### 2. Fruiting Stage (Mushroom Formation & Growth Phase)
*Bags opened — requires cooler temps, high humidity, fresh air, and indirect light.*

| Environmental Factor | Target Range | Sensor | System Automation & Alert Logic |
| :--- | :--- | :--- | :--- |
| 🌡️ **Temperature** | **20–24°C** (Ideal: 22–24°C) | DHT22 | `< 20°C` → Low temp alert. `> 24°C` → Auto-activate intake fan & alert. |
| 💧 **Humidity** | **85–95% RH** | DHT22 | `< 85%` → Auto-activate humidifier & SMS alert. `>= 95%` → Deactivate humidifier. |
| 🌬️ **CO₂ Level** | **600–1,000 ppm** | MQ-135 | `> 1,000 ppm` → Auto-activate intake fan for fresh air & SMS alert. |
| 💡 **Light Level** | **200–800 lux** (Indirect) | BH1750 | `< 200 lux` → Alert too dark. `> 800 lux` → Alert too bright. LED on schedule. |
| 🌱 **Substrate Moisture** | **55–65%** | Capacitive | `< 55%` → Warning SMS alert. `< 50%` → Critical SMS alert. |

---

### ⚡ Actuator Interlock & Safety Logic
- **Humidifier Interlock**: When the **Humidifier** is active, the **Intake Fan** is automatically overridden to **OFF** to prevent mist from being blown out of the chamber.
- **Automatic Stage Switch**: Users can switch growth stages on the dashboard or cycles page, instantly updating live gauges, target indicators, Pinia status evaluators, and backend automation logic.

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
