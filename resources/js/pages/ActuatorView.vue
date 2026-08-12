<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Droplets,
    Wind,
    Sun,
    Zap,
    Clock,
    ToggleLeft,
    ToggleRight,
    History,
    ChevronLeft,
    ChevronRight,
    Settings,
} from '@lucide/vue';
import axios from 'axios';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { useSensorStore } from '@/stores/useSensorStore';
import type { ActuatorLog, LedSchedule, Thresholds, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Actuator Control', href: '/actuators' }],
    },
});

const props = defineProps<{
    logs?: Paginated<ActuatorLog>;
    ledSchedule: LedSchedule;
    thresholds: Thresholds;
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');
const canControl = computed(() => ['admin', 'faculty'].includes(userRole.value));

const store = useSensorStore();

// Logs pagination
const logsData = ref<Paginated<ActuatorLog> | null>(props.logs ?? null);
const logsLoading = ref(false);
const logsPage = ref(1);

async function fetchLogs(p = 1) {
    logsLoading.value = true;
    logsPage.value = p;

    try {
        const res = await axios.get('/actuators', { params: { page: p }, headers: { 'X-Inertia': true } });
        logsData.value = res.data.props?.logs ?? logsData.value;
    } finally {
        logsLoading.value = false;
    }
}

// Actuator toggle
const toggling = ref<Record<string, boolean>>({ humidifier: false, fan: false, led: false });

async function toggleActuator(actuator: 'humidifier' | 'fan' | 'led', action: 'on' | 'off') {
    if (!canControl.value) {
        toast.error('Access denied. Only Admin or Faculty can control actuators.');

        return;
    }

    toggling.value[actuator] = true;

    try {
        await axios.post('/api/actuators/toggle', { actuator, action });
        toast.success(`${actuator} turned ${action}`);
    } catch {
        toast.error('Failed to toggle actuator.');
    } finally {
        toggling.value[actuator] = false;
    }
}

// LED Schedule edit
const schedule = ref({ ...props.ledSchedule });
const savingSchedule = ref(false);

async function saveSchedule() {
    savingSchedule.value = true;

    try {
        await axios.put('/api/actuators/schedule', schedule.value);
        toast.success('LED schedule updated.');
    } catch {
        toast.error('Failed to update schedule.');
    } finally {
        savingSchedule.value = false;
    }
}

// Actuator metadata
const actuators = [
    { key: 'humidifier' as const, label: 'Humidifier', relay: 'Relay 1', icon: Droplets, color: 'blue' },
    { key: 'fan' as const, label: 'Intake Fan', relay: 'Relay 3', icon: Wind, color: 'purple' },
    { key: 'led' as const, label: 'Grow Lights', relay: 'Relay 2', icon: Sun, color: 'yellow' },
];

const colorClasses: Record<string, Record<string, string>> = {
    blue: { bg: 'bg-blue-500/10', text: 'text-blue-500', badge: 'bg-blue-500/20 text-blue-600 dark:text-blue-400', ping: 'bg-blue-400', dot: 'bg-blue-500' },
    purple: { bg: 'bg-purple-500/10', text: 'text-purple-500', badge: 'bg-purple-500/20 text-purple-600 dark:text-purple-400', ping: 'bg-purple-400', dot: 'bg-purple-500' },
    yellow: { bg: 'bg-yellow-500/10', text: 'text-yellow-500', badge: 'bg-yellow-500/20 text-yellow-600 dark:text-yellow-400', ping: 'bg-yellow-400', dot: 'bg-yellow-500' },
};

function getActuatorState(key: 'humidifier' | 'fan' | 'led') {
    return store.actuators[key];
}

const triggerLabels: Record<string, string> = { auto: 'Auto', manual: 'Manual', schedule: 'Schedule' };
const actionLabels: Record<string, string> = { on: 'ON', off: 'OFF' };
</script>

<template>
    <Head title="Actuator Control" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Header -->
            <div>
                <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                    Actuator Control
                </h1>
                <p class="mt-1 text-muted-foreground">Monitor and control relays manually.</p>
                <div v-if="!canControl" class="mt-3 flex items-center gap-2 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-2 text-sm text-yellow-600 dark:text-yellow-400">
                    <Zap class="h-4 w-4" />
                    View-only mode — Admin or Faculty role required to control actuators.
                </div>
            </div>

            <!-- Actuator Status Cards -->
            <div class="grid gap-6 md:grid-cols-3">
                <div
                    v-for="act in actuators"
                    :key="act.key"
                    class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm transition-all duration-300 hover:shadow-lg"
                >
                    <!-- Status dot -->
                    <div class="absolute top-4 right-4">
                        <div v-if="getActuatorState(act.key) === 'on'" class="relative flex h-3 w-3">
                            <span :class="['absolute inline-flex h-full w-full animate-ping rounded-full opacity-75', colorClasses[act.color].ping]"></span>
                            <span :class="['relative inline-flex h-3 w-3 rounded-full', colorClasses[act.color].dot]"></span>
                        </div>
                        <div v-else class="h-3 w-3 rounded-full bg-muted"></div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div :class="['rounded-lg p-3 shadow-inner', colorClasses[act.color].bg, colorClasses[act.color].text]">
                            <component :is="act.icon" class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-foreground">{{ act.label }}</h3>
                            <p class="text-xs text-muted-foreground">{{ act.relay }}</p>
                        </div>
                    </div>

                    <!-- Current status badge -->
                    <div class="mt-4">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-bold uppercase tracking-wider"
                            :class="
                                getActuatorState(act.key) === 'on'
                                    ? colorClasses[act.color].badge
                                    : 'bg-secondary text-muted-foreground'
                            "
                        >
                            {{ getActuatorState(act.key) }}
                        </span>
                    </div>

                    <!-- Toggle buttons -->
                    <div v-if="canControl" class="mt-5 flex gap-3">
                        <button
                            :id="`btn-${act.key}-on`"
                            @click="toggleActuator(act.key, 'on')"
                            :disabled="toggling[act.key] || getActuatorState(act.key) === 'on'"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary/10 px-3 py-2 text-sm font-medium text-primary transition-all hover:bg-primary/20 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ToggleRight class="h-4 w-4" />
                            Turn ON
                        </button>
                        <button
                            :id="`btn-${act.key}-off`"
                            @click="toggleActuator(act.key, 'off')"
                            :disabled="toggling[act.key] || getActuatorState(act.key) === 'off'"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-muted/60 px-3 py-2 text-sm font-medium text-muted-foreground transition-all hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ToggleLeft class="h-4 w-4" />
                            Turn OFF
                        </button>
                    </div>
                </div>
            </div>

            <!-- LED Schedule & Thresholds -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- LED Schedule -->
                <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner">
                            <Clock class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">LED GROW LIGHT SCHEDULE</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label for="led-on-hour" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">ON Hour (0–23)</label>
                            <input
                                id="led-on-hour"
                                v-model.number="schedule.on_hour"
                                type="number"
                                min="0"
                                max="23"
                                :disabled="!canControl"
                                class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:opacity-50"
                            />
                        </div>
                        <div class="space-y-1">
                            <label for="led-off-hour" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">OFF Hour (0–23)</label>
                            <input
                                id="led-off-hour"
                                v-model.number="schedule.off_hour"
                                type="number"
                                min="0"
                                max="23"
                                :disabled="!canControl"
                                class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:opacity-50"
                            />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Current: ON at {{ schedule.on_hour }}:00 → OFF at {{ schedule.off_hour }}:00 ({{ (schedule.off_hour - schedule.on_hour + 24) % 24 }}h light)
                    </p>
                    <button
                        v-if="canControl"
                        id="btn-save-schedule"
                        @click="saveSchedule"
                        :disabled="savingSchedule"
                        class="mt-4 w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ savingSchedule ? 'Saving...' : 'Save Schedule' }}
                    </button>
                </div>

                <!-- Thresholds (read-only display) -->
                <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner">
                            <Settings class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">AUTOMATION THRESHOLDS</h2>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between rounded-lg bg-muted/30 px-4 py-2">
                            <span class="text-muted-foreground">Humidity Low (humidifier ON)</span>
                            <span class="font-semibold text-foreground">{{ thresholds.humidity_low }}%</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-muted/30 px-4 py-2">
                            <span class="text-muted-foreground">Humidity High (humidifier OFF)</span>
                            <span class="font-semibold text-foreground">{{ thresholds.humidity_high }}%</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-muted/30 px-4 py-2">
                            <span class="text-muted-foreground">Temp Max (intake fan ON)</span>
                            <span class="font-semibold text-foreground">{{ thresholds.temp_max }}°C</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-muted/30 px-4 py-2">
                            <span class="text-muted-foreground">CO₂ Max (intake fan ON)</span>
                            <span class="font-semibold text-foreground">{{ thresholds.co2_max }} ppm</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-muted/30 px-4 py-2">
                            <span class="text-muted-foreground">Soil Warning SMS</span>
                            <span class="font-semibold text-foreground">&lt; {{ thresholds.soil_warning }}%</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-muted/30 px-4 py-2">
                            <span class="text-muted-foreground">Soil Critical SMS</span>
                            <span class="font-semibold text-foreground">&lt; {{ thresholds.soil_critical }}%</span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">Edit thresholds in Settings → Sensor Thresholds.</p>
                </div>
            </div>

            <!-- Actuator Log History -->
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md shadow-sm overflow-hidden">
                <div class="p-6 pb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-indigo-500/10 p-2 text-indigo-500 shadow-inner">
                        <History class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">
                        ACTUATOR LOG HISTORY
                        <span v-if="logsData" class="ml-2 text-xs text-muted-foreground/60">({{ logsData.total }} events)</span>
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/30">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actuator</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Trigger</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Triggered By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!logsData">
                                <td colspan="5" class="px-4 py-8 text-center">
                                    <div class="animate-pulse space-y-2">
                                        <div class="h-3 w-full rounded bg-muted"></div>
                                        <div class="h-3 w-3/4 rounded bg-muted"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="log in logsData.data"
                                :key="log.id"
                                class="border-b border-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 text-muted-foreground text-xs">{{ new Date(log.triggered_at).toLocaleString('en-PH') }}</td>
                                <td class="px-4 py-3 font-medium capitalize text-foreground">{{ log.actuator }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-md px-2 py-0.5 text-xs font-bold uppercase"
                                        :class="log.action === 'on' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-secondary text-muted-foreground'"
                                    >
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium capitalize text-muted-foreground">
                                        {{ triggerLabels[log.trigger] ?? log.trigger }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ log.triggered_by ?? '—' }}</td>
                            </tr>
                            <tr v-if="logsData && !logsData.data.length">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No actuator events logged.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logsData && logsData.last_page > 1" class="flex items-center justify-between border-t border-border/50 px-6 py-4">
                    <p class="text-sm text-muted-foreground">Page {{ logsData.current_page }} of {{ logsData.last_page }}</p>
                    <div class="flex items-center gap-2">
                        <button id="btn-logs-prev" @click="fetchLogs(logsData!.current_page - 1)" :disabled="logsData.current_page <= 1"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed">
                            <ChevronLeft class="h-4 w-4" /> Prev
                        </button>
                        <button id="btn-logs-next" @click="fetchLogs(logsData!.current_page + 1)" :disabled="logsData.current_page >= logsData.last_page"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed">
                            Next <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
