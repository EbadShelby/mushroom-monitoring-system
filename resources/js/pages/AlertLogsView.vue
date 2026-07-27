<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import type { AlertLog, Paginated } from '@/types';
import {
    Bell,
    Filter,
    ChevronLeft,
    ChevronRight,
    BarChart2,
    AlertTriangle,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Alert Logs', href: '/alerts' }],
    },
});

const props = defineProps<{
    logs?: Paginated<AlertLog>;
    chartData?: { sensor: string; count: number }[];
}>();

const logsData = ref<Paginated<AlertLog> | null>(props.logs ?? null);
const chartData = ref(props.chartData ?? []);
const isLoading = ref(false);

// Filters
const sensorFilter = ref('');
const fromDate = ref('');
const toDate = ref('');
const currentPage = ref(1);

const sensorOptions = [
    { value: '', label: 'All Sensors' },
    { value: 'temperature', label: 'Temperature' },
    { value: 'humidity', label: 'Humidity' },
    { value: 'co2_raw', label: 'CO₂' },
    { value: 'light_level', label: 'Light' },
    { value: 'soil_moisture', label: 'Soil Moisture' },
];

async function fetchLogs(page = 1) {
    isLoading.value = true;
    currentPage.value = page;
    try {
        const params: Record<string, unknown> = { page };
        if (sensorFilter.value) params.sensor = sensorFilter.value;
        if (fromDate.value) params.from = fromDate.value;
        if (toDate.value) params.to = toDate.value;

        const [logsRes, chartRes] = await Promise.all([
            axios.get('/api/alert-logs/chart', { params }),
            // We re-use the chart endpoint to get frequency data
        ]);

        // For paginated logs we just pass the filter params via URL
        const logsResponse = await axios.get('/alerts', {
            params,
            headers: { 'X-Inertia': true, 'X-Inertia-Partial-Data': 'logs', 'X-Inertia-Partial-Component': 'AlertLogsView' },
        });
        if (logsResponse.data?.props?.logs) {
            logsData.value = logsResponse.data.props.logs;
        }
        chartData.value = logsRes.data;
    } finally {
        isLoading.value = false;
    }
}

function applyFilters() {
    fetchLogs(1);
}

// Chart options
const chartOptions = computed(() => ({
    chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
    colors: ['#f97316', '#3b82f6', '#a855f7', '#eab308', '#22c55e'],
    plotOptions: { bar: { borderRadius: 6, distributed: true } },
    dataLabels: { enabled: false },
    xaxis: {
        categories: chartData.value.map((d) => d.sensor),
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
    co2_raw: 'CO₂',
    light_level: 'Light',
    soil_moisture: 'Soil',
};
</script>

<template>
    <Head title="Alert Logs" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Header -->
            <div>
                <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                    Alert Logs
                </h1>
                <p class="mt-1 text-muted-foreground">SMS alert history and frequency analysis.</p>
            </div>

            <!-- Alert Frequency Chart -->
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-destructive/10 p-2 text-destructive shadow-inner">
                        <BarChart2 class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">ALERT FREQUENCY BY SENSOR</h2>
                </div>
                <div v-if="!chartData.length" class="flex h-32 items-center justify-center text-muted-foreground">
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
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner">
                        <Filter class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">Filters</h2>
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-1">
                        <label for="alert-sensor-filter" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Sensor</label>
                        <select
                            id="alert-sensor-filter"
                            v-model="sensorFilter"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50"
                        >
                            <option v-for="opt in sensorOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label for="alert-from" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">From</label>
                        <input id="alert-from" v-model="fromDate" type="date" class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50" />
                    </div>
                    <div class="space-y-1">
                        <label for="alert-to" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">To</label>
                        <input id="alert-to" v-model="toDate" type="date" class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50" />
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
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md shadow-sm overflow-hidden">
                <div class="p-6 pb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-red-500/10 p-2 text-red-500 shadow-inner">
                        <AlertTriangle class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">
                        SMS ALERT HISTORY
                        <span v-if="logsData" class="ml-2 text-xs text-muted-foreground/60">({{ logsData.total }} alerts)</span>
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/30">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Sent At</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Sensor</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Value</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Threshold</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Message</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!logsData">
                                <td colspan="6" class="px-4 py-8 text-center">
                                    <div class="animate-pulse space-y-2">
                                        <div class="h-3 w-full rounded bg-muted mx-auto max-w-md"></div>
                                        <div class="h-3 w-3/4 rounded bg-muted mx-auto max-w-sm"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="log in logsData.data"
                                :key="log.id"
                                class="border-b border-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ new Date(log.sent_at).toLocaleString('en-PH') }}</td>
                                <td class="px-4 py-3 font-medium text-foreground">{{ sensorLabel[log.sensor] ?? log.sensor }}</td>
                                <td class="px-4 py-3 text-right font-mono text-foreground">{{ log.value_at_alert }}</td>
                                <td class="px-4 py-3 text-muted-foreground capitalize">{{ log.threshold_exceeded }}</td>
                                <td class="px-4 py-3 max-w-xs text-muted-foreground truncate">{{ log.message }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(log.status)">
                                        {{ log.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="logsData && !logsData.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No alerts found for selected filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logsData && logsData.last_page > 1" class="flex items-center justify-between border-t border-border/50 px-6 py-4">
                    <p class="text-sm text-muted-foreground">Page {{ logsData.current_page }} of {{ logsData.last_page }}</p>
                    <div class="flex items-center gap-2">
                        <button id="btn-alert-prev" @click="fetchLogs(logsData!.current_page - 1)" :disabled="logsData.current_page <= 1"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed">
                            <ChevronLeft class="h-4 w-4" /> Prev
                        </button>
                        <button id="btn-alert-next" @click="fetchLogs(logsData!.current_page + 1)" :disabled="logsData.current_page >= logsData.last_page"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed">
                            Next <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
