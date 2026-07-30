import { ref, onValue, type Unsubscribe } from 'firebase/database';
import { defineStore } from 'pinia';
import { ref as vRef, computed } from 'vue';
import { getDb } from '@/lib/firebase';
import type { SensorData, ActuatorData } from '@/types/sensor';

export const useSensorStore = defineStore('sensor', () => {
    // --- State ---
    const sensors = vRef<SensorData>({
        temperature: null,
        humidity: null,
        co2_raw: null,
        light_level: null,
        soil_moisture: null,
        soil_status: null,
        last_updated: null,
    });

    const actuators = vRef<ActuatorData>({
        humidifier: 'off',
        led: 'off',
        fan: 'off',
    });

    const isConnected = vRef<boolean>(false);
    const isLoading = vRef<boolean>(true);

    // Holds Firebase unsubscribe handles so we can clean up on unmount
    let unsubscribeSensors: Unsubscribe | null = null;
    let unsubscribeActuators: Unsubscribe | null = null;

    // Offline detection: if no update arrives within 90 seconds, mark as offline
    let offlineTimer: ReturnType<typeof setTimeout> | null = null;

    function resetOfflineTimer(): void {
        if (offlineTimer) {
            clearTimeout(offlineTimer);
        }
        // ESP32 sends every 10 s; flag offline after 30 s of silence
        offlineTimer = setTimeout(() => {
            isConnected.value = false;
        }, 30_000);
    }

    // --- Getters ---
    const lastUpdatedFormatted = computed(() => {
        if (!sensors.value.last_updated) {
            return 'No data yet';
        }
        return new Date(sensors.value.last_updated).toLocaleString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    });

    const temperatureStatus = computed((): 'normal' | 'warning' | 'critical' => {
        const temp = sensors.value.temperature;
        if (temp === null) { return 'critical'; }
        if (temp < 28 || temp > 32) { return 'warning'; }
        return 'normal';
    });

    const humidityStatus = computed((): 'normal' | 'warning' | 'critical' => {
        const hum = sensors.value.humidity;
        if (hum === null) { return 'critical'; }
        if (hum < 80) { return 'warning'; }
        return 'normal';
    });

    const co2Status = computed((): 'normal' | 'warning' | 'critical' => {
        const co2 = sensors.value.co2_raw;
        if (co2 === null) { return 'critical'; }
        if (co2 > 1000) { return 'warning'; }
        return 'normal';
    });

    // --- Actions ---
    function startListening(): void {
        isLoading.value = true;

        const database = getDb();
        const sensorsRef = ref(database, 'sensors');
        const actuatorsRef = ref(database, 'actuators');

        // onValue returns an Unsubscribe function — store it so stopListening can use it
        unsubscribeSensors = onValue(
            sensorsRef,
            (snapshot) => {
                if (snapshot.exists()) {
                    sensors.value = snapshot.val() as SensorData;
                    isConnected.value = true;
                    resetOfflineTimer();
                }
                isLoading.value = false;
            },
            (error) => {
                console.error('[SensorStore] Firebase sensors error:', error);
                isConnected.value = false;
                isLoading.value = false;
            },
        );

        unsubscribeActuators = onValue(
            actuatorsRef,
            (snapshot) => {
                if (snapshot.exists()) {
                    actuators.value = snapshot.val() as ActuatorData;
                }
            },
            (error) => {
                console.error('[SensorStore] Firebase actuators error:', error);
            },
        );
    }

    function stopListening(): void {
        // Properly detach Firebase listeners
        if (unsubscribeSensors) {
            unsubscribeSensors();
            unsubscribeSensors = null;
        }
        if (unsubscribeActuators) {
            unsubscribeActuators();
            unsubscribeActuators = null;
        }
        if (offlineTimer) {
            clearTimeout(offlineTimer);
            offlineTimer = null;
        }
        isConnected.value = false;
    }

    return {
        // State
        sensors,
        actuators,
        isConnected,
        isLoading,
        // Getters
        lastUpdatedFormatted,
        temperatureStatus,
        humidityStatus,
        co2Status,
        // Actions
        startListening,
        stopListening,
    };
});
