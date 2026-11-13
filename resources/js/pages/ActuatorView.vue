<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Droplets,
    Sun,
    Zap,
    Clock,
    ToggleLeft,
    ToggleRight,
    History,
    ChevronLeft,
    ChevronRight,
    Settings,
    Lock,
    LockOpen,
    ShieldAlert,
    Droplet,
    RefreshCw,
} from '@lucide/vue';
import axios from 'axios';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import { useSensorStore } from '@/stores/useSensorStore';
import type {
    ActuatorLog,
    ActuatorOverrides,
    HumidifierMist,
    LedSchedule,
    Thresholds,
    Paginated,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Actuator Control', href: '/actuators' }],
    },
});

const props = defineProps<{
    logs?: Paginated<ActuatorLog>;
    ledSchedule: LedSchedule;
    thresholds: Thresholds;
    overrides: ActuatorOverrides;
    mist: HumidifierMist;
}>();

const page = usePage();
const userRole = computed(
    () => (page.props.auth as any)?.user?.role ?? 'student',
);
const canControl = computed(() =>
    ['admin', 'faculty'].includes(userRole.value),
);

const store = useSensorStore();

// ── Logs pagination ──────────────────────────────────────────────────────────
const logsData = ref<Paginated<ActuatorLog> | null>(props.logs ?? null);
const logsLoading = ref(false);
const logsPage = ref(1);

async function fetchLogs(p = 1) {
    logsLoading.value = true;
    logsPage.value = p;

    try {
        const res = await axios.get('/actuators', {
            params: { page: p },
            headers: { 'X-Inertia': true },
        });
        logsData.value = res.data.props?.logs ?? logsData.value;
    } finally {
        logsLoading.value = false;
    }
}

// ── Actuator toggle ──────────────────────────────────────────────────────────
const toggling = ref<Record<string, boolean>>({
    humidifier: false,
    led: false,
});

async function toggleActuator(
    actuator: 'humidifier' | 'led',
    action: 'on' | 'off',
) {
    if (!canControl.value) {
        toast.error(
            'Access denied. Only Admin or Faculty can control actuators.',
        );

        return;
    }

    toggling.value[actuator] = true;

    try {
        await axios.post('/api/actuators/toggle', { actuator, action });
        toast.success(`${actuator} turned ${action}`);
        reloadMist(actuator === 'humidifier');
    } catch {
        toast.error('Failed to toggle actuator.');
    } finally {
        toggling.value[actuator] = false;
    }
}

// ── Mist & water tank ────────────────────────────────────────────────────────
const nowMs = ref(Date.now());
let mistTimer: ReturnType<typeof setInterval>;
let reloadTimer: ReturnType<typeof setInterval>;

onMounted(() => {
    mistTimer = setInterval(() => (nowMs.value = Date.now()), 1000);
    reloadTimer = setInterval(() => reloadMist(), 60000);
});
onUnmounted(() => {
    clearInterval(mistTimer);
    clearInterval(reloadTimer);
});

function reloadMist(includeLogs = false) {
    router.reload({ only: includeLogs ? ['mist', 'logs'] : ['mist'] });
}

watch(
    () => props.logs,
    (fresh) => {
        if (fresh) {
            logsData.value = fresh;
        }
    },
);

// Seconds elapsed since the server computed the summary while the humidifier ran.
const liveSeconds = computed(() =>
    props.mist.running
        ? Math.max(
              0,
              Math.floor((nowMs.value - Date.parse(props.mist.as_of)) / 1000),
          )
        : 0,
);

