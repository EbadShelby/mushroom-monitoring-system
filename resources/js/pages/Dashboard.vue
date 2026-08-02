<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { dashboard } from '@/routes';
import { useSensorStore } from '@/stores/useSensorStore';
import type { GrowingCycle, CameraSnapshot, AlertLog, ChartPoint } from '@/types';
import VueApexCharts from 'vue3-apexcharts';
import {
    Thermometer,
    Droplets,
    Wind,
    Sun,
    Sprout,
    Activity,
    Zap,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Leaf,
    Camera,
    Bell,
    Calendar,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Live Monitoring',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    activeCycle?: GrowingCycle | null;
    latestSnapshot?: CameraSnapshot | null;
    lastAlert?: AlertLog | null;
    chartData?: any[];
}>();

const store = useSensorStore();

// Real-time clock for the dashboard header
const currentTime = ref('');
let clockInterval: ReturnType<typeof setInterval> | null = null;

function updateClock() {
    currentTime.value = new Date().toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
}

// Refresh chart data from MySQL every 60 s (independent of live Firebase sensor cards)
let chartRefreshInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
    store.startListening();
    chartRefreshInterval = setInterval(() => {
        router.reload({ data: { chart_interval: chartInterval.value }, only: ['chartData'] });
    }, 60_000);
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }
    store.stopListening();
    if (chartRefreshInterval) {
        clearInterval(chartRefreshInterval);
        chartRefreshInterval = null;
    }
});

const anyWarning = computed(() =>
    store.temperatureStatus === 'warning' ||
    store.humidityStatus === 'warning' ||
    store.co2Status === 'warning' ||
    (store.sensors.soil_moisture !== null && store.sensors.soil_moisture < 30),
);

// Chart Interval Selection
const chartInterval = ref('1m');
const chartIntervalLabel = computed(() => {
    const map: Record<string, string> = { '1m': 'Last Hour', '5m': 'Last 6 Hours', '15m': 'Last 24 Hours', '1h': 'Last 7 Days' };
    return map[chartInterval.value] || 'Last Hour';
});
function onIntervalChange() {
    router.reload({ data: { chart_interval: chartInterval.value }, only: ['chartData'] });
}

// Chart options factory
function buildChartOptions(label: string, color: string) {
    return {
        chart: {
            type: 'area',
            height: 160,
            sparkline: { enabled: false },
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 600 },
            background: 'transparent',
        },
        colors: [color],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 100],
            },
        },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        xaxis: {
            type: 'datetime',
            categories: props.chartData?.map((p) => p.time) ?? [],
            labels: { 
                style: { colors: '#94a3b8', fontSize: '10px' },
                datetimeUTC: false,
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false }
        },
        yaxis: {
            labels: { style: { colors: '#94a3b8', fontSize: '10px' }, formatter: (v: number) => v.toFixed(1) },
        },
        grid: { borderColor: '#1e293b', strokeDashArray: 4, yaxis: { lines: { show: true } }, xaxis: { lines: { show: false } } },
        tooltip: {
            theme: 'dark',
            x: { show: true },
            y: { formatter: (v: number) => `${v.toFixed(1)} ${label === 'Temperature' ? '°C' : '%'}` },
        },
    };
}

const tempSeries = computed(() => [
    { name: 'Temperature', data: props.chartData?.map((p) => p.temperature ?? 0) ?? [] },
]);
const humSeries = computed(() => [
    { name: 'Humidity', data: props.chartData?.map((p) => p.humidity ?? 0) ?? [] },
]);
const tempOptions = computed(() => buildChartOptions('Temperature', '#f97316'));
const humOptions = computed(() => buildChartOptions('Humidity', '#3b82f6'));

const alertSensorLabel: Record<string, string> = {
    temperature: 'Temperature',
    humidity: 'Humidity',
    co2_raw: 'CO₂',
    light_level: 'Light',
    soil_moisture: 'Soil',
};

function alertStatusClass(status: string) {
    return status === 'sent'
        ? 'bg-emerald-500/20 text-emerald-500'
        : 'bg-destructive/20 text-destructive';
}
</script>

