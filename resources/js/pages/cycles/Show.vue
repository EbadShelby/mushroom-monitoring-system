<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Sprout, CalendarDays, CheckCircle2, XCircle, Clock, ChevronLeft,
    FileText, Thermometer, Droplets, Wind, Sun, Leaf, Plus, X, Trash2,
    Camera, FlaskConical, AlertTriangle, Activity,
} from '@lucide/vue';
import axios from 'axios';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import VueApexCharts from 'vue3-apexcharts';
import * as cycles from '@/routes/cycles';
import * as reports from '@/routes/reports';
import type { GrowingCycle, CameraSnapshot, MushroomMeasurement, DailySensorAverage, ThresholdBreachSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Growing Cycles', href: cycles.index() },
            { title: 'Cycle Detail', href: '#' },
        ],
    },
});

interface CycleDetail extends GrowingCycle {
    day_count: number;
}

const props = defineProps<{
    cycle: CycleDetail;
    dailyAverages?: DailySensorAverage[];
    breachSummary?: ThresholdBreachSummary;
    measurements?: MushroomMeasurement[];
    snapshots?: CameraSnapshot[];
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');

// ── Status helpers ─────────────────────────────────────────────────────────────
const statusConfig = {
    active:    { label: 'Active',    class: 'bg-emerald-500/20 text-emerald-300 ring-emerald-500/30', icon: Clock },
    completed: { label: 'Completed', class: 'bg-blue-500/20 text-blue-300 ring-blue-500/30',         icon: CheckCircle2 },
    cancelled: { label: 'Cancelled', class: 'bg-red-500/20 text-red-300 ring-red-500/30',            icon: XCircle },
} as const;

function statusCfg(s: string) {
    return statusConfig[s as keyof typeof statusConfig] ?? statusConfig.active;
}

function formatDate(d: string | null) {
    if (!d) {
 return '—'; 
}

    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

// ── Sensor average chart ────────────────────────────────────────────────────────
function buildAvgChart(key: 'avg_temperature' | 'avg_humidity' | 'avg_co2' | 'avg_light', color: string) {
    const avgs = props.dailyAverages ?? [];

    return {
        options: {
            chart: { type: 'area', height: 160, toolbar: { show: false }, background: 'transparent' },
            colors: [color],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] } },
            stroke: { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
            xaxis: {
                categories: avgs.map(r => r.date),
                labels: { style: { colors: '#94a3b8', fontSize: '9px' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '9px' } } },
            grid: { borderColor: '#1e293b', strokeDashArray: 4 },
            tooltip: { theme: 'dark' },
        },
        series: [{ name: key.replace('avg_', '').replace('_', ' '), data: avgs.map(r => r[key] ?? 0) }],
    };
}

const tempChart = computed(() => buildAvgChart('avg_temperature', '#f97316'));
const humChart  = computed(() => buildAvgChart('avg_humidity',    '#06b6d4'));
const co2Chart  = computed(() => buildAvgChart('avg_co2',         '#a78bfa'));
const lightChart = computed(() => buildAvgChart('avg_light',      '#fbbf24'));

// ── Breach summary ─────────────────────────────────────────────────────────────
const breachCards = computed(() => [
    { label: 'Temperature', value: props.breachSummary?.temperature ?? 0, range: '24–30°C', icon: Thermometer, color: 'text-orange-400', bg: 'bg-orange-500/10' },
    { label: 'Humidity',    value: props.breachSummary?.humidity    ?? 0, range: '≥ 80%',    icon: Droplets,    color: 'text-cyan-400',   bg: 'bg-cyan-500/10' },
    { label: 'CO₂',        value: props.breachSummary?.co2         ?? 0, range: '≤ 1000ppm',icon: Wind,        color: 'text-purple-400', bg: 'bg-purple-500/10' },
    { label: 'Soil Moisture',value: props.breachSummary?.soil_moisture ?? 0, range: '≥ 30%', icon: Leaf,        color: 'text-amber-400',  bg: 'bg-amber-500/10' },
]);

// ── Add Measurement modal ──────────────────────────────────────────────────────
const showMeasureModal = ref(false);
const measureForm = ref({
    observed_date: new Date().toISOString().split('T')[0],
    flush_number: 1,
    weight_g: '',
    height_cm: '',
    cap_diameter_cm: '',
    fruiting_body_count: '',
    notes: '',
});
const measureErrors = ref<Record<string, string>>({});
const savingMeasure = ref(false);

