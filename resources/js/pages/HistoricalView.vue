<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Download, Filter, BarChart2, X } from '@lucide/vue';
import type { ApexOptions } from 'apexcharts';
import axios from 'axios';
import { ref, computed, onMounted } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import PaginationBar from '@/components/PaginationBar.vue';
import {
    chart as historicalChart,
    data as historicalData,
    exportMethod as historicalExport,
} from '@/routes/historical';
import type { SensorReading, HistoricalChartPoint, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Historical Data', href: '/historical' }],
    },
});

type ChartInterval = '1m' | '5m' | '15m' | '1h' | '1d';
type SensorKey =
    'temperature' | 'humidity' | 'co2_raw' | 'light_level' | 'soil_moisture';

// Filters
const fromDate = ref('');
const toDate = ref('');
const appliedFrom = ref('');
const appliedTo = ref('');
const chartInterval = ref<ChartInterval>('5m');
const selectedSensors = ref<SensorKey[]>([
    'temperature',
    'humidity',
    'co2_raw',
    'light_level',
    'soil_moisture',
]);

const sensorOptions: { key: SensorKey; label: string; color: string }[] = [
    { key: 'temperature', label: 'Temperature (°C)', color: '#f97316' },
    { key: 'humidity', label: 'Humidity (%)', color: '#3b82f6' },
    { key: 'co2_raw', label: 'CO₂ (ppm)', color: '#a855f7' },
    { key: 'light_level', label: 'Light (lux)', color: '#eab308' },
    { key: 'soil_moisture', label: 'Substrate Moisture (%)', color: '#22c55e' },
];

const hasAppliedDateRange = computed(() =>
    Boolean(appliedFrom.value || appliedTo.value),
);

const hasAnyDateInput = computed(() =>
    Boolean(
        fromDate.value || toDate.value || appliedFrom.value || appliedTo.value,
    ),
);

const intervalOptions = computed(() => [
    {
        value: '1m' as const,
        label: hasAppliedDateRange.value
            ? 'Per 1 Min'
            : 'Per 1 Min (Last Hour)',
    },
    {
        value: '5m' as const,
        label: hasAppliedDateRange.value
            ? 'Per 5 Mins'
            : 'Per 5 Mins (Last 6 Hrs)',
    },
    {
        value: '15m' as const,
        label: hasAppliedDateRange.value
            ? 'Per 15 Mins'
            : 'Per 15 Mins (Last 24 Hrs)',
    },
    {
        value: '1h' as const,
        label: hasAppliedDateRange.value
            ? 'Per 1 Hour'
            : 'Per 1 Hour (Last 7 Days)',
    },
    {
        value: '1d' as const,
        label: hasAppliedDateRange.value
            ? 'Per 1 Day'
            : 'Per 1 Day (Last 30 Days)',
    },
]);

const chartIntervalLabel = computed(() => {
    const windowMap: Record<ChartInterval, string> = {
        '1m': 'Last Hour',
        '5m': 'Last 6 Hours',
        '15m': 'Last 24 Hours',
        '1h': 'Last 7 Days',
        '1d': 'Last 30 Days',
    };

    const bucketMap: Record<ChartInterval, string> = {
        '1m': 'Per 1 Min',
        '5m': 'Per 5 Mins',
        '15m': 'Per 15 Mins',
        '1h': 'Per 1 Hour',
        '1d': 'Per 1 Day',
    };

    if (!hasAppliedDateRange.value) {
        return windowMap[chartInterval.value];
    }

    const range =
        appliedFrom.value && appliedTo.value
            ? `${appliedFrom.value} to ${appliedTo.value}`
            : appliedFrom.value
              ? `From ${appliedFrom.value}`
              : `Until ${appliedTo.value}`;

    return `${range} · ${bucketMap[chartInterval.value]}`;
});

// Data
const readings = ref<Paginated<SensorReading> | null>(null);
const chartPoints = ref<HistoricalChartPoint[]>([]);
const isLoading = ref(false);
const isChartLoading = ref(false);
const currentPage = ref(1);
const perPage = ref(50);

async function fetchData() {
    isLoading.value = true;

    try {
        const params: Record<string, unknown> = {
            page: currentPage.value,
            per_page: perPage.value,
            sensors: selectedSensors.value,
        };

        if (appliedFrom.value) {
            params.from = appliedFrom.value;
        }

        if (appliedTo.value) {
            params.to = appliedTo.value;
        }

        const response = await axios.get(historicalData.url(), { params });
        readings.value = response.data;
    } finally {
        isLoading.value = false;
    }
}

