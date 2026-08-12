<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Sliders,
    MessageSquare,
    Sun,
    Monitor,
    ShieldX,
    Microscope,
    Layers,
} from '@lucide/vue';
import axios from 'axios';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import type { AppUser, SystemSettings as SystemSettingsType } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: '/settings' }],
    },
});

const props = defineProps<{
    settings?: SystemSettingsType;
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');
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
const settingsForm = ref<SystemSettingsType>({ ...props.settings });
const savingSettings = ref(false);

async function saveSettings() {
    savingSettings.value = true;

    try {
        await axios.put('/api/settings', settingsForm.value);
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
</script>

<template>
    <Head title="Settings" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Access Denied -->
            <div v-if="!isAdmin" class="flex flex-col items-center justify-center py-24 text-center">
                <ShieldX class="mb-4 h-16 w-16 text-destructive opacity-50" />
                <h2 class="text-2xl font-bold text-foreground">Access Denied</h2>
                <p class="mt-2 text-muted-foreground">This page is restricted to administrators only.</p>
            </div>

            <template v-else>
                <!-- Header -->
                <div>
                    <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                        System Settings
                    </h1>
                    <p class="mt-1 text-muted-foreground">Manage users, thresholds, and system configuration.</p>
                </div>

                <!-- Tabs -->
                <div class="flex flex-wrap gap-2 rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-2">
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

                    <!-- Colonization Stage Thresholds -->
                    <div class="rounded-2xl border border-amber-500/20 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="rounded-lg bg-amber-500/10 p-2 text-amber-500 shadow-inner">
                                <Microscope class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-amber-400">COLONIZATION STAGE THRESHOLDS</h2>
                                <p class="text-xs text-muted-foreground">Mycelium spawn running phase — warm, dark, high CO₂ tolerated</p>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div class="space-y-1">
                                <label for="col-temp-min" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Temp Min (°C)</label>
                                <input id="col-temp-min" v-model="settingsForm.threshold_col_temp_min" type="number" step="0.1" :class="inputClass()" placeholder="24" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-temp-max" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Temp Max (°C)</label>
                                <input id="col-temp-max" v-model="settingsForm.threshold_col_temp_max" type="number" step="0.1" :class="inputClass()" placeholder="28" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-hum-low" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Humidity Low — Humidifier ON (%)</label>
                                <input id="col-hum-low" v-model="settingsForm.threshold_col_humidity_low" type="number" step="1" :class="inputClass()" placeholder="70" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-hum-high" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Humidity High — Humidifier OFF (%)</label>
                                <input id="col-hum-high" v-model="settingsForm.threshold_col_humidity_high" type="number" step="1" :class="inputClass()" placeholder="80" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-co2" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">CO₂ Max — Fan ON (ppm)</label>
                                <input id="col-co2" v-model="settingsForm.threshold_col_co2_max" type="number" step="1" :class="inputClass()" placeholder="5000" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-light-max" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Light Max — Keep Dark (lux)</label>
                                <input id="col-light-max" v-model="settingsForm.threshold_col_light_max" type="number" step="1" :class="inputClass()" placeholder="100" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-soil-warn" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Substrate Moisture Warning (%)</label>
                                <input id="col-soil-warn" v-model="settingsForm.threshold_col_soil_warning" type="number" step="1" :class="inputClass()" placeholder="55" />
                            </div>
                            <div class="space-y-1">
                                <label for="col-soil-crit" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Substrate Moisture Critical (%)</label>
                                <input id="col-soil-crit" v-model="settingsForm.threshold_col_soil_critical" type="number" step="1" :class="inputClass()" placeholder="50" />
                            </div>
                        </div>
                    </div>

                    <!-- Fruiting Stage Thresholds -->
                    <div class="rounded-2xl border border-purple-500/20 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                        <div class="mb-6 flex items-center gap-3">
                            <div class="rounded-lg bg-purple-500/10 p-2 text-purple-500 shadow-inner">
                                <Layers class="h-4 w-4" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-purple-400">FRUITING STAGE THRESHOLDS</h2>
                                <p class="text-xs text-muted-foreground">Mushroom formation phase — cooler, high humidity, fresh air, indirect light</p>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div class="space-y-1">
                                <label for="fruit-temp-min" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Temp Min (°C)</label>
                                <input id="fruit-temp-min" v-model="settingsForm.threshold_fruit_temp_min" type="number" step="0.1" :class="inputClass()" placeholder="20" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-temp-max" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Temp Max (°C)</label>
                                <input id="fruit-temp-max" v-model="settingsForm.threshold_fruit_temp_max" type="number" step="0.1" :class="inputClass()" placeholder="24" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-hum-low" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Humidity Low — Humidifier ON (%)</label>
                                <input id="fruit-hum-low" v-model="settingsForm.threshold_fruit_humidity_low" type="number" step="1" :class="inputClass()" placeholder="85" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-hum-high" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Humidity High — Humidifier OFF (%)</label>
                                <input id="fruit-hum-high" v-model="settingsForm.threshold_fruit_humidity_high" type="number" step="1" :class="inputClass()" placeholder="95" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-co2" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">CO₂ Max — Fan ON (ppm)</label>
                                <input id="fruit-co2" v-model="settingsForm.threshold_fruit_co2_max" type="number" step="1" :class="inputClass()" placeholder="1000" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-light-min" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Light Min — Needs Indirect Light (lux)</label>
                                <input id="fruit-light-min" v-model="settingsForm.threshold_fruit_light_min" type="number" step="1" :class="inputClass()" placeholder="200" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-light-max" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Light Max — Too Bright (lux)</label>
                                <input id="fruit-light-max" v-model="settingsForm.threshold_fruit_light_max" type="number" step="1" :class="inputClass()" placeholder="800" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-soil-warn" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Substrate Moisture Warning (%)</label>
                                <input id="fruit-soil-warn" v-model="settingsForm.threshold_fruit_soil_warning" type="number" step="1" :class="inputClass()" placeholder="55" />
                            </div>
                            <div class="space-y-1">
                                <label for="fruit-soil-crit" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Substrate Moisture Critical (%)</label>
                                <input id="fruit-soil-crit" v-model="settingsForm.threshold_fruit_soil_critical" type="number" step="1" :class="inputClass()" placeholder="50" />
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
                <div v-if="activeTab === 'sms'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner">
                            <MessageSquare class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">SMS RECIPIENT NUMBERS</h2>
                    </div>
                    <div class="space-y-1">
                        <label for="sms-recipients" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Recipient Numbers (comma-separated)
                        </label>
                        <textarea
                            id="sms-recipients"
                            v-model="settingsForm.sms_recipients"
                            rows="4"
                            :class="inputClass()"
                            placeholder="+639123456789,+639987654321"
                        ></textarea>
                        <p class="text-xs text-muted-foreground">Enter numbers in E.164 format, separated by commas. Uses Semaphore API.</p>
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
                <div v-if="activeTab === 'schedule'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner">
                            <Sun class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="font-semibold text-muted-foreground">LED GROW LIGHT SCHEDULE</h2>
                            <p class="text-xs text-muted-foreground">LED runs on schedule during Fruiting stage only. Colonization keeps lights off.</p>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 max-w-md">
                        <div class="space-y-1">
                            <label for="sched-on" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">ON Hour (0–23)</label>
                            <input id="sched-on" v-model="settingsForm.led_on_hour" type="number" min="0" max="23" :class="inputClass()" placeholder="6" />
                        </div>
                        <div class="space-y-1">
                            <label for="sched-off" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">OFF Hour (0–23)</label>
                            <input id="sched-off" v-model="settingsForm.led_off_hour" type="number" min="0" max="23" :class="inputClass()" placeholder="18" />
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Lights ON at {{ settingsForm.led_on_hour ?? 6 }}:00 and OFF at {{ settingsForm.led_off_hour ?? 18 }}:00 daily (for fruiting photoperiod).
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
                <div v-if="activeTab === 'system'" class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-6 flex items-center gap-2">
                        <div class="rounded-lg bg-secondary/60 p-2 text-muted-foreground shadow-inner">
                            <Monitor class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">SYSTEM CONFIGURATION</h2>
                    </div>
                    <div class="max-w-md space-y-4">
                        <div class="space-y-1">
                            <label for="system-name" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">System Name</label>
                            <input id="system-name" v-model="settingsForm.system_name" type="text" :class="inputClass()" placeholder="IoT Mushroom Monitoring System" />
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