async function saveMeasurement() {
    measureErrors.value = {};
    savingMeasure.value = true;

    try {
        await axios.post('/api/measurements', {
            ...measureForm.value,
            growing_cycle_id: props.cycle.id,
        });
        toast.success('Measurement saved!');
        showMeasureModal.value = false;
        measureForm.value = { observed_date: new Date().toISOString().split('T')[0], flush_number: 1, weight_g: '', height_cm: '', cap_diameter_cm: '', fruiting_body_count: '', notes: '' };
        router.reload({ only: ['measurements'] });
    } catch (e: any) {
        if (e.response?.status === 422) {
 measureErrors.value = e.response.data.errors ?? {}; 
} else {
 toast.error('Failed to save measurement'); 
}
    } finally {
        savingMeasure.value = false;
    }
}

async function deleteMeasurement(id: number) {
    if (!confirm('Delete this measurement?')) {
 return; 
}

    try {
        await axios.delete(`/api/measurements/${id}`);
        toast.success('Measurement deleted');
        router.reload({ only: ['measurements'] });
    } catch {
 toast.error('Failed to delete'); 
}
}

// ── Lightbox ───────────────────────────────────────────────────────────────────
const lightboxSrc = ref<string | null>(null);

function openLightbox(snap: CameraSnapshot) {
    lightboxSrc.value = `/storage/${snap.file_path}`;
}

// ── Snapshot grouping ─────────────────────────────────────────────────────────
const groupedSnapshots = computed(() => {
    const snaps = props.snapshots ?? [];
    const map = new Map<string, CameraSnapshot[]>();

    for (const s of snaps) {
        const key = s.captured_date ?? '';

        if (!map.has(key)) {
 map.set(key, []); 
}

        map.get(key)!.push(s);
    }

    return Array.from(map.entries()).map(([date, items]) => ({ date, items }));
});

async function deleteSnapshot(id: number) {
    if (!confirm('Delete this photo?')) {
 return; 
}

    try {
        await axios.delete(`/api/camera/${id}`);
        toast.success('Photo deleted');
        router.reload({ only: ['snapshots'] });
    } catch {
 toast.error('Failed to delete photo'); 
}
}

const reportUrl = computed(() => `/reports/${props.cycle.id}`);
</script>

