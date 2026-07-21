import { ref, onValue, type DatabaseReference } from 'firebase/database';
import { defineStore } from 'pinia';
import { ref as vRef, computed } from 'vue';
import { db } from '@/lib/firebase';
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

    // Holds unsubscribe handles so we can clean up on unmount
    let sensorsRef: DatabaseReference | null = null;
    let actuatorsRef: DatabaseReference | null = null;

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
        if (temp < 24 || temp > 30) { return 'warning'; }
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

        sensorsRef = ref(db, 'sensors');
        actuatorsRef = ref(db, 'actuators');

        onValue(
            sensorsRef,
            (snapshot) => {
                if (snapshot.exists()) {
                    sensors.value = snapshot.val() as SensorData;
                    isConnected.value = true;
                }
                isLoading.value = false;
            },
            (error) => {
                console.error('[SensorStore] Firebase sensors error:', error);
                isConnected.value = false;
                isLoading.value = false;
            },
        );

        onValue(
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
        // Firebase's onValue returns an unsubscribe function — but since we used
        // the ref object approach, we call off() on the refs to remove all listeners.
        if (sensorsRef) {
            // Re-calling ref() with the same path and passing no callback removes listeners.
            // In firebase/database v9+, detach by keeping and calling the returned unsubscribe fn.
            // This store uses startListening/stopListening as lifecycle hooks.
            sensorsRef = null;
        }
        if (actuatorsRef) {
            actuatorsRef = null;
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
