<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { dashboard } from '@/routes';
import { useSensorStore } from '@/stores/useSensorStore';
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

const store = useSensorStore();

onMounted(() => store.startListening());
onUnmounted(() => store.stopListening());
</script>

<template>
    <Head title="Live Monitoring" />

    <div
        class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
    >
        <!-- Header & Connection Status -->
        <div
            class="flex flex-col justify-between gap-4 md:flex-row md:items-end"
        >
            <div>
                <h1
                    class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                >
                    Mushroom Cultivation
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Live environmental metrics and actuator control.
                </p>
            </div>

            <div
                class="flex items-center gap-3 rounded-full border px-4 py-2 text-sm font-medium shadow-sm transition-all duration-300"
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

        <!-- Sensor Grid -->
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Temperature -->
            <div
                class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:shadow-lg"
                :class="
                    store.temperatureStatus === 'warning'
                        ? 'border-destructive/50 bg-destructive/5'
                        : 'border-border/50 bg-card'
                "
            >
                <div
                    class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                >
                    <Thermometer class="h-32 w-32" stroke-width="1.5" />
                </div>

                <div class="relative z-10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-orange-500/10 p-2 text-orange-500"
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
                    <p class="text-5xl font-bold tracking-tighter">
                        <span v-if="store.sensors.temperature !== null">{{
                            store.sensors.temperature
                        }}</span>
                        <span v-else class="text-muted-foreground/50">--</span>
                    </p>
                    <span class="text-xl font-medium text-muted-foreground"
                        >°C</span
                    >
                </div>

                <div
                    class="relative z-10 mt-4 flex items-center justify-between text-sm"
                >
                    <span class="text-muted-foreground">Target: 24-30°C</span>
                    <div
                        v-if="store.temperatureStatus === 'warning'"
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
                class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:shadow-lg"
                :class="
                    store.humidityStatus === 'warning'
                        ? 'border-destructive/50 bg-destructive/5'
                        : 'border-border/50 bg-card'
                "
            >
                <div
                    class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                >
                    <Droplets class="h-32 w-32" stroke-width="1.5" />
                </div>

                <div class="relative z-10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-blue-500/10 p-2 text-blue-500"
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
                    <p class="text-5xl font-bold tracking-tighter">
                        <span v-if="store.sensors.humidity !== null">{{
                            store.sensors.humidity
                        }}</span>
                        <span v-else class="text-muted-foreground/50">--</span>
                    </p>
                    <span class="text-xl font-medium text-muted-foreground"
                        >%</span
                    >
                </div>

                <div
                    class="relative z-10 mt-4 flex items-center justify-between text-sm"
                >
                    <span class="text-muted-foreground">Target: 80-95%</span>
                    <div
                        v-if="store.humidityStatus === 'warning'"
                        class="flex animate-pulse items-center gap-1 font-medium text-destructive"
                    >
                        <AlertCircle class="h-4 w-4" /> Low
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
                class="group relative overflow-hidden rounded-2xl border p-6 transition-all duration-300 hover:shadow-lg"
                :class="
                    store.co2Status === 'warning'
                        ? 'border-destructive/50 bg-destructive/5'
                        : 'border-border/50 bg-card'
                "
            >
                <div
                    class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                >
                    <Wind class="h-32 w-32" stroke-width="1.5" />
                </div>

                <div class="relative z-10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-purple-500/10 p-2 text-purple-500"
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
                    <p class="text-5xl font-bold tracking-tighter">
                        <span v-if="store.sensors.co2_raw !== null">{{
                            store.sensors.co2_raw
                        }}</span>
                        <span v-else class="text-muted-foreground/50">--</span>
                    </p>
                    <span class="text-xl font-medium text-muted-foreground"
                        >ppm</span
                    >
                </div>

                <div
                    class="relative z-10 mt-4 flex items-center justify-between text-sm"
                >
                    <span class="text-muted-foreground">Target: &lt; 1000</span>
                    <div
                        v-if="store.co2Status === 'warning'"
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
                class="group relative overflow-hidden rounded-2xl border border-border/50 bg-card p-6 transition-all duration-300 hover:shadow-lg"
            >
                <div
                    class="absolute -top-6 -right-6 text-foreground/5 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-45"
                >
                    <Sun class="h-32 w-32" stroke-width="1.5" />
                </div>

                <div class="relative z-10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500"
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
                    <p class="text-5xl font-bold tracking-tighter">
                        <span v-if="store.sensors.light_level !== null">{{
                            store.sensors.light_level
                        }}</span>
                        <span v-else class="text-muted-foreground/50">--</span>
                    </p>
                    <span class="text-xl font-medium text-muted-foreground"
                        >lux</span
                    >
                </div>

                <div
                    class="relative z-10 mt-4 flex items-center justify-between text-sm"
                >
                    <span class="text-muted-foreground"
                        >Fruiting: 50-1000 lux</span
                    >
                </div>
            </div>
        </div>

        <!-- Bottom Section: Soil & Actuators -->
        <div class="grid gap-6 md:grid-cols-3">
            <!-- Soil Moisture -->
            <div
                class="relative overflow-hidden rounded-2xl border border-border/50 bg-card p-6 shadow-sm md:col-span-1"
            >
                <div class="mb-6 flex items-center gap-2">
                    <div
                        class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500"
                    >
                        <Sprout class="h-5 w-5" />
                    </div>
                    <h3
                        class="font-semibold tracking-wide text-muted-foreground"
                    >
                        SOIL MOISTURE
                    </h3>
                </div>

                <div class="flex flex-col items-center justify-center py-6">
                    <div
                        class="relative flex h-40 w-40 items-center justify-center rounded-full border-4 border-muted"
                    >
                        <div
                            class="absolute inset-0 rounded-full border-4 border-emerald-500"
                            :style="`clip-path: inset(${100 - (store.sensors.soil_moisture ?? 0)}% 0 0 0)`"
                        ></div>
                        <div class="text-center">
                            <p class="text-4xl font-bold tracking-tighter">
                                <span
                                    v-if="store.sensors.soil_moisture !== null"
                                    >{{ store.sensors.soil_moisture }}</span
                                >
                                <span v-else class="text-muted-foreground/50"
                                    >--</span
                                ><span class="text-xl text-muted-foreground"
                                    >%</span
                                >
                            </p>
                        </div>
                    </div>
                    <div
                        class="mt-6 flex items-center gap-2 rounded-full bg-emerald-500/10 px-4 py-2 font-medium text-emerald-600 capitalize dark:text-emerald-400"
                    >
                        <Activity class="h-4 w-4" />
                        {{ store.sensors.soil_status ?? 'Unknown' }}
                    </div>
                </div>
            </div>

            <!-- Actuators Panel -->
            <div
                class="relative overflow-hidden rounded-2xl border border-border/50 bg-card p-6 shadow-sm md:col-span-2"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-indigo-500/10 p-2 text-indigo-500"
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
                        class="rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-secondary-foreground"
                        >Automated</span
                    >
                </div>

                <div class="grid h-[calc(100%-4rem)] gap-4 sm:grid-cols-3">
                    <!-- Humidifier Actuator -->
                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/50 p-4 transition-colors hover:bg-muted/50"
                    >
                        <div class="flex items-start justify-between">
                            <div
                                class="rounded-lg bg-blue-500/10 p-2.5 text-blue-500"
                            >
                                <Droplets class="h-6 w-6" />
                            </div>
                            <div
                                class="relative flex h-3 w-3"
                                v-if="store.actuators.humidifier === 'on'"
                            >
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-3 w-3 rounded-full bg-blue-500"
                                ></span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h4 class="text-lg font-semibold">Humidifier</h4>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm text-muted-foreground"
                                    >Relay 1</span
                                >
                                <span
                                    class="rounded-md px-2 py-1 text-xs font-bold tracking-wider uppercase"
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
                    </div>

                    <!-- Fan Actuator -->
                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/50 p-4 transition-colors hover:bg-muted/50"
                    >
                        <div class="flex items-start justify-between">
                            <div
                                class="rounded-lg bg-purple-500/10 p-2.5 text-purple-500"
                            >
                                <Wind class="h-6 w-6" />
                            </div>
                            <div
                                class="relative flex h-3 w-3"
                                v-if="store.actuators.fan === 'on'"
                            >
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-purple-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-3 w-3 rounded-full bg-purple-500"
                                ></span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h4 class="text-lg font-semibold">Cooling Fan</h4>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm text-muted-foreground"
                                    >Relay 3</span
                                >
                                <span
                                    class="rounded-md px-2 py-1 text-xs font-bold tracking-wider uppercase"
                                    :class="
                                        store.actuators.fan === 'on'
                                            ? 'bg-purple-500/20 text-purple-600 dark:text-purple-400'
                                            : 'bg-secondary text-muted-foreground'
                                    "
                                >
                                    {{ store.actuators.fan }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- LED Actuator -->
                    <div
                        class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/50 p-4 transition-colors hover:bg-muted/50"
                    >
                        <div class="flex items-start justify-between">
                            <div
                                class="rounded-lg bg-yellow-500/10 p-2.5 text-yellow-500"
                            >
                                <Sun class="h-6 w-6" />
                            </div>
                            <div
                                class="relative flex h-3 w-3"
                                v-if="store.actuators.led === 'on'"
                            >
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-yellow-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-3 w-3 rounded-full bg-yellow-500"
                                ></span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <h4 class="text-lg font-semibold">Grow Lights</h4>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm text-muted-foreground"
                                    >Relay 2</span
                                >
                                <span
                                    class="rounded-md px-2 py-1 text-xs font-bold tracking-wider uppercase"
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
            </div>
        </div>
    </div>
</template>
