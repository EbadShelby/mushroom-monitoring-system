<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, Filter, BarChart2, AlertTriangle } from '@lucide/vue';
import type { ApexOptions } from 'apexcharts';
import { ref, computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import PaginationBar from '@/components/PaginationBar.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AlertLog, GrowingCycle, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Alert Logs', href: '/alerts' }],
    },
});

const props = defineProps<{
    logs?: Paginated<AlertLog>;
    chartData?: { sensor: string; count: number }[];
    cycles?: Pick<GrowingCycle, 'id' | 'name' | 'status'>[];
    filters?: {
        cycle_id?: string;
        sensor?: string;
        from?: string;
        to?: string;
    };
}>();

const logsData = ref<Paginated<AlertLog> | null>(props.logs ?? null);
const chartData = ref(props.chartData ?? []);
const isLoading = ref(false);
const selectedAlert = ref<AlertLog | null>(null);

watch(
    () => props.logs,
    (val) => {
        if (val) {
            logsData.value = val;
        }
    },
    { immediate: true },
);

watch(
    () => props.chartData,
    (val) => {
        if (val) {
            chartData.value = val;
        }
    },
    { immediate: true },
);

// Filters
const cycleFilter = ref(props.filters?.cycle_id ?? 'all');
const sensorFilter = ref(props.filters?.sensor ?? '');
const fromDate = ref(props.filters?.from ?? '');
const toDate = ref(props.filters?.to ?? '');
const currentPage = ref(props.logs?.current_page ?? 1);

const sensorOptions = [
    { value: '', label: 'All Sensors' },
    { value: 'temperature', label: 'Temperature' },
    { value: 'humidity', label: 'Humidity' },
    { value: 'co2', label: 'CO₂' },
    { value: 'light', label: 'Light' },
    { value: 'soil_moisture', label: 'Substrate Moisture' },
    { value: 'water_tank', label: 'Water Tank' },
];

function fetchLogs(page = 1) {
    isLoading.value = true;
    currentPage.value = page;

    router.get(
        '/alerts',
        {
            page,
            cycle_id: cycleFilter.value || 'all',
            ...(sensorFilter.value ? { sensor: sensorFilter.value } : {}),
            ...(fromDate.value ? { from: fromDate.value } : {}),
            ...(toDate.value ? { to: toDate.value } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}

function applyFilters() {
    fetchLogs(1);
}

// Chart options
const chartOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'bar' as const,
        height: 200,
        toolbar: { show: false },
        background: 'transparent',
    },
    colors: ['#f97316', '#3b82f6', '#a855f7', '#eab308', '#22c55e'],
    plotOptions: { bar: { borderRadius: 6, distributed: true } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: chartData.value.map((d) => sensorLabel[d.sensor] ?? d.sensor),
        labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
        axisBorder: { show: false },
    },
    yaxis: { labels: { style: { colors: '#94a3b8' } } },
    grid: { borderColor: '#1e293b', strokeDashArray: 4 },
    legend: { show: false },
    tooltip: { theme: 'dark', y: { formatter: (v: number) => `${v} alerts` } },
}));

const chartSeries = computed(() => [
    { name: 'Alert Count', data: chartData.value.map((d) => d.count) },
]);

function statusClass(status: string) {
    return status === 'sent'
        ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
        : 'bg-destructive/20 text-destructive';
}

const sensorLabel: Record<string, string> = {
    temperature: 'Temperature',
    humidity: 'Humidity',
    co2: 'CO₂',
    co2_raw: 'CO₂',
    light: 'Light',
    light_level: 'Light',
    soil_moisture: 'Substrate',
    water_tank: 'Water Tank',
};

function formatAlertValue(sensor: string, value: number | string | null): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    switch (sensor) {
        case 'temperature':
            return `${value}°C`;
        case 'humidity':
        case 'soil_moisture':
            return `${value}%`;
        case 'co2':
        case 'co2_raw':
            return `${value} ppm`;
        case 'light':
        case 'light_level':
            return `${value} lux`;
        case 'water_tank':
            return `${value} mL`;
        default:
            return String(value);
    }
}
</script>