async function fetchChartData() {
    isChartLoading.value = true;

    try {
        const params: Record<string, unknown> = {
            interval: chartInterval.value,
        };

        if (appliedFrom.value) {
            params.from = appliedFrom.value;
        }

        if (appliedTo.value) {
            params.to = appliedTo.value;
        }

        const response = await axios.get<HistoricalChartPoint[]>(
            historicalChart.url(),
            { params },
        );
        chartPoints.value = response.data;
    } finally {
        isChartLoading.value = false;
    }
}

function goToPage(page: number) {
    currentPage.value = page;
    fetchData();
}

function onPerPageChange() {
    currentPage.value = 1;
    fetchData();
}

function onIntervalChange() {
    fetchChartData();
}

function applyFilters() {
    appliedFrom.value = fromDate.value;
    appliedTo.value = toDate.value;
    currentPage.value = 1;
    void Promise.all([fetchData(), fetchChartData()]);
}

function clearDates() {
    fromDate.value = '';
    toDate.value = '';
    appliedFrom.value = '';
    appliedTo.value = '';
    currentPage.value = 1;
    void Promise.all([fetchData(), fetchChartData()]);
}

function exportCsv() {
    const params = new URLSearchParams();

    if (fromDate.value) {
        params.set('from', fromDate.value);
    }

    if (toDate.value) {
        params.set('to', toDate.value);
    }

    selectedSensors.value.forEach((s) => params.append('sensors[]', s));
    window.location.href = `${historicalExport.url()}?${params.toString()}`;
}

// Charts
const chartSeries = computed(() => {
    if (!chartPoints.value.length) {
        return [];
    }

    return selectedSensors.value.map((key) => {
        const opt = sensorOptions.find((s) => s.key === key)!;

        return {
            name: opt.label,
            data: chartPoints.value.map((p) => ({
                x: new Date(p.time).getTime(),
                y: p[key] ?? null,
            })),
        };
    });
});

const chartOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'line' as const,
        height: 320,
        toolbar: { show: false },
        animations: { enabled: false },
        background: 'transparent',
        zoom: { enabled: true },
    },
    colors: selectedSensors.value.map(
        (key) => sensorOptions.find((s) => s.key === key)?.color ?? '#94a3b8',
    ),
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    xaxis: {
        type: 'datetime',
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
    },
    yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
    grid: { borderColor: '#1e293b', strokeDashArray: 4 },
    legend: { labels: { colors: '#94a3b8' } },
    tooltip: {
        theme: 'dark',
        x: {
            format:
                chartInterval.value === '1d'
                    ? 'dd MMM yyyy'
                    : 'dd MMM, h:mm TT',
        },
    },
}));

// Column header map
const columnLabels: Record<string, string> = {
    recorded_at: 'Time',
    temperature: 'Temp (°C)',
    humidity: 'Humidity (%)',
    co2_raw: 'CO₂ (ppm)',
    light_level: 'Light (lux)',
    soil_moisture: 'Substrate (%)',
};

// Initial load
onMounted(() => {
    void Promise.all([fetchData(), fetchChartData()]);
});
</script>

