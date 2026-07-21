<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { dashboard } from '@/routes';
import { useSensorStore } from '@/stores/useSensorStore';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const store = useSensorStore();

onMounted(() => store.startListening());
onUnmounted(() => store.stopListening());
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <!-- Connection Status Banner -->
        <div
            class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium"
            :class="
                store.isLoading
                    ? 'border-yellow-500/30 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400'
                    : store.isConnected
                      ? 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400'
                      : 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'
            "
        >
            <span
                class="size-2 rounded-full"
                :class="
                    store.isLoading
                        ? 'animate-pulse bg-yellow-500'
                        : store.isConnected
                          ? 'bg-green-500'
                          : 'bg-red-500'
                "
            />
            <span v-if="store.isLoading">Connecting to Firebase…</span>
            <span v-else-if="store.isConnected">
                Firebase connected — Last updated: {{ store.lastUpdatedFormatted }}
            </span>
            <span v-else>Firebase disconnected — check your credentials</span>
        </div>

        <!-- Sensor Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Temperature -->
            <div
                class="rounded-xl border p-5"
                :class="
                    store.temperatureStatus === 'warning'
                        ? 'border-orange-500/30 bg-orange-500/5'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Temperature</p>
                <p class="mt-2 text-4xl font-bold tabular-nums">
                    <span v-if="store.sensors.temperature !== null">{{ store.sensors.temperature }}°C</span>
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">Optimal: 24 – 30°C</p>
                <p
                    v-if="store.temperatureStatus === 'warning'"
                    class="mt-2 text-xs font-medium text-orange-500"
                >
                    ⚠ Out of range
                </p>
            </div>

            <!-- Humidity -->
            <div
                class="rounded-xl border p-5"
                :class="
                    store.humidityStatus === 'warning'
                        ? 'border-orange-500/30 bg-orange-500/5'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Humidity</p>
                <p class="mt-2 text-4xl font-bold tabular-nums">
                    <span v-if="store.sensors.humidity !== null">{{ store.sensors.humidity }}%</span>
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">Optimal: 80 – 95%</p>
                <p
                    v-if="store.humidityStatus === 'warning'"
                    class="mt-2 text-xs font-medium text-orange-500"
                >
                    ⚠ Too low — humidifier needed
                </p>
            </div>

            <!-- CO2 -->
            <div
                class="rounded-xl border p-5"
                :class="
                    store.co2Status === 'warning'
                        ? 'border-orange-500/30 bg-orange-500/5'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">CO₂ Level</p>
                <p class="mt-2 text-4xl font-bold tabular-nums">
                    <span v-if="store.sensors.co2_raw !== null">{{ store.sensors.co2_raw }} ppm</span>
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">Limit: &lt; 1000 ppm</p>
                <p
                    v-if="store.co2Status === 'warning'"
                    class="mt-2 text-xs font-medium text-orange-500"
                >
                    ⚠ High — fan needed
                </p>
            </div>

            <!-- Light Level -->
            <div class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Light Level</p>
                <p class="mt-2 text-4xl font-bold tabular-nums">
                    <span v-if="store.sensors.light_level !== null">{{ store.sensors.light_level }} lux</span>
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">Fruiting: 50 – 1000 lux</p>
            </div>

            <!-- Soil Moisture -->
            <div class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Soil Moisture</p>
                <p class="mt-2 text-4xl font-bold tabular-nums">
                    <span v-if="store.sensors.soil_moisture !== null">{{ store.sensors.soil_moisture }}%</span>
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Status:
                    <span class="font-semibold capitalize">{{ store.sensors.soil_status ?? '—' }}</span>
                </p>
            </div>

            <!-- Actuators -->
            <div class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Actuators</p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li class="flex items-center justify-between">
                        <span>Humidifier</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="
                                store.actuators.humidifier === 'on'
                                    ? 'bg-green-500/15 text-green-600 dark:text-green-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ store.actuators.humidifier.toUpperCase() }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>LED Light</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="
                                store.actuators.led === 'on'
                                    ? 'bg-yellow-500/15 text-yellow-600 dark:text-yellow-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ store.actuators.led.toUpperCase() }}
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Fan</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="
                                store.actuators.fan === 'on'
                                    ? 'bg-blue-500/15 text-blue-600 dark:text-blue-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ store.actuators.fan.toUpperCase() }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

