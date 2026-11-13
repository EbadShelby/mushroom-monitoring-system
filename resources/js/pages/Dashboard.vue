<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Thermometer,
    Droplets,
    Wind,
    Sun,
    Sprout,
    Zap,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Leaf,
    Camera,
    Bell,
    Calendar,
    Microscope,
    Layers,
    ArrowRightLeft,
    X,
    Droplet,
    RefreshCw,
} from '@lucide/vue';
import type { ApexOptions } from 'apexcharts';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watchEffect } from 'vue';
import { toast } from 'vue-sonner';
import VueApexCharts from 'vue3-apexcharts';
import SmsMuteControl from '@/components/SmsMuteControl.vue';
import { dashboard } from '@/routes';
import { useSensorStore } from '@/stores/useSensorStore';

const showLightbox = ref(false);
import type {
    GrowingCycle,
    CameraSnapshot,
    AlertLog,
    HumidifierMist,
    HumidifierTank,
    SmsMuteStatus,
} from '@/types';

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
    tank?: HumidifierTank;
    mist?: HumidifierMist;
    smsMute?: SmsMuteStatus | null;
}>();

const page = usePage();
const userRole = computed(
    () => (page.props.auth as any)?.user?.role ?? 'student',
);

const store = useSensorStore();

// Stage awareness
const activeStage = computed(
    () => props.activeCycle?.growing_stage ?? 'colonization',
);
const isColonization = computed(() => activeStage.value === 'colonization');

const snapshotImage = computed(() => {
    return props.latestSnapshot?.file_path
        ? `/storage/${props.latestSnapshot.file_path}`
        : '/sample-image.jpg';
});

const snapshotAlt = computed(() => {
    return props.latestSnapshot?.file_name ?? 'Latest mushroom snapshot';
});

const snapshotLabel = computed(() => {
    if (!props.latestSnapshot) {
        return 'No photo yet (sample preview)';
    }

    if (props.latestSnapshot.snapshot_number) {
        return `Snapshot ${props.latestSnapshot.snapshot_number} • ${props.latestSnapshot.captured_date ?? ''}`;
    }

    return `Snapshot • ${props.latestSnapshot.captured_date ?? ''}`;
});

watchEffect(() => {
    store.setStage(activeStage.value);
});

const switchingStage = ref(false);
async function toggleStage() {
    if (!props.activeCycle?.id) {
        return;
    }

    const nextStage = isColonization.value ? 'fruiting' : 'colonization';
    const stageLabel = nextStage === 'fruiting' ? 'Fruiting' : 'Colonization';

    if (
        !confirm(
            `Switch "${props.activeCycle.name}" to ${stageLabel} stage? This updates active environmental targets & automation logic.`,
        )
    ) {
        return;
    }

    switchingStage.value = true;

    try {
        await axios.post(`/api/cycles/${props.activeCycle.id}/switch-stage`, {
            growing_stage: nextStage,
        });
        toast.success(`Switched to ${stageLabel} stage — thresholds updated.`);
        router.reload({ only: ['activeCycle'] });
    } catch (e: any) {
        toast.error(e.response?.data?.error ?? 'Failed to switch stage');
    } finally {
        switchingStage.value = false;
    }
}

// Real-time clock for the dashboard header
const currentTime = ref('');
const nowMs = ref(Date.now());
let clockInterval: ReturnType<typeof setInterval> | null = null;

function updateClock() {
    nowMs.value = Date.now();
    currentTime.value = new Date(nowMs.value).toLocaleTimeString('en-US', {
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
    chartRefreshInterval = setInterval(() => {
        router.reload({
            data: { chart_interval: chartInterval.value },
            only: ['chartData'],
        });
    }, 60_000);
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }

    if (chartRefreshInterval) {
        clearInterval(chartRefreshInterval);
        chartRefreshInterval = null;
    }
});

const hasActiveCycle = computed(() => Boolean(props.activeCycle));

const anyWarning = computed(
    () =>
        hasActiveCycle.value &&
        (store.temperatureStatus !== 'normal' ||
            store.humidityStatus !== 'normal' ||
            store.co2Status !== 'normal' ||
            store.lightStatus !== 'normal' ||
            store.soilStatus !== 'normal'),
);

// Chart Interval Selection
const chartInterval = ref('1m');
const chartIntervalLabel = computed(() => {
    const map: Record<string, string> = {
        '1m': 'Last Hour',
        '5m': 'Last 6 Hours',
        '15m': 'Last 24 Hours',
        '1h': 'Last 7 Days',
    };

    return map[chartInterval.value] || 'Last Hour';
});
function onIntervalChange() {
    router.reload({
        data: { chart_interval: chartInterval.value },
        only: ['chartData'],
    });
}

