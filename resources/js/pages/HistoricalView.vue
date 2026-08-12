<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    Download,
    Filter,
    ChevronLeft,
    ChevronRight,
    BarChart2,
} from '@lucide/vue';
import axios from 'axios';
import { ref, computed, watch, onMounted } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import type { SensorReading, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Historical Data', href: '/historical' }],
    },
});

// Filters
const fromDate = ref('');
const toDate = ref('');
const selectedSensors = ref<string[]>(['temperature', 'humidity', 'co2_raw', 'light_level', 'soil_moisture']);

const sensorOptions = [
    { key: 'temperature', label: 'Temperature (°C)', color: '#f97316' },
    { key: 'humidity', label: 'Humidity (%)', color: '#3b82f6' },
    { key: 'co2_raw', label: 'CO₂ (ppm)', color: '#a855f7' },
    { key: 'light_level', label: 'Light (lux)', color: '#eab308' },
    { key: 'soil_moisture', label: 'Soil Moisture (%)', color: '#22c55e' },
];

// Data
const readings = ref<Paginated<SensorReading> | null>(null);
const isLoading = ref(false);
const currentPage = ref(1);

async function fetchData() {
    isLoading.value = true;

    try {
        const params: Record<string, unknown> = {
            page: currentPage.value,
            per_page: 50,
            sensors: selectedSensors.value,
        };

        if (fromDate.value) {
params.from = fromDate.value;
}

        if (toDate.value) {
params.to = toDate.value;
}

        const response = await axios.get('/api/historical', { params });
        readings.value = response.data;
    } finally {
        isLoading.value = false;
    }
}

function goToPage(page: number) {
    currentPage.value = page;
    fetchData();
}

function applyFilters() {
    currentPage.value = 1;
    fetchData();
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
    window.location.href = `/api/historical/export?${params.toString()}`;
}

// Charts
const chartSeries = computed(() => {
    if (!readings.value?.data.length) {
return [];
}

    return selectedSensors.value.map((key) => {
        const opt = sensorOptions.find((s) => s.key === key)!;

        return {
            name: opt.label,
            data: readings.value!.data.slice().reverse().map((r) => ({
                x: new Date(r.recorded_at).getTime(),
                y: r[key as keyof SensorReading] as number | null,
            })),
        };
    });
});

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 320,
        toolbar: { show: false },
        animations: { enabled: false },
        background: 'transparent',
        zoom: { enabled: true },
    },
    colors: selectedSensors.value.map((key) => sensorOptions.find((s) => s.key === key)?.color ?? '#94a3b8'),
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    xaxis: {
        type: 'datetime',
        labels: { style: { colors: '#94a3b8', fontSize: '10px' }, datetimeUTC: false },
        axisBorder: { show: false },
    },
    yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '10px' } } },
    grid: { borderColor: '#1e293b', strokeDashArray: 4 },
    legend: { labels: { colors: '#94a3b8' } },
    tooltip: { theme: 'dark', x: { format: 'dd MMM HH:mm' } },
}));

// Column header map
const columnLabels: Record<string, string> = {
    recorded_at: 'Time',
    temperature: 'Temp (°C)',
    humidity: 'Humidity (%)',
    co2_raw: 'CO₂ (ppm)',
    light_level: 'Light (lux)',
    soil_moisture: 'Soil (%)',
};

// Initial load
onMounted(() => {
    fetchData();
});
</script>

<template>
    <Head title="Historical Data" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                        Historical Data
                    </h1>
                    <p class="mt-1 text-muted-foreground">Analyze sensor trends over time.</p>
                </div>
                <button
                    id="btn-export-csv"
                    @click="exportCsv"
                    class="flex items-center gap-2 rounded-xl border border-border/50 bg-card/60 px-4 py-2 text-sm font-medium text-muted-foreground backdrop-blur-md transition-all hover:bg-primary/10 hover:text-primary hover:border-primary/30"
                >
                    <Download class="h-4 w-4" />
                    Export CSV
                </button>
            </div>

            <!-- Filters -->
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner">
                        <Filter class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">Filters</h2>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <!-- Date From -->
                    <div class="space-y-1">
                        <label for="filter-from" class="text-xs font-medium text-muted-foreground uppercase tracking-wider">From</label>
                        <input
                            id="filter-from"
                            v-model="fromDate"
                            type="date"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50"
                        />
                    </div>
                    <!-- Date To -->
                    <div class="space-y-1">
                        <label for="filter-to" class="text-xs font-medium text-muted-foreground uppercase tracking-wider">To</label>
                        <input
                            id="filter-to"
                            v-model="toDate"
                            type="date"
                            class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50"
                        />
                    </div>
                    <!-- Apply Button -->
                    <div class="flex items-end">
                        <button
                            id="btn-apply-filters"
                            @click="applyFilters"
                            class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                        >
                            Apply Filters
                        </button>
                    </div>
                </div>
                <!-- Sensor Selector -->
                <div class="mt-4 flex flex-wrap gap-3">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider self-center">Sensors:</span>
                    <label
                        v-for="opt in sensorOptions"
                        :key="opt.key"
                        class="flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium transition-all"
                        :class="
                            selectedSensors.includes(opt.key)
                                ? 'border-transparent text-white shadow-sm'
                                : 'border-border/50 bg-card/40 text-muted-foreground hover:bg-muted/50'
                        "
                        :style="selectedSensors.includes(opt.key) ? `background-color: ${opt.color}33; color: ${opt.color}; border-color: ${opt.color}66` : ''"
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
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary shadow-inner">
                        <BarChart2 class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">Sensor Trends</h2>
                </div>
                <div v-if="isLoading" class="animate-pulse">
                    <div class="h-80 w-full rounded-lg bg-muted"></div>
                </div>
                <div v-else-if="!readings?.data.length" class="flex h-48 items-center justify-center text-muted-foreground">
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
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md shadow-sm overflow-hidden">
                <div class="p-6 pb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Activity class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-muted-foreground">
                            Raw Data
                            <span v-if="readings" class="ml-2 text-xs text-muted-foreground/60">({{ readings.total }} records)</span>
                        </h2>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/30">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Time</th>
                                <th v-for="key in selectedSensors" :key="key" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    {{ columnLabels[key] }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td :colspan="selectedSensors.length + 1" class="px-4 py-8 text-center text-muted-foreground">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
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
                                <td class="px-4 py-3 text-muted-foreground">{{ new Date(row.recorded_at).toLocaleString('en-PH') }}</td>
                                <td v-for="key in selectedSensors" :key="key" class="px-4 py-3 text-right font-mono text-foreground">
                                    {{ row[key as keyof SensorReading] ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="!isLoading && !readings?.data.length">
                                <td :colspan="selectedSensors.length + 1" class="px-4 py-8 text-center text-muted-foreground">No records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="readings && readings.last_page > 1" class="flex items-center justify-between border-t border-border/50 px-6 py-4">
                    <p class="text-sm text-muted-foreground">
                        Page {{ readings.current_page }} of {{ readings.last_page }} · {{ readings.total }} records
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            id="btn-prev-page"
                            @click="goToPage(readings!.current_page - 1)"
                            :disabled="readings.current_page <= 1"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground transition-all hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <ChevronLeft class="h-4 w-4" /> Prev
                        </button>
                        <button
                            id="btn-next-page"
                            @click="goToPage(readings!.current_page + 1)"
                            :disabled="readings.current_page >= readings.last_page"
                            class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground transition-all hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            Next <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
