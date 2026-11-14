<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Sliders,
    MessageSquare,
    Sun,
    Monitor,
    ShieldX,
} from '@lucide/vue';
import axios from 'axios';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import type { SystemSettings as SystemSettingsType } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: '/settings' }],
    },
});

const props = defineProps<{
    settings?: SystemSettingsType;
    thresholdDefaults?: Record<string, number>;
}>();

const page = usePage();
const userRole = computed(
    () => (page.props.auth as any)?.user?.role ?? 'student',
);
const isAdmin = computed(() => userRole.value === 'admin');

// Tabs
type Tab = 'thresholds' | 'sms' | 'schedule' | 'system';
const activeTab = ref<Tab>('thresholds');

const tabs: { id: Tab; label: string; icon: unknown }[] = [
    { id: 'thresholds', label: 'Sensor Thresholds', icon: Sliders },
    { id: 'sms', label: 'SMS Recipients', icon: MessageSquare },
    { id: 'schedule', label: 'LED Schedule', icon: Sun },
    { id: 'system', label: 'System', icon: Monitor },
];

// ---------- Settings Form ----------
const savedSettings = ref<SystemSettingsType>({ ...props.settings });
const settingsForm = ref<SystemSettingsType>({ ...props.settings });
const savingSettings = ref(false);

function thresholdPlaceholder(key: keyof SystemSettingsType): string {
    const current = savedSettings.value?.[key];
    const fallback = props.thresholdDefaults?.[key as string];

    if (current !== undefined && current !== null && current !== '') {
        return String(current);
    }

    return fallback !== undefined ? String(fallback) : '';
}

async function saveSettings() {
    savingSettings.value = true;

    try {
        await axios.put('/api/settings', settingsForm.value);

        for (const [k, v] of Object.entries(settingsForm.value)) {
            if (v !== undefined && v !== null && v !== '') {
                (savedSettings.value as Record<string, unknown>)[k] = v;
            }
        }

        toast.success('Settings saved.');
    } catch {
        toast.error('Failed to save settings.');
    } finally {
        savingSettings.value = false;
    }
}

function inputClass() {
    return 'w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50';
}

function formatHour(hour: number | string | undefined | null): string {
    const h = Number(hour ?? 0);

    if (isNaN(h)) {
        return '12:00 AM';
    }

    const period = h >= 12 ? 'PM' : 'AM';
    const displayHour = h % 12 === 0 ? 12 : h % 12;

    return `${displayHour}:00 ${period}`;
}
</script>