const rate = computed(() => props.mist.rate_ml_per_second);
const unitRate = computed(() => props.mist.unit_rate_ml_per_second);
const todayMl = computed(
    () => props.mist.today.ml + liveSeconds.value * rate.value,
);
const todaySeconds = computed(
    () => props.mist.today.seconds + liveSeconds.value,
);
// Only drain the tank live once a refill has been logged; otherwise the
// server keeps reporting a full tank and the tick would flicker back to 300.
const tankRemainingMl = computed(() =>
    props.mist.tank.tracking
        ? Math.max(
              0,
              props.mist.tank.remaining_ml - liveSeconds.value * unitRate.value,
          )
        : props.mist.tank.remaining_ml,
);
const tankPercent = computed(() =>
    Math.round((tankRemainingMl.value / props.mist.tank.capacity_ml) * 100),
);
const runtimeLeftSeconds = computed(() =>
    Math.round(tankRemainingMl.value / unitRate.value),
);
const tankLow = computed(
    () => tankPercent.value <= props.mist.tank.low_percent,
);
const weekMaxMl = computed(() =>
    Math.max(1, todayMl.value, ...props.mist.week.map((d) => d.ml)),
);

function formatDuration(totalSeconds: number): string {
    const s = Math.max(0, Math.round(totalSeconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);

    if (h > 0) {
        return `${h}h ${m}m`;
    }

    return m > 0 ? `${m}m ${s % 60}s` : `${s}s`;
}

const refilling = ref(false);

async function refillTank() {
    if (!canControl.value) {
        toast.error('Access denied. Only Admin or Faculty can log a refill.');

        return;
    }

    refilling.value = true;

    try {
        await axios.post('/api/actuators/humidifier/refill');
        toast.success('Tank refill logged.');
        reloadMist();
    } catch {
        toast.error('Failed to log refill.');
    } finally {
        refilling.value = false;
    }
}

// ── Manual Override ──────────────────────────────────────────────────────────
const overrideState = ref<ActuatorOverrides>({ ...props.overrides });
const lockingOverride = ref<Record<string, boolean>>({
    humidifier: false,
    led: false,
});

const anyOverrideActive = computed(() =>
    Object.values(overrideState.value).some(Boolean),
);

const activeOverrideNames = computed(() =>
    (
        Object.entries(overrideState.value) as [
            keyof ActuatorOverrides,
            boolean,
        ][]
    )
        .filter(([, v]) => v)
        .map(([k]) => actuators.find((a) => a.key === k)?.label ?? k)
        .join(', '),
);

async function toggleOverride(actuator: 'humidifier' | 'led') {
    if (!canControl.value) {
        toast.error('Access denied. Only Admin or Faculty can set overrides.');

        return;
    }

    lockingOverride.value[actuator] = true;
    const newActive = !overrideState.value[actuator];

    try {
        await axios.post('/api/actuators/override', {
            actuator,
            active: newActive,
        });
        overrideState.value[actuator] = newActive;

        toast.success(
            newActive
                ? `🔒 Override ON — automation paused for ${actuator}.`
                : `🔓 Override OFF — automation resumed for ${actuator}.`,
        );
    } catch {
        toast.error('Failed to update override.');
    } finally {
        lockingOverride.value[actuator] = false;
    }
}

// ── LED Schedule ─────────────────────────────────────────────────────────────
const schedule = ref({ ...props.ledSchedule });
const savingSchedule = ref(false);

function formatHour(hour: number | string | undefined | null): string {
    const h = Number(hour ?? 0);

    if (isNaN(h)) {
        return '12:00 AM';
    }

    const period = h >= 12 ? 'PM' : 'AM';
    const displayHour = h % 12 === 0 ? 12 : h % 12;

    return `${displayHour}:00 ${period}`;
}

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

// ── Actuator metadata ────────────────────────────────────────────────────────
const actuators = [
    {
        key: 'humidifier' as const,
        label: 'Humidifier',
        relay: 'Relay 1',
        icon: Droplets,
        color: 'blue',
    },
    {
        key: 'led' as const,
        label: 'Grow Lights',
        relay: 'Relay 2',
        icon: Sun,
        color: 'yellow',
    },
];

const colorClasses: Record<string, Record<string, string>> = {
    blue: {
        bg: 'bg-blue-500/10',
        text: 'text-blue-500',
        badge: 'bg-blue-500/20 text-blue-600 dark:text-blue-400',
        ping: 'bg-blue-400',
        dot: 'bg-blue-500',
    },
    yellow: {
        bg: 'bg-yellow-500/10',
        text: 'text-yellow-500',
        badge: 'bg-yellow-500/20 text-yellow-600 dark:text-yellow-400',
        ping: 'bg-yellow-400',
        dot: 'bg-yellow-500',
    },
};

function getActuatorState(key: 'humidifier' | 'led') {
    return store.actuators[key];
}

const triggerLabels: Record<string, string> = {
    auto: 'Auto',
    manual: 'Manual',
    schedule: 'Schedule',
    automatic: 'Auto',
};
</script>

<template>
    <Head title="Actuator Control" />

    <div
        class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10"
    >
        <div
            class="z-10 mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
        >
            <!-- Header -->
            <div>
                <h1
                    class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                >
                    Actuator Control
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Monitor and control relays manually.
                </p>
                <div
                    v-if="!canControl"
                    class="mt-3 flex items-center gap-2 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-2 text-sm text-yellow-600 dark:text-yellow-400"
                >
                    <Zap class="h-4 w-4" />
                    View-only mode — Admin or Faculty role required to control
                    actuators.
                </div>
            </div>

            <!-- Override Active Banner -->
            <div
                v-if="anyOverrideActive"
                class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-5 py-4"
            >
                <ShieldAlert
                    class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500"
                />
                <div>
                    <p
                        class="text-sm font-semibold text-amber-600 dark:text-amber-400"
                    >
                        Manual Override Active
                    </p>
                    <p
                        class="mt-0.5 text-xs text-amber-600/80 dark:text-amber-400/80"
                    >
                        Automation is paused for:
                        <strong>{{ activeOverrideNames }}</strong
                        >. The system will not automatically adjust these relays
                        until the override is removed.
                    </p>
                </div>
            </div>

            <!-- Actuator Status Cards -->
            <div class="grid gap-6 md:grid-cols-2">
                <div
                    v-for="act in actuators"
                    :key="act.key"
                    class="relative overflow-hidden rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md transition-all duration-300 hover:shadow-lg"
                    :class="
                        overrideState[act.key]
                            ? 'border-amber-500/40 shadow-amber-500/5'
                            : ''
                    "
                >
                    <!-- Status dot -->
                    <div class="absolute top-4 right-4 flex items-center gap-2">
                        <!-- Override lock indicator -->
                        <div
                            v-if="overrideState[act.key]"
                            class="flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/15 px-2 py-0.5"
                        >
                            <Lock class="h-3 w-3 text-amber-500" />
                            <span
                                class="text-[10px] font-bold tracking-wider text-amber-500 uppercase"
                                >Override</span
                            >
                        </div>

                        <!-- Live state dot -->
                        <div
                            v-if="getActuatorState(act.key) === 'on'"
                            class="relative flex h-3 w-3"
                        >
                            <span
                                :class="[
                                    'absolute inline-flex h-full w-full animate-ping rounded-full opacity-75',
                                    colorClasses[act.color].ping,
                                ]"
                            ></span>
                            <span
                                :class="[
                                    'relative inline-flex h-3 w-3 rounded-full',
                                    colorClasses[act.color].dot,
                                ]"
                            ></span>
                        </div>
                        <div v-else class="h-3 w-3 rounded-full bg-muted"></div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div
                            :class="[
                                'rounded-lg p-3 shadow-inner',
                                colorClasses[act.color].bg,
                                colorClasses[act.color].text,
                            ]"
                        >
                            <component :is="act.icon" class="h-6 w-6" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-foreground">
                                {{ act.label }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ act.relay }}
                            </p>
                        </div>
                    </div>

                    <!-- Current status badge -->
                    <div class="mt-4">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-bold tracking-wider uppercase"
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
                            :disabled="
                                toggling[act.key] ||
                                getActuatorState(act.key) === 'on'
                            "
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-primary/10 px-3 py-2 text-sm font-medium text-primary transition-all hover:bg-primary/20 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ToggleRight class="h-4 w-4" />
                            Turn ON
                        </button>
                        <button
                            :id="`btn-${act.key}-off`"
                            @click="toggleActuator(act.key, 'off')"
                            :disabled="
                                toggling[act.key] ||
                                getActuatorState(act.key) === 'off'
                            "
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-muted/60 px-3 py-2 text-sm font-medium text-muted-foreground transition-all hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ToggleLeft class="h-4 w-4" />
                            Turn OFF
                        </button>
                    </div>

                    <!-- Override toggle -->
                    <div v-if="canControl" class="mt-3">
                        <button
                            :id="`btn-${act.key}-override`"
                            @click="toggleOverride(act.key)"
                            :disabled="lockingOverride[act.key]"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                overrideState[act.key]
                                    ? 'border-amber-500/50 bg-amber-500/15 text-amber-600 hover:bg-amber-500/25 dark:text-amber-400'
                                    : 'border-border/50 bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                        >
                            <Lock
                                v-if="overrideState[act.key]"
                                class="h-3.5 w-3.5"
                            />
                            <LockOpen v-else class="h-3.5 w-3.5" />
                            {{
                                overrideState[act.key]
                                    ? 'Override Active — Click to Resume Auto'
                                    : 'Lock to Manual'
                            }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Humidifier Mist & Water Tank -->
            <div class="grid gap-6 md:grid-cols-2">
                <div
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner"
                        >
                            <Droplet class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-foreground">
                            Water Tanks ({{ mist.unit_count }} units)
                        </h2>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-foreground">
                            <span>{{ tankRemainingMl.toFixed(1) }}</span>
                            <span
                                class="text-base font-normal text-muted-foreground"
                                >/ {{ mist.tank.capacity_ml }} mL each</span
                            >
                        </p>
                        <p class="text-sm text-muted-foreground">
                            ≈ {{ formatDuration(runtimeLeftSeconds) }} of
                            misting left
                        </p>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="tankLow ? 'bg-red-500' : 'bg-blue-500'"
                            :style="{ width: `${tankPercent}%` }"
                        ></div>
                    </div>
                    <p
                        v-if="tankLow && mist.tank.tracking"
                        class="mt-3 text-sm font-medium text-red-500"
                    >
                        Tank is running low — refill soon.
                    </p>
                    <p
                        v-if="!mist.tank.tracking"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Not tracking yet. Fill all tanks, then press “Log
                        refill”.
                    </p>
                    <p
                        v-else-if="mist.tank.refilled_at"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Last refill:
                        {{
                            new Date(mist.tank.refilled_at).toLocaleString(
                                'en-PH',
                            )
                        }}
                    </p>
                    <button
                        v-if="canControl"
                        id="btn-tank-refill"
                        @click="refillTank"
                        :disabled="refilling"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-500/10 px-3 py-2 text-sm font-medium text-blue-600 transition-all hover:bg-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                    >
                        <RefreshCw class="h-4 w-4" />
                        Log refill (all {{ mist.unit_count }} tanks)
                    </button>
                </div>

                <div
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-blue-500/10 p-2 text-blue-500 shadow-inner"
                        >
                            <Droplets class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-foreground">
                            Mist Output (est.)
                        </h2>
                    </div>
                    <p class="text-3xl font-bold text-foreground">
                        <span>{{ todayMl.toFixed(1) }}</span>
                        <span
                            class="text-base font-normal text-muted-foreground"
                            >mL today</span
                        >
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ formatDuration(todaySeconds) }} on ·
                        {{ mist.unit_count }} × {{ unitRate }} mL/s
                    </p>
                    <!-- 7-Day History Header -->
                    <div
                        class="mt-4 flex items-center justify-between border-t border-border/40 pt-3"
                    >
                        <span class="text-xs font-medium text-muted-foreground"
                            >Last 7 Days Output</span
                        >
                        <span class="text-[11px] text-muted-foreground"
                            >Hover bar for details</span
                        >
                    </div>

                    <div class="mt-2 flex h-28 items-end gap-2">
                        <div
                            v-for="(day, i) in mist.week"
                            :key="day.date"
                            class="group relative flex h-full flex-1 flex-col items-center gap-1"
                        >
                            <!-- Output value above bar -->
                            <span
                                class="text-[10px] font-medium transition-opacity"
                                :class="
                                    (i === 6 ? todayMl : day.ml) > 0
                                        ? i === 6
                                            ? 'font-bold text-blue-500 dark:text-blue-400'
                                            : 'text-foreground/80'
                                        : 'text-muted-foreground/60 opacity-0 group-hover:opacity-100'
                                "
                            >
                                {{ Math.round(i === 6 ? todayMl : day.ml) }}
                            </span>

                            <!-- Bar -->
                            <div class="flex w-full flex-1 items-end">
                                <div
                                    class="w-full rounded-t transition-all"
                                    :class="
                                        i === 6
                                            ? 'bg-blue-500 shadow-sm shadow-blue-500/30'
                                            : 'bg-blue-500/40 group-hover:bg-blue-500/70'
                                    "
                                    :style="{
                                        height: `${Math.max(
                                            5,
                                            ((i === 6 ? todayMl : day.ml) /
                                                weekMaxMl) *
                                                100,
                                        )}%`,
                                    }"
                                    :title="`${day.date} (${i === 6 ? 'Today' : i === 5 ? 'Yesterday' : day.label}): ${(i === 6 ? todayMl : day.ml).toFixed(1)} mL · ${formatDuration(i === 6 ? todaySeconds : day.seconds)}`"
                                ></div>
                            </div>

                            <!-- Day label -->
                            <span
                                class="text-[10px]"
                                :class="
                                    i === 6
                                        ? 'font-bold text-blue-500 dark:text-blue-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ i === 6 ? 'Today' : day.label }}
                            </span>
                        </div>
                    </div>
                    <p class="mt-3 text-[11px] text-muted-foreground">
                        Estimated from relay on-time ×
                        {{ mist.unit_count }} units × {{ unitRate }} mL/s, not
                        measured.
                    </p>
                </div>
            </div>

            <!-- LED Schedule & Thresholds -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- LED Schedule -->
                <div
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-yellow-500/10 p-2 text-yellow-500 shadow-inner"
                        >
                            <Clock class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            LED GROW LIGHT SCHEDULE
                        </h2>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label
                                for="led-on-hour"
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >ON Time</label
                            >
                            <select
                                id="led-on-hour"
                                v-model.number="schedule.on_hour"
                                :disabled="!canControl"
                                class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none disabled:opacity-50 dark:bg-slate-800"
                            >
                                <option
                                    v-for="h in 24"
                                    :key="h - 1"
                                    :value="h - 1"
                                >
                                    {{ formatHour(h - 1) }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                for="led-off-hour"
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >OFF Time</label
                            >
                            <select
                                id="led-off-hour"
                                v-model.number="schedule.off_hour"
                                :disabled="!canControl"
                                class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none disabled:opacity-50 dark:bg-slate-800"
                            >
                                <option
                                    v-for="h in 24"
                                    :key="h - 1"
                                    :value="h - 1"
                                >
                                    {{ formatHour(h - 1) }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Current: ON at {{ formatHour(schedule.on_hour) }} → OFF
                        at {{ formatHour(schedule.off_hour) }} ({{
                            (schedule.off_hour - schedule.on_hour + 24) % 24
                        }}h light)
                    </p>
                    <div
                        v-if="overrideState.led"
                        class="mt-3 flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-600 dark:text-amber-400"
                    >
                        <Lock class="h-3.5 w-3.5 flex-shrink-0" />
                        LED override is active — the schedule is paused.
                    </div>
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
                <div
                    class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner"
                        >
                            <Settings class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            AUTOMATION THRESHOLDS
                        </h2>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >Humidifier ON at</span
                            >
                            <span class="font-semibold text-foreground"
                                >&le; {{ thresholds.humidifier_on_at }}%</span
                            >
                        </div>
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >Humidifier OFF at</span
                            >
                            <span class="font-semibold text-foreground"
                                >&ge; {{ thresholds.humidifier_off_at }}%</span
                            >
                        </div>
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >Humidity Low (SMS alert)</span
                            >
                            <span class="font-semibold text-foreground"
                                >&lt; {{ thresholds.humidity_low }}%</span
                            >
                        </div>
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >Temp Max (SMS alert)</span
                            >
                            <span class="font-semibold text-foreground"
                                >{{ thresholds.temp_max }}°C</span
                            >
                        </div>
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >CO₂ Max (SMS alert)</span
                            >
                            <span class="font-semibold text-foreground"
                                >{{ thresholds.co2_max }} ppm</span
                            >
                        </div>
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >Substrate Warning SMS</span
                            >
                            <span class="font-semibold text-foreground"
                                >&lt; {{ thresholds.soil_warning }}%</span
                            >
                        </div>
                        <div
                            class="flex justify-between rounded-lg bg-muted/30 px-4 py-2"
                        >
                            <span class="text-muted-foreground"
                                >Substrate Critical SMS</span
                            >
                            <span class="font-semibold text-foreground"
                                >&lt; {{ thresholds.soil_critical }}%</span
                            >
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Edit thresholds in Settings → Sensor Thresholds.
                    </p>
                </div>
            </div>

            <!-- Actuator Log History -->
            <div
                class="overflow-hidden rounded-2xl border border-border/50 bg-card/60 shadow-sm backdrop-blur-md"
            >
                <div class="flex items-center gap-2 p-6 pb-4">
                    <div
                        class="rounded-lg bg-indigo-500/10 p-2 text-indigo-500 shadow-inner"
                    >
                        <History class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">
                        ACTUATOR LOG HISTORY
                        <span
                            v-if="logsData"
                            class="ml-2 text-xs text-muted-foreground/60"
                            >({{ logsData.total }} events)</span
                        >
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/30">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Time
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Actuator
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Action
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Trigger
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Triggered By
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Run / Mist (est.)
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!logsData">
                                <td colspan="6" class="px-4 py-8 text-center">
                                    <div class="animate-pulse space-y-2">
                                        <div
                                            class="h-3 w-full rounded bg-muted"
                                        ></div>
                                        <div
                                            class="h-3 w-3/4 rounded bg-muted"
                                        ></div>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="log in logsData.data"
                                :key="log.id"
                                class="border-b border-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{
                                        new Date(
                                            log.triggered_at,
                                        ).toLocaleString('en-PH')
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground capitalize"
                                >
                                    {{ log.actuator }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-md px-2 py-0.5 text-xs font-bold uppercase"
                                        :class="
                                            log.action === 'on'
                                                ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                                                : 'bg-secondary text-muted-foreground'
                                        "
                                    >
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground capitalize"
                                    >
                                        {{
                                            triggerLabels[log.trigger] ??
                                            log.trigger
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ log.triggered_by ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <span v-if="log.duration_seconds != null"
                                        >{{
                                            formatDuration(log.duration_seconds)
                                        }}
                                        · {{ log.mist_ml }} mL</span
                                    >
                                    <span v-else>—</span>
                                </td>
                            </tr>
                            <tr v-if="logsData && !logsData.data.length">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No actuator events logged.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="logsData && logsData.last_page > 1"
                    class="flex items-center justify-between border-t border-border/50 px-6 py-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Page {{ logsData.current_page }} of
                        {{ logsData.last_page }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            id="btn-logs-prev"
                            @click="fetchLogs(logsData!.current_page - 1)"
                            :disabled="logsData.current_page <= 1"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <ChevronLeft class="h-4 w-4" /> Prev
                        </button>
                        <button
                            id="btn-logs-next"
                            @click="fetchLogs(logsData!.current_page + 1)"
                            :disabled="
                                logsData.current_page >= logsData.last_page
                            "
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Next <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