// Chart options factory
function buildChartOptions(label: string, color: string): ApexOptions {
    return {
        chart: {
            type: 'area' as const,
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
                datetimeFormatter: {
                    year: 'yyyy',
                    month: 'MMM',
                    day: 'dd MMM',
                    hour: 'h:mm TT',
                    minute: 'h:mm TT',
                    second: 'h:mm:ss TT',
                },
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: {
            labels: {
                style: { colors: '#94a3b8', fontSize: '10px' },
                formatter: (v: number) => v.toFixed(1),
            },
        },
        grid: {
            borderColor: '#1e293b',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } },
            xaxis: { lines: { show: false } },
        },
        tooltip: {
            theme: 'dark',
            x: { show: true, format: 'dd MMM, h:mm TT' },
            y: {
                formatter: (v: number) =>
                    `${v.toFixed(1)} ${label === 'Temperature' ? '°C' : '%'}`,
            },
        },
    };
}

const tempSeries = computed(() => [
    {
        name: 'Temperature',
        data: props.chartData?.map((p) => p.temperature ?? 0) ?? [],
    },
]);
const humSeries = computed(() => [
    {
        name: 'Humidity',
        data: props.chartData?.map((p) => p.humidity ?? 0) ?? [],
    },
]);
const tempOptions = computed(() => buildChartOptions('Temperature', '#f97316'));
const humOptions = computed(() => buildChartOptions('Humidity', '#3b82f6'));

const alertSensorLabel: Record<string, string> = {
    temperature: 'Temperature',
    humidity: 'Humidity',
    co2_raw: 'CO₂',
    light_level: 'Light',
    soil_moisture: 'Substrate',
};

function alertStatusClass(status: string) {
    return status === 'sent'
        ? 'bg-emerald-500/20 text-emerald-500'
        : 'bg-destructive/20 text-destructive';
}