<template>
    <Head title="Settings" />

    <div
        class="relative flex h-full min-h-[calc(100vh-(--spacing(16)))] flex-1 flex-col bg-linear-to-br from-primary/5 via-background to-secondary/10"
    >
        <div
            class="z-10 mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
        >
            <!-- Access Denied -->
            <div
                v-if="!isAdmin"
                class="flex flex-col items-center justify-center py-24 text-center"
            >
                <ShieldX class="mb-4 h-16 w-16 text-destructive opacity-50" />
                <h2 class="text-2xl font-bold text-foreground">
                    Access Denied
                </h2>
                <p class="mt-2 text-muted-foreground">
                    This page is restricted to administrators only.
                </p>
            </div>

            <template v-else>
                <!-- Header -->
                <div>
                    <h1
                        class="bg-linear-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                    >
                        System Settings
                    </h1>
                    <p class="mt-1 text-muted-foreground">
                        Manage users, thresholds, and system configuration.
                    </p>
                </div>

                <!-- Tabs -->
                <div
                    class="flex flex-wrap gap-2 rounded-2xl border border-border/50 bg-card/60 p-2 backdrop-blur-md"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        :id="`tab-${tab.id}`"
                        @click="activeTab = tab.id"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-all"
                        :class="
                            activeTab === tab.id
                                ? 'bg-primary text-primary-foreground shadow-md'
                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                        "
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>

                <!-- ======= SENSOR THRESHOLDS TAB ======= -->
                <div v-if="activeTab === 'thresholds'" class="space-y-6">
                    <!-- Environmental Thresholds -->
                    <div
                        class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                    >
                        <div class="mb-6 flex items-center gap-3">
                            <div
                                class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner"
                            >
                                <Sliders class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-foreground">
                                    ENVIRONMENTAL THRESHOLDS
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Target chamber bounds for humidifier
                                    automation and SMS alerts
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div class="space-y-1">
                                <label
                                    for="threshold-temp-min"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Temp Min (°C)</label
                                >
                                <input
                                    id="threshold-temp-min"
                                    v-model="settingsForm.threshold_temp_min"
                                    type="number"
                                    step="0.1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_temp_min',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-temp-max"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Temp Max (°C)</label
                                >
                                <input
                                    id="threshold-temp-max"
                                    v-model="settingsForm.threshold_temp_max"
                                    type="number"
                                    step="0.1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_temp_max',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-hum-low"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Humidity Low — Humidifier ON (%)</label
                                >
                                <input
                                    id="threshold-hum-low"
                                    v-model="
                                        settingsForm.threshold_humidity_low
                                    "
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_humidity_low',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-hum-high"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Humidity High — Humidifier OFF (%)</label
                                >
                                <input
                                    id="threshold-hum-high"
                                    v-model="
                                        settingsForm.threshold_humidity_high
                                    "
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_humidity_high',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-co2"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >CO₂ Max — SMS Alert (ppm)</label
                                >
                                <input
                                    id="threshold-co2"
                                    v-model="settingsForm.threshold_co2_max"
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_co2_max',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-light-min"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Light Min — Indirect Light (lux)</label
                                >
                                <input
                                    id="threshold-light-min"
                                    v-model="settingsForm.threshold_light_min"
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_light_min',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-light-max"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Light Max — Too Bright (lux)</label
                                >
                                <input
                                    id="threshold-light-max"
                                    v-model="settingsForm.threshold_light_max"
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_light_max',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-soil-warn"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Substrate Moisture Warning (%)</label
                                >
                                <input
                                    id="threshold-soil-warn"
                                    v-model="
                                        settingsForm.threshold_soil_warning
                                    "
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_soil_warning',
                                        )
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="threshold-soil-crit"
                                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >Substrate Moisture Critical (%)</label
                                >
                                <input
                                    id="threshold-soil-crit"
                                    v-model="
                                        settingsForm.threshold_soil_critical
                                    "
                                    type="number"
                                    step="1"
                                    :class="inputClass()"
                                    :placeholder="
                                        thresholdPlaceholder(
                                            'threshold_soil_critical',
                                        )
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <button
                        id="btn-save-thresholds"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Thresholds' }}
                    </button>
                </div>

                <!-- ======= SMS RECIPIENTS TAB ======= -->
                <div
                    v-if="activeTab === 'sms'"
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-6 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner"
                        >
                            <MessageSquare class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            SMS RECIPIENT NUMBERS
                        </h2>
                    </div>
                    <div class="space-y-1">
                        <label
                            for="sms-recipients"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Recipient Numbers (comma-separated)
                        </label>
                        <textarea
                            id="sms-recipients"
                            v-model="settingsForm.sms_recipients"
                            rows="4"
                            :class="inputClass()"
                            placeholder="09123456789,09987654321"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">
                            Enter numbers like 09123456789, separated by commas
                            (+63 format is also accepted and converted). Uses
                            Semaphore API.
                        </p>
                    </div>
                    <button
                        id="btn-save-sms"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Recipients' }}
                    </button>
                </div>

                <!-- ======= LED SCHEDULE TAB ======= -->
                <div
                    v-if="activeTab === 'schedule'"
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-6 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner"
                        >
                            <Sun class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-muted-foreground">
                                LED GROW LIGHT SCHEDULE
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Daily automated schedule for the LED grow light
                                relay.
                            </p>
                        </div>
                    </div>
                    <div class="grid max-w-md gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label
                                for="sched-on"
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >ON Time</label
                            >
                            <select
                                id="sched-on"
                                v-model="settingsForm.led_on_hour"
                                :class="inputClass()"
                            >
                                <option
                                    v-for="h in 24"
                                    :key="h - 1"
                                    :value="String(h - 1)"
                                >
                                    {{ formatHour(h - 1) }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                for="sched-off"
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >OFF Time</label
                            >
                            <select
                                id="sched-off"
                                v-model="settingsForm.led_off_hour"
                                :class="inputClass()"
                            >
                                <option
                                    v-for="h in 24"
                                    :key="h - 1"
                                    :value="String(h - 1)"
                                >
                                    {{ formatHour(h - 1) }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Lights ON at
                        {{ formatHour(settingsForm.led_on_hour ?? 6) }} and OFF
                        at
                        {{ formatHour(settingsForm.led_off_hour ?? 18) }} daily.
                    </p>
                    <button
                        id="btn-save-schedule-settings"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Schedule' }}
                    </button>
                </div>

                <!-- ======= SYSTEM TAB ======= -->
                <div
                    v-if="activeTab === 'system'"
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-6 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-secondary/60 p-2 text-muted-foreground shadow-inner"
                        >
                            <Monitor class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            SYSTEM CONFIGURATION
                        </h2>
                    </div>
                    <div class="max-w-md space-y-4">
                        <div class="space-y-1">
                            <label
                                for="system-name"
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >System Name</label
                            >
                            <input
                                id="system-name"
                                v-model="settingsForm.system_name"
                                type="text"
                                :class="inputClass()"
                                placeholder="IoT Mushroom Monitoring System"
                            />
                        </div>
                    </div>
                    <button
                        id="btn-save-system"
                        @click="saveSettings"
                        :disabled="savingSettings"
                        class="mt-6 rounded-lg bg-primary px-6 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSettings ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