<template>
    <Head title="Live Monitoring" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <!-- Subtle decorative blobs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-primary/5 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-secondary/5 blur-3xl"></div>
        </div>

        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">
            <!-- Header & Connection Status -->
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                        Mushroom Cultivation
                    </h1>
                    <p class="mt-1 text-muted-foreground flex items-center gap-2">
                        <span>Live environmental metrics and actuator control.</span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-secondary text-secondary-foreground shadow-sm">{{ currentTime }}</span>
                    </p>
                </div>

                <div
                    class="flex items-center gap-3 rounded-full border px-4 py-2 text-sm font-medium shadow-sm transition-all duration-300 backdrop-blur-md"
                    :class="
                        store.isLoading
                            ? 'border-yellow-500/30 bg-yellow-500/10 text-yellow-700 dark:text-yellow-400'
                            : store.isConnected
                              ? 'border-primary/30 bg-primary/10 text-primary-foreground dark:text-primary'
                              : 'border-destructive/30 bg-destructive/10 text-destructive-foreground dark:text-destructive'
                    "
                >
                    <span class="relative flex h-3 w-3">
                        <span
                            v-if="store.isLoading || store.isConnected"
                            class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                            :class="store.isLoading ? 'bg-yellow-400' : 'bg-primary'"
                        ></span>
                        <span
                            class="relative inline-flex h-3 w-3 rounded-full"
                            :class="
                                store.isLoading
                                    ? 'bg-yellow-500'
                                    : store.isConnected
                                      ? 'bg-primary'
                                      : 'bg-destructive'
                            "
                        ></span>
                    </span>
                    <span v-if="store.isLoading">Syncing sensors...</span>
                    <span v-else-if="store.isConnected">
                        Live
                        <span class="hidden sm:inline">— Updated: {{ store.lastUpdatedFormatted }}</span>
                    </span>
                    <span v-else>Offline — Check connection</span>
                </div>
            </div>

            <!-- Alert Banner -->
            <div
                v-if="anyWarning && store.isConnected"
                class="flex items-center gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive-foreground dark:text-destructive backdrop-blur-md shadow-sm"
            >
                <AlertTriangle class="h-4 w-4 shrink-0" />
                <span class="font-medium">One or more sensor readings are outside the optimal range. Check actuator status.</span>
            </div>

            <!-- Sensor Grid -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Temperature -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:shadow-lg hover:-translate-y-1"
                    :class="
                        store.temperatureStatus === 'warning'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12">
                        <Thermometer class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div class="relative z-10 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-orange-500/10 p-2 text-orange-500 shadow-inner">
                                <Thermometer class="h-5 w-5" />
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-muted-foreground">
                                TEMPERATURE
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p class="text-5xl font-bold tracking-tighter text-foreground">
                            <span v-if="store.sensors.temperature !== null">{{ store.sensors.temperature }}</span>
                            <span v-else class="text-muted-foreground/50">--</span>
                        </p>
                        <span class="text-xl font-medium text-muted-foreground">°C</span>
                    </div>

                    <div class="relative z-10 mt-4 flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Target: 28-32°C</span>
                        <div v-if="store.temperatureStatus === 'warning'" class="flex animate-pulse items-center gap-1 font-medium text-destructive">
                            <AlertCircle class="h-4 w-4" /> Out of range
                        </div>
                        <div v-else class="flex items-center gap-1 font-medium text-primary">
                            <CheckCircle2 class="h-4 w-4" /> Optimal
                        </div>
                    </div>
                </div>

                <!-- Humidity -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:shadow-lg hover:-translate-y-1"
                    :class="
                        store.humidityStatus === 'warning'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12">
                        <Droplets class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div class="relative z-10 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner">
                                <Droplets class="h-5 w-5" />
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-muted-foreground">
                                HUMIDITY
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p class="text-5xl font-bold tracking-tighter text-foreground">
                            <span v-if="store.sensors.humidity !== null">{{ store.sensors.humidity }}</span>
                            <span v-else class="text-muted-foreground/50">--</span>
                        </p>
                        <span class="text-xl font-medium text-muted-foreground">%</span>
                    </div>

                    <div class="relative z-10 mt-4 flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Target: 80-95%</span>
                        <div v-if="store.humidityStatus === 'warning'" class="flex animate-pulse items-center gap-1 font-medium text-destructive">
                            <AlertCircle class="h-4 w-4" /> Low
                        </div>
                        <div v-else class="flex items-center gap-1 font-medium text-primary">
                            <CheckCircle2 class="h-4 w-4" /> Optimal
                        </div>
                    </div>
                </div>

                <!-- CO2 -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:shadow-lg hover:-translate-y-1"
                    :class="
                        store.co2Status === 'warning'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12">
                        <Wind class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div class="relative z-10 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-purple-500/10 p-2 text-purple-500 shadow-inner">
                                <Wind class="h-5 w-5" />
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-muted-foreground">
                                CO₂ LEVEL
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p class="text-5xl font-bold tracking-tighter text-foreground">
                            <span v-if="store.sensors.co2_raw !== null">{{ store.sensors.co2_raw }}</span>
                            <span v-else class="text-muted-foreground/50">--</span>
                        </p>
                        <span class="text-xl font-medium text-muted-foreground">ppm</span>
                    </div>

                    <div class="relative z-10 mt-4 flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Target: &lt; 1000</span>
                        <div v-if="store.co2Status === 'warning'" class="flex animate-pulse items-center gap-1 font-medium text-destructive">
                            <AlertCircle class="h-4 w-4" /> High
                        </div>
                        <div v-else class="flex items-center gap-1 font-medium text-primary">
                            <CheckCircle2 class="h-4 w-4" /> Good
                        </div>
                    </div>
                </div>

                <!-- Light Level -->
                <div class="group relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
                    <div class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-45">
                        <Sun class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div class="relative z-10 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner">
                                <Sun class="h-5 w-5" />
                            </div>
                            <p class="text-sm font-semibold tracking-wide text-muted-foreground">
                                LIGHT LEVEL
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p class="text-5xl font-bold tracking-tighter text-foreground">
                            <span v-if="store.sensors.light_level !== null">{{ store.sensors.light_level }}</span>
                            <span v-else class="text-muted-foreground/50">--</span>
                        </p>
                        <span class="text-xl font-medium text-muted-foreground">lux</span>
                    </div>

                    <div class="relative z-10 mt-4 flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Fruiting: 50-1000 lux</span>
                    </div>
                </div>
            </div>

            <!-- Live Charts Row Header & Controls -->
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight text-foreground">Environmental Trends</h2>
                <select
                    v-model="chartInterval"
                    @change="onIntervalChange"
                    class="rounded-md border border-border/50 bg-card/60 px-3 py-1.5 text-sm shadow-sm backdrop-blur-md focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:bg-card/40"
                >
                    <option value="1m">Per 1 Min (Last Hour)</option>
                    <option value="5m">Per 5 Mins (Last 6 Hrs)</option>
                    <option value="15m">Per 15 Mins (Last 24 Hrs)</option>
                    <option value="1h">Per 1 Hour (Last 7 Days)</option>
                </select>
            </div>

            <!-- Live Charts Row -->
            <div class="grid gap-6 lg:grid-cols-2 mt-2">
                <!-- Temperature Chart -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-orange-500/10 p-2 text-orange-500 shadow-inner">
                            <Thermometer class="h-4 w-4" />
                        </div>
                        <h3 class="font-semibold tracking-wide text-muted-foreground uppercase">TEMPERATURE — {{ chartIntervalLabel }}</h3>
                    </div>
                    <div v-if="!chartData" class="animate-pulse space-y-2 py-6">
                        <div class="h-3 w-3/4 rounded bg-muted"></div>
                        <div class="h-24 w-full rounded bg-muted"></div>
                        <div class="h-3 w-1/2 rounded bg-muted"></div>
                    </div>
                    <VueApexCharts
                        v-else
                        type="area"
                        height="160"
                        :options="tempOptions"
                        :series="tempSeries"
                    />
                </div>

                <!-- Humidity Chart -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner">
                            <Droplets class="h-4 w-4" />
                        </div>
                        <h3 class="font-semibold tracking-wide text-muted-foreground uppercase">HUMIDITY — {{ chartIntervalLabel }}</h3>
                    </div>
                    <div v-if="!chartData" class="animate-pulse space-y-2 py-6">
                        <div class="h-3 w-3/4 rounded bg-muted"></div>
                        <div class="h-24 w-full rounded bg-muted"></div>
                        <div class="h-3 w-1/2 rounded bg-muted"></div>
                    </div>
                    <VueApexCharts
                        v-else
                        type="area"
                        height="160"
                        :options="humOptions"
                        :series="humSeries"
                    />
                </div>
            </div>

            <!-- Bottom Section: Soil & Actuators -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Soil Moisture -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm md:col-span-1 transition-all duration-300 hover:shadow-lg">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500 shadow-inner">
                            <Sprout class="h-5 w-5" />
                        </div>
                        <h3 class="font-semibold tracking-wide text-muted-foreground">
                            SOIL MOISTURE
                        </h3>
                    </div>

                    <div class="flex flex-col items-center justify-center py-6">
                        <div class="relative flex h-40 w-40 items-center justify-center rounded-full border-4 border-muted shadow-inner bg-background/50">
                            <div
                                class="absolute inset-0 rounded-full border-4 border-emerald-500 transition-all duration-1000"
                                :style="`clip-path: inset(${100 - (store.sensors.soil_moisture ?? 0)}% 0 0 0)`"
                            ></div>
                            <div class="text-center z-10 bg-background/80 px-3 py-1 rounded-full backdrop-blur-sm">
                                <p class="text-3xl font-bold tracking-tighter text-foreground">
                                    <span v-if="store.sensors.soil_moisture !== null">{{ store.sensors.soil_moisture }}</span>
                                    <span v-else class="text-muted-foreground/50">--</span>
                                    <span class="text-lg text-muted-foreground">%</span>
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center gap-2 rounded-full bg-emerald-500/10 px-4 py-2 font-medium text-emerald-600 capitalize dark:text-emerald-400 shadow-sm">
                            <Activity class="h-4 w-4" />
                            {{ store.sensors.soil_status ?? 'Unknown' }}
                        </div>
                    </div>
                </div>

                <!-- Actuators Panel -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm md:col-span-2 transition-all duration-300 hover:shadow-lg">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-indigo-500/10 p-2 text-indigo-500 shadow-inner">
                                <Zap class="h-5 w-5" />
                            </div>
                            <h3 class="font-semibold tracking-wide text-muted-foreground">
                                SYSTEM AUTOMATION
                            </h3>
                        </div>
                        <span class="rounded-full bg-secondary/80 px-2.5 py-1 text-xs font-medium text-secondary-foreground shadow-sm">
                            Automated
                        </span>
                    </div>

                    <div class="grid h-[calc(100%-4rem)] gap-4 sm:grid-cols-3">
                        <!-- Humidifier Actuator -->
                        <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/40 backdrop-blur-sm p-4 transition-all hover:bg-muted/50 hover:shadow-md">
                            <div class="flex items-start justify-between">
                                <div class="rounded-lg bg-blue-500/10 p-2.5 text-blue-500 shadow-inner">
                                    <Droplets class="h-6 w-6" />
                                </div>
                                <div class="relative flex h-3 w-3" v-if="store.actuators.humidifier === 'on'">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                                    <span class="relative inline-flex h-3 w-3 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]"></span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h4 class="text-lg font-semibold text-foreground">Humidifier</h4>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Relay 1</span>
                                    <span
                                        class="rounded-md px-2 py-1 text-xs font-bold tracking-wider uppercase transition-colors"
                                        :class="
                                            store.actuators.humidifier === 'on'
                                                ? 'bg-blue-500/20 text-blue-600 dark:text-blue-400 shadow-sm'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                    >
                                        {{ store.actuators.humidifier }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Fan Actuator -->
                        <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/40 backdrop-blur-sm p-4 transition-all hover:bg-muted/50 hover:shadow-md">
                            <div class="flex items-start justify-between">
                                <div class="rounded-lg bg-purple-500/10 p-2.5 text-purple-500 shadow-inner">
                                    <Wind class="h-6 w-6" />
                                </div>
                                <div class="relative flex h-3 w-3" v-if="store.actuators.fan === 'on'">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-purple-400 opacity-75"></span>
                                    <span class="relative inline-flex h-3 w-3 rounded-full bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.8)]"></span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h4 class="text-lg font-semibold text-foreground">Intake Fan</h4>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Relay 3</span>
                                    <span
                                        class="rounded-md px-2 py-1 text-xs font-bold tracking-wider uppercase transition-colors"
                                        :class="
                                            store.actuators.fan === 'on'
                                                ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400 shadow-sm'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                    >
                                        {{ store.actuators.fan }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- LED Actuator -->
                        <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/40 backdrop-blur-sm p-4 transition-all hover:bg-muted/50 hover:shadow-md">
                            <div class="flex items-start justify-between">
                                <div class="rounded-lg bg-yellow-500/10 p-2.5 text-yellow-500 shadow-inner">
                                    <Sun class="h-6 w-6" />
                                </div>
                                <div class="relative flex h-3 w-3" v-if="store.actuators.led === 'on'">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-yellow-400 opacity-75"></span>
                                    <span class="relative inline-flex h-3 w-3 rounded-full bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.8)]"></span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h4 class="text-lg font-semibold text-foreground">Grow Lights</h4>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Relay 2</span>
                                    <span
                                        class="rounded-md px-2 py-1 text-xs font-bold tracking-wider uppercase transition-colors"
                                        :class="
                                            store.actuators.led === 'on'
                                                ? 'bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 shadow-sm'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                    >
                                        {{ store.actuators.led }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Row: Active Cycle + Camera Snapshot + Last Alert -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Active Growing Cycle -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm transition-all duration-300 hover:shadow-lg">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-green-500/10 p-2 text-green-500 shadow-inner">
                            <Leaf class="h-5 w-5" />
                        </div>
                        <h3 class="font-semibold tracking-wide text-muted-foreground">ACTIVE CYCLE</h3>
                    </div>

                    <!-- Skeleton -->
                    <div v-if="activeCycle === undefined" class="animate-pulse space-y-3">
                        <div class="h-4 w-3/4 rounded bg-muted"></div>
                        <div class="h-3 w-1/2 rounded bg-muted"></div>
                        <div class="h-3 w-2/3 rounded bg-muted"></div>
                        <div class="h-3 w-1/3 rounded bg-muted"></div>
                    </div>

                    <div v-else-if="activeCycle">
                        <p class="text-xl font-bold text-foreground">{{ activeCycle.name }}</p>
                        <div class="mt-3 space-y-2 text-sm text-muted-foreground">
                            <div class="flex justify-between">
                                <span>Variety</span>
                                <span class="font-medium text-foreground">{{ activeCycle.mushroom_variety }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Substrate</span>
                                <span class="font-medium text-foreground">{{ activeCycle.substrate_type }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Started</span>
                                <span class="font-medium text-foreground">{{ activeCycle.start_date }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Day</span>
                                <span class="rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-bold text-green-600 dark:text-green-400">
                                    Day {{ activeCycle.day_count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-6 text-center text-muted-foreground">
                        <Calendar class="mb-2 h-10 w-10 opacity-30" />
                        <p class="text-sm">No active cycle</p>
                    </div>
                </div>

                <!-- Latest Camera Snapshot -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm transition-all duration-300 hover:shadow-lg">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-teal-500/10 p-2 text-teal-500 shadow-inner">
                            <Camera class="h-5 w-5" />
                        </div>
                        <h3 class="font-semibold tracking-wide text-muted-foreground">LATEST SNAPSHOT</h3>
                    </div>

                    <!-- Skeleton -->
                    <div v-if="latestSnapshot === undefined" class="animate-pulse">
                        <div class="h-36 w-full rounded-lg bg-muted"></div>
                        <div class="mt-2 h-3 w-2/3 rounded bg-muted"></div>
                    </div>

                    <div v-else>
                        <!-- Always show sample image as thumbnail (dummy) -->
                        <div class="overflow-hidden rounded-lg border border-border/50">
                            <img
                                src="/sample-image.png"
                                alt="Latest mushroom snapshot"
                                class="h-36 w-full object-cover transition-transform duration-500 hover:scale-105"
                            />
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ latestSnapshot ? latestSnapshot.captured_at : 'Sample preview' }}
                        </p>
                    </div>
                </div>

                <!-- Last SMS Alert -->
                <div class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm transition-all duration-300 hover:shadow-lg">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-red-500/10 p-2 text-red-500 shadow-inner">
                            <Bell class="h-5 w-5" />
                        </div>
                        <h3 class="font-semibold tracking-wide text-muted-foreground">LAST SMS ALERT</h3>
                    </div>

                    <!-- Skeleton -->
                    <div v-if="lastAlert === undefined" class="animate-pulse space-y-3">
                        <div class="h-4 w-1/2 rounded bg-muted"></div>
                        <div class="h-3 w-full rounded bg-muted"></div>
                        <div class="h-3 w-3/4 rounded bg-muted"></div>
                    </div>

                    <div v-else-if="lastAlert">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold uppercase text-foreground">
                                {{ alertSensorLabel[lastAlert.sensor] ?? lastAlert.sensor }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="alertStatusClass(lastAlert.status)"
                            >
                                {{ lastAlert.status }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-muted-foreground line-clamp-3">{{ lastAlert.message }}</p>
                        <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                            <span>Value: <strong class="text-foreground">{{ lastAlert.value_at_alert }}</strong></span>
                            <span>{{ lastAlert.sent_at }}</span>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-6 text-center text-muted-foreground">
                        <Bell class="mb-2 h-10 w-10 opacity-30" />
                        <p class="text-sm">No alerts yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