<template>
    <Head title="Alert Logs" />

    <div
        class="relative flex h-full min-h-[calc(100vh-(--spacing(16)))] flex-1 flex-col bg-linear-to-br from-primary/5 via-background to-secondary/10"
    >
        <div
            class="z-10 mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
        >
            <!-- Header -->
            <div>
                <h1
                    class="bg-linear-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                >
                    Alert Logs
                </h1>
                <p class="mt-1 text-muted-foreground">
                    SMS alert history and frequency analysis.
                </p>
            </div>

            <!-- Alert Frequency Chart -->
            <div
                class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
            >
                <div
                    class="mb-4 flex flex-wrap items-baseline justify-between gap-2"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-destructive/10 p-2 text-destructive shadow-inner"
                        >
                            <BarChart2 class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            ALERT FREQUENCY BY SENSOR
                        </h2>
                    </div>
                    <span class="text-xs text-muted-foreground">
                        Counted at most once every 15 mins per sensor
                    </span>
                </div>
                <div
                    v-if="!chartData.length"
                    class="flex h-32 items-center justify-center text-muted-foreground"
                >
                    <div class="text-center">
                        <Bell class="mx-auto mb-2 h-8 w-8 opacity-30" />
                        <p class="text-sm">No alerts recorded yet.</p>
                    </div>
                </div>
                <VueApexCharts
                    v-else
                    type="bar"
                    height="200"
                    :options="chartOptions"
                    :series="chartSeries"
                />
            </div>

            <!-- Filters -->
            <div
                class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
            >
                <div class="mb-4 flex items-center gap-2">
                    <div
                        class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner"
                    >
                        <Filter class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">Filters</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="space-y-1">
                        <label
                            for="alert-cycle-filter"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Growing Cycle</label
                        >
                        <select
                            id="alert-cycle-filter"
                            v-model="cycleFilter"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        >
                            <option value="all">All Cycles</option>
                            <option
                                v-for="c in cycles"
                                :key="c.id"
                                :value="String(c.id)"
                            >
                                {{ c.name
                                }}{{ c.status === 'active' ? ' (Active)' : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            for="alert-sensor-filter"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Sensor</label
                        >
                        <select
                            id="alert-sensor-filter"
                            v-model="sensorFilter"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        >
                            <option
                                v-for="opt in sensorOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            for="alert-from"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >From</label
                        >
                        <input
                            id="alert-from"
                            v-model="fromDate"
                            type="date"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            for="alert-to"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >To</label
                        >
                        <input
                            id="alert-to"
                            v-model="toDate"
                            type="date"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            id="btn-alert-filter"
                            @click="applyFilters"
                            class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                        >
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alert Log Table -->
            <div
                class="overflow-hidden rounded-2xl border border-border/50 bg-card/60 shadow-sm backdrop-blur-md"
            >
                <div class="flex items-center gap-2 p-6 pb-4">
                    <div
                        class="rounded-lg bg-red-500/10 p-2 text-red-500 shadow-inner"
                    >
                        <AlertTriangle class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">
                        SMS ALERT HISTORY
                        <span
                            v-if="logsData"
                            class="ml-2 text-xs text-muted-foreground/60"
                            >({{ logsData.total }} alerts)</span
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
                                    Sent At
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Sensor
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Value
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Threshold
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Message
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!logsData">
                                <td colspan="6" class="px-4 py-8 text-center">
                                    <div class="animate-pulse space-y-2">
                                        <div
                                            class="mx-auto h-3 w-full max-w-md rounded bg-muted"
                                        ></div>
                                        <div
                                            class="mx-auto h-3 w-3/4 max-w-sm rounded bg-muted"
                                        ></div>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="log in logsData.data"
                                :key="log.id"
                                tabindex="0"
                                @click="selectedAlert = log"
                                @keydown.enter="selectedAlert = log"
                                @keydown.space.prevent="selectedAlert = log"
                                class="cursor-pointer border-b border-border/30 transition-colors hover:bg-muted/30 focus:bg-muted/30 focus:outline-none"
                            >
                                <td
                                    class="px-4 py-3 text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{
                                        new Date(log.sent_at).toLocaleString(
                                            'en-PH',
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ sensorLabel[log.sensor] ?? log.sensor }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono text-foreground"
                                >
                                    {{
                                        formatAlertValue(
                                            log.sensor,
                                            log.value_at_alert,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-muted-foreground capitalize"
                                >
                                    {{ log.threshold_exceeded }}
                                </td>
                                <td
                                    class="max-w-xs truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ log.message }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-bold capitalize"
                                        :class="statusClass(log.status)"
                                    >
                                        {{ log.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="logsData && !logsData.data.length">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No alerts found for selected filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <PaginationBar
                    v-if="logsData"
                    :current-page="logsData.current_page"
                    :last-page="logsData.last_page"
                    :total="logsData.total"
                    unit="alerts"
                    :loading="isLoading"
                    @change="fetchLogs"
                />
            </div>
        </div>

        <Dialog
            :open="selectedAlert !== null"
            @update:open="
                (open) => {
                    if (!open) selectedAlert = null;
                }
            "
        >
            <DialogContent v-if="selectedAlert" class="sm:max-w-lg">
                <DialogHeader>
                    <div class="flex items-center gap-2 pr-6">
                        <DialogTitle>
                            {{
                                sensorLabel[selectedAlert.sensor] ??
                                selectedAlert.sensor
                            }}
                            Alert
                        </DialogTitle>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-bold capitalize"
                            :class="statusClass(selectedAlert.status)"
                        >
                            {{ selectedAlert.status }}
                        </span>
                    </div>
                    <DialogDescription>
                        {{
                            new Date(selectedAlert.sent_at).toLocaleString(
                                'en-PH',
                            )
                        }}
                        <span v-if="selectedAlert.growing_cycle">
                            · {{ selectedAlert.growing_cycle.name }}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        class="rounded-lg border border-border/50 bg-muted/40 p-4 text-sm leading-relaxed wrap-break-word whitespace-pre-wrap text-foreground"
                    >
                        {{ selectedAlert.message }}
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div class="rounded-lg border border-border/40 p-2.5">
                            <p class="text-muted-foreground">Value</p>
                            <p
                                class="mt-0.5 font-mono font-semibold text-foreground"
                            >
                                {{
                                    formatAlertValue(
                                        selectedAlert.sensor,
                                        selectedAlert.value_at_alert,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-border/40 p-2.5">
                            <p class="text-muted-foreground">Threshold</p>
                            <p
                                class="mt-0.5 font-semibold text-foreground capitalize"
                            >
                                {{ selectedAlert.threshold_exceeded }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-border/40 p-2.5">
                            <p class="text-muted-foreground">Recipient</p>
                            <p
                                class="mt-0.5 truncate font-mono font-semibold text-foreground"
                            >
                                {{ selectedAlert.recipient_number || '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