<template>
    <Head title="Historical Data" />

    <div
        class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10"
    >
        <div
            class="z-10 mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
        >
            <!-- Header -->
            <div
                class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <h1
                        class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                    >
                        Historical Data
                    </h1>
                    <p class="mt-1 text-muted-foreground">
                        Analyze sensor trends over time.
                    </p>
                </div>
                <button
                    id="btn-export-csv"
                    @click="exportCsv"
                    class="flex items-center gap-2 self-start rounded-xl border border-border/50 bg-card/60 px-4 py-2 text-sm font-medium text-muted-foreground backdrop-blur-md transition-all hover:border-primary/30 hover:bg-primary/10 hover:text-primary md:self-auto"
                >
                    <Download class="h-4 w-4" />
                    Export CSV
                </button>
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
                <div class="grid gap-4 sm:grid-cols-3">
                    <!-- Date From -->
                    <div class="space-y-1">
                        <label
                            for="filter-from"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >From</label
                        >
                        <input
                            id="filter-from"
                            v-model="fromDate"
                            type="date"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                    <!-- Date To -->
                    <div class="space-y-1">
                        <label
                            for="filter-to"
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >To</label
                        >
                        <input
                            id="filter-to"
                            v-model="toDate"
                            type="date"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                    <!-- Apply Button -->
                    <div class="flex items-end gap-2">
                        <button
                            id="btn-apply-filters"
                            @click="applyFilters"
                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                        >
                            Apply Filters
                        </button>
                        <button
                            v-if="hasAnyDateInput"
                            id="btn-clear-dates"
                            type="button"
                            @click="clearDates"
                            title="Clear date range"
                            class="inline-flex items-center justify-center gap-1 rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm font-medium text-muted-foreground transition-all hover:border-border hover:bg-muted/50 hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                            Clear
                        </button>
                    </div>
                </div>
                <!-- Sensor Selector -->
                <div class="mt-4 flex flex-wrap gap-3">
                    <span
                        class="self-center text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >Sensors:</span
                    >
                    <label
                        v-for="opt in sensorOptions"
                        :key="opt.key"
                        class="flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium transition-all"
                        :class="
                            selectedSensors.includes(opt.key)
                                ? 'border-transparent text-white shadow-sm'
                                : 'border-border/50 bg-card/40 text-muted-foreground hover:bg-muted/50'
                        "
                        :style="
                            selectedSensors.includes(opt.key)
                                ? `background-color: ${opt.color}33; color: ${opt.color}; border-color: ${opt.color}66`
                                : ''
                        "
                    >
                        <input
                            type="checkbox"
                            :value="opt.key"
                            v-model="selectedSensors"
                            class="sr-only"
                        />
                        {{ opt.label }}
                    </label>
                </div>
            </div>

            <!-- Chart -->
            <div
                class="rounded-2xl border border-border/50 bg-card/60 p-6 shadow-sm backdrop-blur-md"
            >
                <div
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner"
                        >
                            <BarChart2 class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            Sensor Trends — {{ chartIntervalLabel }}
                        </h2>
                    </div>
                    <select
                        id="historical-chart-interval"
                        v-model="chartInterval"
                        @change="onIntervalChange"
                        class="w-full rounded-md border border-border/50 bg-card/60 px-3 py-1.5 text-sm shadow-sm backdrop-blur-md focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none sm:w-auto dark:bg-card/40"
                    >
                        <option
                            v-for="opt in intervalOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div v-if="isChartLoading" class="animate-pulse">
                    <div class="h-80 w-full rounded-lg bg-muted"></div>
                </div>
                <div
                    v-else-if="!chartPoints.length || !selectedSensors.length"
                    class="flex h-48 items-center justify-center text-muted-foreground"
                >
                    <div class="text-center">
                        <Activity class="mx-auto mb-2 h-10 w-10 opacity-30" />
                        <p>No data for selected filters.</p>
                    </div>
                </div>
                <VueApexCharts
                    v-else
                    type="line"
                    height="320"
                    :options="chartOptions"
                    :series="chartSeries"
                />
            </div>

            <!-- Data Table -->
            <div
                class="overflow-hidden rounded-2xl border border-border/50 bg-card/60 shadow-sm backdrop-blur-md"
            >
                <div class="flex items-center justify-between p-6 pb-4">
                    <div class="flex items-center gap-2">
                        <Activity class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-muted-foreground">
                            Raw Data
                            <span
                                v-if="readings"
                                class="ml-2 text-xs text-muted-foreground/60"
                                >({{ readings.total }} records)</span
                            >
                        </h2>
                    </div>
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
                                    v-for="key in selectedSensors"
                                    :key="key"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{ columnLabels[key] }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td
                                    :colspan="selectedSensors.length + 1"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    <div
                                        class="flex items-center justify-center gap-2"
                                    >
                                        <div
                                            class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"
                                        ></div>
                                        Loading...
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-else
                                v-for="row in readings?.data"
                                :key="row.id"
                                class="border-b border-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                                >
                                    {{
                                        new Date(
                                            row.recorded_at,
                                        ).toLocaleString('en-PH')
                                    }}
                                </td>
                                <td
                                    v-for="key in selectedSensors"
                                    :key="key"
                                    class="px-4 py-3 text-right font-mono text-foreground"
                                >
                                    {{ row[key as keyof SensorReading] ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="!isLoading && !readings?.data.length">
                                <td
                                    :colspan="selectedSensors.length + 1"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No records found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="readings"
                    class="flex items-center justify-between gap-3 border-t border-border/50 px-4 py-3 sm:px-6"
                >
                    <label
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        Rows per page
                        <select
                            v-model.number="perPage"
                            class="h-8 rounded-lg border border-border/50 bg-background px-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            @change="onPerPageChange"
                        >
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                            <option :value="200">200</option>
                        </select>
                    </label>
                </div>
                <PaginationBar
                    v-if="readings"
                    :current-page="readings.current_page"
                    :last-page="readings.last_page"
                    :total="readings.total"
                    :loading="isLoading"
                    @change="goToPage"
                />
            </div>
        </div>
    </div>
</template>