<template>
    <Head :title="`${cycle.name} — Cycle Detail`" />

    <div class="space-y-6">
        <!-- Back + header -->
        <div>
            <Link :href="cycles.index()" class="mb-3 inline-flex items-center gap-1.5 text-sm text-slate-400 transition hover:text-white">
                <ChevronLeft class="h-4 w-4" /> Back to Cycles
            </Link>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/10">
                        <Sprout class="h-6 w-6 text-emerald-400" />
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold text-white">{{ cycle.name }}</h1>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusCfg(cycle.status).class"
                            >
                                <component :is="statusCfg(cycle.status).icon" class="h-3 w-3" />
                                {{ statusCfg(cycle.status).label }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-400">{{ cycle.mushroom_variety }} &bull; {{ cycle.substrate_type }}</p>
                    </div>
                </div>
                <a
                    :href="reportUrl"
                    target="_blank"
                    class="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 transition hover:bg-blue-500 active:scale-95"
                >
                    <FileText class="h-4 w-4" />
                    Generate Report
                </a>
            </div>
        </div>

        <!-- Cycle meta cards -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Day</p>
                <p class="mt-1 text-3xl font-bold text-white">{{ cycle.day_count }}</p>
            </div>
            <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Start Date</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ formatDate(cycle.start_date) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">End Date</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ formatDate(cycle.end_date) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Readings</p>
                <p class="mt-1 text-3xl font-bold text-white">{{ breachSummary?.total_readings?.toLocaleString() ?? '—' }}</p>
            </div>
        </div>

        <!-- Notes -->
        <div v-if="cycle.notes" class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
            <p class="text-sm text-slate-300"><span class="font-semibold text-slate-200">Notes: </span>{{ cycle.notes }}</p>
        </div>

        <!-- Threshold Breaches -->
        <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 backdrop-blur-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-white">
                <AlertTriangle class="h-4 w-4 text-amber-400" />
                Threshold Breach Summary
            </h2>
            <div v-if="!breachSummary" class="grid grid-cols-4 gap-4">
                <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-2xl bg-slate-700/40" />
            </div>
            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div
                    v-for="card in breachCards"
                    :key="card.label"
                    class="rounded-2xl border border-slate-700/30 p-4"
                    :class="card.bg"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <component :is="card.icon" class="h-4 w-4" :class="card.color" />
                        <span class="text-xs text-slate-400">{{ card.label }}</span>
                    </div>
                    <p class="text-3xl font-bold" :class="card.value > 0 ? 'text-amber-300' : 'text-emerald-400'">{{ card.value.toLocaleString() }}</p>
                    <p class="mt-1 text-xs text-slate-500">breaches &bull; target {{ card.range }}</p>
                </div>
            </div>
        </div>

        <!-- Daily Sensor Average Charts -->
        <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 backdrop-blur-sm">
            <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-white">
                <Activity class="h-4 w-4 text-emerald-400" />
                Daily Sensor Averages
            </h2>
            <div v-if="!dailyAverages" class="grid grid-cols-2 gap-4">
                <div v-for="i in 4" :key="i" class="h-40 animate-pulse rounded-2xl bg-slate-700/40" />
            </div>
            <div v-else-if="dailyAverages.length === 0" class="py-10 text-center text-sm text-slate-500">
                No sensor readings recorded for this cycle yet.
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-700/30 bg-slate-900/60 p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <Thermometer class="h-4 w-4 text-orange-400" />
                        <span class="text-sm font-medium text-slate-300">Temperature (°C)</span>
                    </div>
                    <VueApexCharts :options="tempChart.options" :series="tempChart.series" height="140" />
                </div>
                <div class="rounded-2xl border border-slate-700/30 bg-slate-900/60 p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <Droplets class="h-4 w-4 text-cyan-400" />
                        <span class="text-sm font-medium text-slate-300">Humidity (%)</span>
                    </div>
                    <VueApexCharts :options="humChart.options" :series="humChart.series" height="140" />
                </div>
                <div class="rounded-2xl border border-slate-700/30 bg-slate-900/60 p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <Wind class="h-4 w-4 text-purple-400" />
                        <span class="text-sm font-medium text-slate-300">CO₂ (ppm)</span>
                    </div>
                    <VueApexCharts :options="co2Chart.options" :series="co2Chart.series" height="140" />
                </div>
                <div class="rounded-2xl border border-slate-700/30 bg-slate-900/60 p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <Sun class="h-4 w-4 text-amber-400" />
                        <span class="text-sm font-medium text-slate-300">Light Level (lux)</span>
                    </div>
                    <VueApexCharts :options="lightChart.options" :series="lightChart.series" height="140" />
                </div>
            </div>
        </div>

        <!-- Measurement History -->
        <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 backdrop-blur-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base font-semibold text-white">
                    <FlaskConical class="h-4 w-4 text-emerald-400" />
                    Measurement History
                </h2>
                <button
                    v-if="userRole !== 'student'"
                    class="flex items-center gap-1.5 rounded-xl bg-emerald-600/20 px-3 py-1.5 text-xs font-semibold text-emerald-300 ring-1 ring-emerald-500/30 transition hover:bg-emerald-600/30"
                    @click="showMeasureModal = true"
                >
                    <Plus class="h-3.5 w-3.5" /> Add Measurement
                </button>
            </div>
            <div v-if="!measurements" class="space-y-2">
                <div v-for="i in 3" :key="i" class="h-10 animate-pulse rounded-xl bg-slate-700/40" />
            </div>
            <div v-else-if="measurements.length === 0" class="py-8 text-center text-sm text-slate-500">
                No measurements recorded yet. Add the first one!
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Date</th>
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Flush</th>
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Weight (g)</th>
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Height (cm)</th>
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Cap Diam. (cm)</th>
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Fruiting Bodies</th>
                            <th class="pb-2.5 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Logged By</th>
                            <th v-if="userRole !== 'student'" class="pb-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        <tr v-for="m in measurements" :key="m.id" class="group hover:bg-slate-700/10">
                            <td class="py-3 pr-4 text-slate-300">{{ formatDate(m.observed_date) }}</td>
                            <td class="py-3 pr-4">
                                <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400">Flush {{ m.flush_number }}</span>
                            </td>
                            <td class="py-3 pr-4 text-emerald-400 font-bold font-mono">{{ m.weight_g ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-300">{{ m.height_cm ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-300">{{ m.cap_diameter_cm ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-300">{{ m.fruiting_body_count ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-300">{{ m.user?.name ?? '—' }}</td>
                            <td v-if="userRole !== 'student'" class="py-3 text-right">
                                <button
                                    class="rounded-lg p-1.5 text-slate-500 transition hover:bg-red-500/10 hover:text-red-400"
                                    @click="deleteMeasurement(m.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Camera Snapshot Timeline -->
        <div class="rounded-2xl border border-slate-700/50 bg-slate-800/50 p-5 backdrop-blur-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base font-semibold text-white">
                    <Camera class="h-4 w-4 text-emerald-400" />
                    Growth Photo Timeline
                </h2>
                <Link
                    :href="`/camera?cycle_id=${cycle.id}`"
                    class="flex items-center gap-1.5 rounded-xl bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-600"
                >
                    View All Photos
                </Link>
            </div>
            <div v-if="!snapshots" class="grid grid-cols-4 gap-3">
                <div v-for="i in 8" :key="i" class="aspect-square animate-pulse rounded-xl bg-slate-700/40" />
            </div>
            <div v-else-if="snapshots.length === 0" class="py-10 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-700/50">
                    <Camera class="h-7 w-7 text-slate-500" />
                </div>
                <p class="text-sm text-slate-500">No photos uploaded for this cycle yet.</p>
            </div>
            <div v-else class="space-y-5">
                <div v-for="group in groupedSnapshots" :key="group.date">
                    <p class="mb-2 text-xs font-semibold text-slate-400">
                        {{ formatDate(group.date) }}
                    </p>
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        <div
                            v-for="snap in group.items"
                            :key="snap.id"
                            class="group relative aspect-square cursor-pointer overflow-hidden rounded-xl bg-slate-900"
                            @click="openLightbox(snap)"
                        >
                            <img
                                :src="`/storage/${snap.file_path}`"
                                :alt="snap.file_name"
                                class="h-full w-full object-cover transition group-hover:scale-105"
                            />
                            <div class="absolute inset-0 flex items-end bg-gradient-to-t from-black/60 to-transparent opacity-0 transition group-hover:opacity-100">
                                <div class="p-2">
                                    <p v-if="snap.flush_number" class="text-xs font-semibold text-white">Flush {{ snap.flush_number }}</p>
                                </div>
                            </div>
                            <button
                                v-if="userRole !== 'student'"
                                class="absolute right-1 top-1 rounded-lg bg-red-500/80 p-1 opacity-0 transition group-hover:opacity-100"
                                @click.stop="deleteSnapshot(snap.id)"
                            >
                                <Trash2 class="h-3 w-3 text-white" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Measurement Modal -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div
                v-if="showMeasureModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0,0,0,0.7);"
                @click.self="showMeasureModal = false"
            >
                <div class="w-full max-w-md rounded-2xl border border-slate-700/50 bg-slate-900 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-700/50 px-6 py-4">
                        <h2 class="text-lg font-semibold text-white">Add Measurement</h2>
                        <button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-700" @click="showMeasureModal = false">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <form class="space-y-4 p-6" @submit.prevent="saveMeasurement">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Date <span class="text-red-400">*</span></label>
                                <input v-model="measureForm.observed_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" style="color-scheme:dark" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Flush # <span class="text-red-400">*</span></label>
                                <input v-model.number="measureForm.flush_number" type="number" min="1" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Weight (g)</label>
                                <input v-model="measureForm.weight_g" type="number" step="0.1" min="0" placeholder="0.0" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Fruiting Bodies</label>
                                <input v-model.number="measureForm.fruiting_body_count" type="number" min="0" placeholder="0" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Height (cm)</label>
                                <input v-model="measureForm.height_cm" type="number" step="0.1" min="0" placeholder="0.0" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Cap Diam. (cm)</label>
                                <input v-model="measureForm.cap_diameter_cm" type="number" step="0.1" min="0" placeholder="0.0" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-300">Notes</label>
                            <textarea v-model="measureForm.notes" rows="2" class="w-full resize-none rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none" />
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button type="button" class="flex-1 rounded-xl border border-slate-700 py-2.5 text-sm text-slate-300 hover:bg-slate-800" @click="showMeasureModal = false">Cancel</button>
                            <button type="submit" :disabled="savingMeasure" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-60">
                                <span v-if="savingMeasure" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                {{ savingMeasure ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div
                v-if="lightboxSrc"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0,0,0,0.9);"
                @click="lightboxSrc = null"
            >
                <button class="absolute right-4 top-4 rounded-full bg-slate-800/80 p-2 text-white backdrop-blur-sm hover:bg-slate-700">
                    <X class="h-5 w-5" />
                </button>
                <img :src="lightboxSrc" alt="Full size" class="max-h-[90vh] max-w-full rounded-2xl shadow-2xl" @click.stop />
            </div>
        </Transition>
    </Teleport>
</template>