function formatAlertDate(dateStr: string | null | undefined): string {
    if (!dateStr) {
        return '—';
    }

    const d = new Date(dateStr);

    if (isNaN(d.getTime())) {
        return dateStr;
    }

    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

const refillingTank = ref(false);

async function refillTank() {
    refillingTank.value = true;

    try {
        await axios.post('/api/actuators/tank-refill');
        toast.success('Water tank refill logged.');
        router.reload({ only: ['tank', 'mist'] });
    } catch {
        toast.error('Failed to log tank refill.');
    } finally {
        refillingTank.value = false;
    }
}

const liveMistSeconds = computed(() =>
    props.mist?.running
        ? Math.max(
              0,
              Math.floor((nowMs.value - Date.parse(props.mist.as_of)) / 1000),
          )
        : 0,
);
const mistRate = computed(() => props.mist?.rate_ml_per_second ?? 0);
const mistUnitRate = computed(() => props.mist?.unit_rate_ml_per_second ?? 0);
const mistUnitCount = computed(() => props.mist?.unit_count ?? 4);
const todayMistMl = computed(
    () => (props.mist?.today.ml ?? 0) + liveMistSeconds.value * mistRate.value,
);
const todayMistSeconds = computed(
    () => (props.mist?.today.seconds ?? 0) + liveMistSeconds.value,
);
const weekMaxMistMl = computed(() =>
    Math.max(
        1,
        todayMistMl.value,
        ...(props.mist?.week.map((d) => d.ml) ?? [0]),
    ),
);

function formatDuration(totalSeconds: number): string {
    const s = Math.max(0, Math.round(totalSeconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);

    if (h > 0) {
        return `${h}h ${m}m`;
    }

    return `${m}m`;
}
</script>

<template>
    <Head title="Live Monitoring" />

    <div
        class="relative flex h-full min-h-[calc(100vh-(--spacing(16)))] flex-1 flex-col bg-linear-to-br from-primary/5 via-background to-secondary/10"
    >
        <!-- Subtle decorative blobs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-primary/5 blur-3xl"
            ></div>
            <div
                class="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-secondary/5 blur-3xl"
            ></div>
        </div>

        <div
            class="z-10 mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
        >
            <!-- Header & Connection Status -->
            <div
                class="flex flex-col justify-between gap-4 md:flex-row md:items-end"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1
                            class="bg-linear-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                        >
                            Mushroom Cultivation
                        </h1>
                        <span
                            v-if="activeCycle"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold shadow-sm ring-1 ring-inset"
                            :class="
                                isColonization
                                    ? 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-400 dark:ring-amber-500/30'
                                    : 'bg-purple-100 text-purple-700 ring-purple-600/20 dark:bg-purple-500/15 dark:text-purple-400 dark:ring-purple-500/30'
                            "
                        >
                            <component
                                :is="isColonization ? Microscope : Layers"
                                class="h-3.5 w-3.5"
                            />
                            {{
                                isColonization
                                    ? 'Colonization Stage'
                                    : 'Fruiting Stage'
                            }}
                        </span>
                    </div>
                    <p
                        class="mt-1 flex items-center gap-2 text-muted-foreground"
                    >
                        <span
                            >Live environmental metrics and actuator
                            control.</span
                        >
                        <span
                            class="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground shadow-sm"
                            >{{ currentTime }}</span
                        >
                    </p>
                </div>

                <div
                    class="flex items-center gap-3 rounded-full border px-4 py-2 text-sm font-medium shadow-sm backdrop-blur-md transition-all duration-300"
                    :class="
                        store.isLoading
                            ? 'border-yellow-500/30 bg-yellow-500/10 text-yellow-700 dark:text-yellow-500'
                            : store.isConnected
                              ? 'border-primary/30 bg-primary/10 text-primary'
                              : 'border-destructive/30 bg-destructive/10 text-destructive'
                    "
                >
                    <span class="relative flex h-3 w-3">
                        <span
                            v-if="store.isLoading || store.isConnected"
                            class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                            :class="
                                store.isLoading ? 'bg-yellow-400' : 'bg-primary'
                            "
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
                        <span class="hidden sm:inline"
                            >— Updated: {{ store.lastUpdatedFormatted }}</span
                        >
                    </span>
                    <span v-else>Offline — Check connection</span>
                </div>
            </div>

            <!-- Alert Banner -->
            <div
                v-if="anyWarning && store.isConnected"
                class="flex items-center gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-sm backdrop-blur-md dark:text-destructive"
            >
                <AlertTriangle class="h-4 w-4 shrink-0" />
                <span class="font-medium"
                    >One or more sensor readings are outside the
                    {{ isColonization ? 'Colonization' : 'Fruiting' }} optimal
                    stage range. Check actuator status.</span
                >
            </div>

            <!-- Sensor Grid -->
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <!-- Temperature -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                    :class="
                        hasActiveCycle && store.temperatureStatus !== 'normal'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div
                        class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                    >
                        <Thermometer class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-orange-500/10 p-2 text-orange-500 shadow-inner"
                            >
                                <Thermometer class="h-5 w-5" />
                            </div>
                            <p
                                class="text-sm font-semibold tracking-wide text-muted-foreground"
                            >
                                TEMPERATURE
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p
                            class="text-5xl font-bold tracking-tighter text-foreground"
                        >
                            <span v-if="store.sensors.temperature !== null">{{
                                store.sensors.temperature
                            }}</span>
                            <span v-else class="text-muted-foreground/50"
                                >--</span
                            >
                        </p>
                        <span class="text-xl font-medium text-muted-foreground"
                            >°C</span
                        >
                    </div>

                    <div
                        class="relative z-10 mt-4 flex items-center justify-between text-sm"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Target: 26–32°C</span
                        >
                        <span
                            v-if="!hasActiveCycle"
                            class="text-xs font-medium text-muted-foreground"
                            >Idle</span
                        >
                        <div
                            v-else-if="store.temperatureStatus !== 'normal'"
                            class="flex animate-pulse items-center gap-1 font-medium text-destructive"
                        >
                            <AlertCircle class="h-4 w-4" /> Out of range
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-1 font-medium text-primary"
                        >
                            <CheckCircle2 class="h-4 w-4" /> Optimal
                        </div>
                    </div>
                </div>

                <!-- Humidity -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                    :class="
                        hasActiveCycle && store.humidityStatus !== 'normal'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div
                        class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                    >
                        <Droplets class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner"
                            >
                                <Droplets class="h-5 w-5" />
                            </div>
                            <p
                                class="text-sm font-semibold tracking-wide text-muted-foreground"
                            >
                                HUMIDITY
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p
                            class="text-5xl font-bold tracking-tighter text-foreground"
                        >
                            <span v-if="store.sensors.humidity !== null">{{
                                store.sensors.humidity
                            }}</span>
                            <span v-else class="text-muted-foreground/50"
                                >--</span
                            >
                        </p>
                        <span class="text-xl font-medium text-muted-foreground"
                            >%</span
                        >
                    </div>

                    <div
                        class="relative z-10 mt-4 flex items-center justify-between text-sm"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Target: 75–95%</span
                        >
                        <span
                            v-if="!hasActiveCycle"
                            class="text-xs font-medium text-muted-foreground"
                            >Idle</span
                        >
                        <div
                            v-else-if="store.humidityStatus !== 'normal'"
                            class="flex animate-pulse items-center gap-1 font-medium text-destructive"
                        >
                            <AlertCircle class="h-4 w-4" /> Out of range
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-1 font-medium text-primary"
                        >
                            <CheckCircle2 class="h-4 w-4" /> Optimal
                        </div>
                    </div>
                </div>

                <!-- CO2 -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                    :class="
                        hasActiveCycle && store.co2Status !== 'normal'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div
                        class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                    >
                        <Wind class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-purple-500/10 p-2 text-purple-500 shadow-inner"
                            >
                                <Wind class="h-5 w-5" />
                            </div>
                            <p
                                class="text-sm font-semibold tracking-wide text-muted-foreground"
                            >
                                CO₂ LEVEL
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p
                            class="text-5xl font-bold tracking-tighter text-foreground"
                        >
                            <span v-if="store.sensors.co2_raw !== null">{{
                                store.sensors.co2_raw
                            }}</span>
                            <span v-else class="text-muted-foreground/50"
                                >--</span
                            >
                        </p>
                        <span class="text-xl font-medium text-muted-foreground"
                            >ppm</span
                        >
                    </div>

                    <div
                        class="relative z-10 mt-4 flex items-center justify-between text-sm"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Target:
                            {{ isColonization ? '< 5,000' : '< 1,000' }}
                            ppm</span
                        >
                        <span
                            v-if="!hasActiveCycle"
                            class="text-xs font-medium text-muted-foreground"
                            >Idle</span
                        >
                        <div
                            v-else-if="store.co2Status !== 'normal'"
                            class="flex animate-pulse items-center gap-1 font-medium text-destructive"
                        >
                            <AlertCircle class="h-4 w-4" /> High
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-1 font-medium text-primary"
                        >
                            <CheckCircle2 class="h-4 w-4" /> Good
                        </div>
                    </div>
                </div>

                <!-- Light Level -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                    :class="
                        hasActiveCycle && store.lightStatus !== 'normal'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div
                        class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-45"
                    >
                        <Sun class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner"
                            >
                                <Sun class="h-5 w-5" />
                            </div>
                            <p
                                class="text-sm font-semibold tracking-wide text-muted-foreground"
                            >
                                LIGHT LEVEL
                            </p>
                        </div>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p
                            class="text-5xl font-bold tracking-tighter text-foreground"
                        >
                            <span v-if="store.sensors.light_level !== null">{{
                                store.sensors.light_level
                            }}</span>
                            <span v-else class="text-muted-foreground/50"
                                >--</span
                            >
                        </p>
                        <span class="text-xl font-medium text-muted-foreground"
                            >lux</span
                        >
                    </div>

                    <div
                        class="relative z-10 mt-4 flex items-center justify-between text-sm"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Target:
                            {{
                                isColonization
                                    ? '0–50 lux (Dim)'
                                    : '200–800 lux'
                            }}</span
                        >
                        <span
                            v-if="!hasActiveCycle"
                            class="text-xs font-medium text-muted-foreground"
                            >Idle</span
                        >
                        <div
                            v-else-if="store.lightStatus !== 'normal'"
                            class="flex animate-pulse items-center gap-1 font-medium text-destructive"
                        >
                            <AlertCircle class="h-4 w-4" />
                            {{ isColonization ? 'Too Bright' : 'Out of range' }}
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-1 font-medium text-primary"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            {{ isColonization ? 'Dark' : 'Optimal' }}
                        </div>
                    </div>
                </div>

                <!-- Substrate Moisture -->
                <div
                    class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                    :class="
                        hasActiveCycle && store.soilStatus !== 'normal'
                            ? 'border-destructive/50 bg-destructive/5 backdrop-blur-md'
                            : 'border-border/50 bg-card/60 backdrop-blur-md'
                    "
                >
                    <div
                        class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                    >
                        <Sprout class="h-32 w-32" stroke-width="1.5" />
                    </div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500 shadow-inner"
                            >
                                <Sprout class="h-5 w-5" />
                            </div>
                            <p
                                class="text-sm font-semibold tracking-wide text-muted-foreground"
                            >
                                SUBSTRATE
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-extrabold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            {{ store.sensors.soil_status ?? 'Unknown' }}
                        </span>
                    </div>

                    <div class="relative z-10 mt-6 flex items-baseline gap-2">
                        <p
                            class="text-5xl font-bold tracking-tighter text-foreground"
                        >
                            <span v-if="store.sensors.soil_moisture !== null">{{
                                store.sensors.soil_moisture
                            }}</span>
                            <span v-else class="text-muted-foreground/50"
                                >--</span
                            >
                        </p>
                        <span class="text-xl font-medium text-muted-foreground"
                            >%</span
                        >
                    </div>

                    <div
                        class="relative z-10 mt-4 flex items-center justify-between text-sm"
                    >
                        <span class="font-medium text-muted-foreground"
                            >Target: ≥ 30%</span
                        >
                        <span
                            v-if="!hasActiveCycle"
                            class="text-xs font-medium text-muted-foreground"
                            >Idle</span
                        >
                        <div
                            v-else-if="store.soilStatus !== 'normal'"
                            class="flex animate-pulse items-center gap-1 font-medium text-destructive"
                        >
                            <AlertCircle class="h-4 w-4" />
                            {{
                                store.soilStatus === 'critical'
                                    ? 'Critical'
                                    : 'Dry'
                            }}
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-1 font-medium text-primary"
                        >
                            <CheckCircle2 class="h-4 w-4" /> Optimal
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Charts Row Header & Controls -->
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight text-foreground">
                    Environmental Trends
                </h2>
                <select
                    v-model="chartInterval"
                    @change="onIntervalChange"
                    class="rounded-md border border-border/50 bg-card/60 px-3 py-1.5 text-sm shadow-sm backdrop-blur-md focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none dark:bg-card/40"
                >
                    <option value="1m">Per 1 Min (Last Hour)</option>
                    <option value="5m">Per 5 Mins (Last 6 Hrs)</option>
                    <option value="15m">Per 15 Mins (Last 24 Hrs)</option>
                    <option value="1h">Per 1 Hour (Last 7 Days)</option>
                </select>
            </div>

            <!-- Live Charts Row -->
            <div class="mt-2 grid gap-6 lg:grid-cols-2">
                <!-- Temperature Chart -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-orange-500/10 p-2 text-orange-500 shadow-inner"
                        >
                            <Thermometer class="h-4 w-4" />
                        </div>
                        <h3
                            class="font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            TEMPERATURE — {{ chartIntervalLabel }}
                        </h3>
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
                <div
                    class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner"
                        >
                            <Droplets class="h-4 w-4" />
                        </div>
                        <h3
                            class="font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            HUMIDITY — {{ chartIntervalLabel }}
                        </h3>
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

            <!-- Operational Row: Water Tank + Mist Output + System Automation -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Water Tank Level -->
                <div
                    class="relative flex flex-col justify-between overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                >
                    <div>
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div
                                    class="rounded-lg p-2 shadow-inner"
                                    :class="
                                        (tank?.percent ?? 100) <=
                                        (tank?.low_percent ?? 20)
                                            ? 'bg-red-500/10 text-red-500'
                                            : 'bg-blue-500/10 text-blue-500'
                                    "
                                >
                                    <Droplet class="h-5 w-5" />
                                </div>
                                <h3
                                    class="font-semibold tracking-wide text-muted-foreground"
                                >
                                    WATER TANK
                                </h3>
                            </div>
                            <span
                                v-if="tank?.tracking"
                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="
                                    tank.percent <= tank.low_percent
                                        ? 'bg-red-500/20 text-red-600 dark:text-red-400'
                                        : 'bg-blue-500/20 text-blue-600 dark:text-blue-400'
                                "
                            >
                                {{
                                    tank.percent <= tank.low_percent
                                        ? 'Low Water'
                                        : 'Normal'
                                }}
                            </span>
                        </div>

                        <div v-if="tank">
                            <div class="flex items-baseline justify-between">
                                <div class="text-2xl font-bold text-foreground">
                                    {{ tank.remaining_ml }}
                                    <span
                                        class="text-sm font-normal text-muted-foreground"
                                    >
                                        / {{ tank.capacity_ml }} mL
                                    </span>
                                </div>
                                <span
                                    class="text-sm font-semibold text-muted-foreground"
                                >
                                    {{ tank.percent }}%
                                </span>
                            </div>

                            <!-- Progress bar -->
                            <div
                                class="mt-2.5 h-2.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="
                                        tank.percent <= tank.low_percent
                                            ? 'bg-red-500'
                                            : 'bg-blue-500'
                                    "
                                    :style="{ width: `${tank.percent}%` }"
                                ></div>
                            </div>

                            <p class="mt-3 text-xs text-muted-foreground">
                                <template v-if="tank.tracking">
                                    ≈
                                    {{
                                        formatDuration(
                                            tank.runtime_left_seconds,
                                        )
                                    }}
                                    misting left
                                </template>
                                <template v-else>
                                    Not tracking yet. Refill and log to start.
                                </template>
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="userRole !== 'student'"
                        class="mt-4 flex items-center justify-between border-t border-border/40 pt-3"
                    >
                        <button
                            type="button"
                            @click="refillTank"
                            :disabled="refillingTank"
                            class="flex items-center gap-1.5 text-xs font-semibold text-blue-500 transition-colors hover:text-blue-400 disabled:opacity-50"
                        >
                            <RefreshCw
                                class="h-3.5 w-3.5"
                                :class="{ 'animate-spin': refillingTank }"
                            />
                            {{ refillingTank ? 'Logging...' : 'Log Refill' }}
                        </button>

                        <Link
                            href="/actuators"
                            class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Actuators →
                        </Link>
                    </div>
                </div>

                <!-- Mist Output -->
                <div
                    class="relative flex flex-col justify-between overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                >
                    <div>
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div
                                    class="rounded-lg bg-cyan-500/10 p-2 text-cyan-500 shadow-inner"
                                >
                                    <Droplets class="h-5 w-5" />
                                </div>
                                <h3
                                    class="font-semibold tracking-wide text-muted-foreground"
                                >
                                    MIST OUTPUT
                                </h3>
                            </div>
                            <span
                                class="rounded-full bg-cyan-500/10 px-2 py-0.5 text-[11px] font-semibold text-cyan-600 dark:text-cyan-400"
                            >
                                Est.
                            </span>
                        </div>

                        <div v-if="mist">
                            <p class="text-2xl font-bold text-foreground">
                                {{ todayMistMl.toFixed(1) }}
                                <span
                                    class="text-sm font-normal text-muted-foreground"
                                >
                                    mL today
                                </span>
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ formatDuration(todayMistSeconds) }} on ·
                                {{ mistUnitCount }} × {{ mistUnitRate }} mL/s
                            </p>

                            <div class="mt-4 flex h-16 items-end gap-1.5">
                                <div
                                    v-for="(day, i) in mist.week"
                                    :key="day.date"
                                    class="group relative flex h-full flex-1 flex-col items-center gap-1"
                                >
                                    <div class="flex w-full flex-1 items-end">
                                        <div
                                            class="w-full rounded-t transition-all"
                                            :class="
                                                i === 6
                                                    ? 'bg-cyan-500 shadow-sm shadow-cyan-500/30'
                                                    : 'bg-cyan-500/35 group-hover:bg-cyan-500/70'
                                            "
                                            :style="{
                                                height: `${Math.max(
                                                    6,
                                                    ((i === 6
                                                        ? todayMistMl
                                                        : day.ml) /
                                                        weekMaxMistMl) *
                                                        100,
                                                )}%`,
                                            }"
                                            :title="`${day.date} (${i === 6 ? 'Today' : i === 5 ? 'Yesterday' : day.label}): ${(i === 6 ? todayMistMl : day.ml).toFixed(1)} mL · ${formatDuration(i === 6 ? todayMistSeconds : day.seconds)}`"
                                        ></div>
                                    </div>
                                    <span
                                        class="text-[10px]"
                                        :class="
                                            i === 6
                                                ? 'font-bold text-cyan-500 dark:text-cyan-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ i === 6 ? 'Today' : day.label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between border-t border-border/40 pt-3 text-xs text-muted-foreground"
                    >
                        <span>Last 7 days output</span>
                        <Link
                            href="/actuators"
                            class="transition-colors hover:text-foreground"
                        >
                            Details →
                        </Link>
                    </div>
                </div>

                <!-- Compact System Automation -->
                <div
                    class="relative flex flex-col justify-between overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                >
                    <div>
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div
                                    class="rounded-lg bg-indigo-500/10 p-2 text-indigo-500 shadow-inner"
                                >
                                    <Zap class="h-5 w-5" />
                                </div>
                                <h3
                                    class="font-semibold tracking-wide text-muted-foreground"
                                >
                                    SYSTEM AUTOMATION
                                </h3>
                            </div>
                            <span
                                class="rounded-full bg-secondary/80 px-2.5 py-0.5 text-[11px] font-medium text-secondary-foreground"
                            >
                                Automated
                            </span>
                        </div>

                        <div class="space-y-2.5">
                            <!-- Humidifier Actuator -->
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/50 bg-background/40 px-3.5 py-2.5 transition-colors hover:bg-muted/50"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="rounded-lg bg-blue-500/10 p-2 text-blue-500"
                                    >
                                        <Droplets class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            Humidifier
                                        </p>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Relay 1
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="
                                            store.actuators.humidifier === 'on'
                                        "
                                        class="relative flex h-2.5 w-2.5"
                                    >
                                        <span
                                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
                                        ></span>
                                        <span
                                            class="relative inline-flex h-2.5 w-2.5 rounded-full bg-blue-500"
                                        ></span>
                                    </span>
                                    <span
                                        class="rounded-md px-2 py-0.5 text-xs font-bold tracking-wider uppercase"
                                        :class="
                                            store.actuators.humidifier === 'on'
                                                ? 'bg-blue-500/20 text-blue-600 dark:text-blue-400'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                    >
                                        {{ store.actuators.humidifier }}
                                    </span>
                                </div>
                            </div>

                            <!-- LED Actuator -->
                            <div
                                class="flex items-center justify-between rounded-xl border border-border/50 bg-background/40 px-3.5 py-2.5 transition-colors hover:bg-muted/50"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500"
                                    >
                                        <Sun class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            Grow Lights
                                        </p>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Relay 2
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="store.actuators.led === 'on'"
                                        class="relative flex h-2.5 w-2.5"
                                    >
                                        <span
                                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-yellow-400 opacity-75"
                                        ></span>
                                        <span
                                            class="relative inline-flex h-2.5 w-2.5 rounded-full bg-yellow-500"
                                        ></span>
                                    </span>
                                    <span
                                        class="rounded-md px-2 py-0.5 text-xs font-bold tracking-wider uppercase"
                                        :class="
                                            store.actuators.led === 'on'
                                                ? 'bg-yellow-500/20 text-yellow-600 dark:text-yellow-400'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                    >
                                        {{ store.actuators.led }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="userRole !== 'student'"
                        class="mt-4 flex items-center justify-between border-t border-border/40 pt-3 text-xs text-muted-foreground"
                    >
                        <span>Relay controls</span>
                        <Link
                            href="/actuators"
                            class="transition-colors hover:text-foreground"
                        >
                            Manage →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Info Row: Active Cycle + Camera Snapshot + Last Alert -->
            <div
                class="grid gap-6"
                :class="
                    userRole === 'student'
                        ? 'md:grid-cols-2 lg:grid-cols-2'
                        : 'md:grid-cols-2 lg:grid-cols-3'
                "
            >
                <!-- Active Growing Cycle -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-green-500/10 p-2 text-green-500 shadow-inner"
                        >
                            <Leaf class="h-5 w-5" />
                        </div>
                        <h3
                            class="font-semibold tracking-wide text-muted-foreground"
                        >
                            ACTIVE CYCLE
                        </h3>
                    </div>

                    <!-- Skeleton -->
                    <div
                        v-if="activeCycle === undefined"
                        class="animate-pulse space-y-3"
                    >
                        <div class="h-4 w-3/4 rounded bg-muted"></div>
                        <div class="h-3 w-1/2 rounded bg-muted"></div>
                        <div class="h-3 w-2/3 rounded bg-muted"></div>
                        <div class="h-3 w-1/3 rounded bg-muted"></div>
                    </div>

                    <div v-else-if="activeCycle">
                        <p class="text-xl font-bold text-foreground">
                            {{ activeCycle.name }}
                        </p>
                        <div
                            class="mt-3 space-y-2 text-sm text-muted-foreground"
                        >
                            <div class="flex items-center justify-between">
                                <span>Stage</span>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold ring-1 ring-inset"
                                        :class="
                                            isColonization
                                                ? 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-500/20 dark:text-amber-400 dark:ring-amber-500/30'
                                                : 'bg-purple-100 text-purple-700 ring-purple-600/20 dark:bg-purple-500/20 dark:text-purple-400 dark:ring-purple-500/30'
                                        "
                                    >
                                        <component
                                            :is="
                                                isColonization
                                                    ? Microscope
                                                    : Layers
                                            "
                                            class="h-3 w-3"
                                        />
                                        {{
                                            isColonization
                                                ? 'Colonization'
                                                : 'Fruiting'
                                        }}
                                    </span>
                                    <button
                                        v-if="userRole !== 'student'"
                                        @click="toggleStage"
                                        :disabled="switchingStage"
                                        class="inline-flex items-center gap-1 rounded-md bg-secondary/80 px-2 py-0.5 text-[11px] font-medium text-secondary-foreground transition-colors hover:bg-secondary"
                                        title="Switch active growth stage"
                                    >
                                        <ArrowRightLeft class="h-3 w-3" />
                                        Switch
                                    </button>
                                </div>
                            </div>
                            <div class="flex justify-between">
                                <span>Variety</span>
                                <span class="font-medium text-foreground">{{
                                    activeCycle.mushroom_variety
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Substrate</span>
                                <span class="font-medium text-foreground">{{
                                    activeCycle.substrate_type
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Started</span>
                                <span class="font-medium text-foreground">{{
                                    activeCycle.start_date
                                }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Day</span>
                                <span
                                    class="rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-bold text-green-600 dark:text-green-400"
                                >
                                    Day {{ activeCycle.day_count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-6 text-center text-muted-foreground"
                    >
                        <Calendar class="mb-2 h-10 w-10 opacity-30" />
                        <p class="text-sm">No active cycle</p>
                    </div>
                </div>

                <!-- Latest Camera Snapshot -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-teal-500/10 p-2 text-teal-500 shadow-inner"
                        >
                            <Camera class="h-5 w-5" />
                        </div>
                        <h3
                            class="font-semibold tracking-wide text-muted-foreground"
                        >
                            LATEST SNAPSHOT
                        </h3>
                    </div>

                    <!-- Skeleton -->
                    <div
                        v-if="latestSnapshot === undefined"
                        class="animate-pulse"
                    >
                        <div class="h-36 w-full rounded-lg bg-muted"></div>
                        <div class="mt-2 h-3 w-2/3 rounded bg-muted"></div>
                    </div>

                    <div v-else>
                        <div
                            class="cursor-pointer overflow-hidden rounded-lg border border-border/50 ring-offset-background transition-all hover:ring-2 hover:ring-emerald-500/50"
                            @click="showLightbox = true"
                        >
                            <img
                                :src="snapshotImage"
                                :alt="snapshotAlt"
                                class="h-36 w-full object-cover transition-transform duration-500 hover:scale-105"
                            />
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ snapshotLabel }}
                        </p>
                    </div>
                </div>

                <!-- Last SMS Alert -->
                <div
                    v-if="userRole !== 'student'"
                    class="relative rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-red-500/10 p-2 text-red-500 shadow-inner"
                            >
                                <Bell class="h-5 w-5" />
                            </div>
                            <h3
                                class="font-semibold tracking-wide text-muted-foreground"
                            >
                                LAST SMS ALERT
                            </h3>
                        </div>
                        <SmsMuteControl :initial-status="smsMute" compact />
                    </div>

                    <!-- Skeleton -->
                    <div
                        v-if="lastAlert === undefined"
                        class="animate-pulse space-y-3"
                    >
                        <div class="h-4 w-1/2 rounded bg-muted"></div>
                        <div class="h-3 w-full rounded bg-muted"></div>
                        <div class="h-3 w-3/4 rounded bg-muted"></div>
                    </div>

                    <div v-else-if="lastAlert">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-sm font-semibold text-foreground uppercase"
                            >
                                {{
                                    alertSensorLabel[lastAlert.sensor] ??
                                    lastAlert.sensor
                                }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                :class="alertStatusClass(lastAlert.status)"
                            >
                                {{ lastAlert.status }}
                            </span>
                        </div>
                        <p
                            class="mt-3 line-clamp-3 text-sm text-muted-foreground"
                        >
                            {{ lastAlert.message }}
                        </p>
                        <div
                            class="mt-3 flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span
                                >Value:
                                <strong class="text-foreground">{{
                                    lastAlert.value_at_alert
                                }}</strong></span
                            >
                            <span>{{
                                formatAlertDate(lastAlert.sent_at)
                            }}</span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-6 text-center text-muted-foreground"
                    >
                        <Bell class="mb-2 h-10 w-10 opacity-30" />
                        <p class="text-sm">No alerts yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Image Lightbox -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="showLightbox"
                class="fixed inset-0 z-100 flex items-center justify-center"
                style="background: rgba(0, 0, 0, 0.93)"
                @click.self="showLightbox = false"
            >
                <button
                    class="absolute top-4 right-4 rounded-full bg-card p-2 text-foreground backdrop-blur-sm hover:bg-muted dark:bg-slate-800/80 dark:text-white dark:hover:bg-slate-700"
                    @click="showLightbox = false"
                >
                    <X class="h-5 w-5" />
                </button>
                <img
                    :src="snapshotImage"
                    :alt="snapshotAlt"
                    class="max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl ring-1 ring-white/10"
                />
            </div>
        </Transition>
    </Teleport>
</template>
