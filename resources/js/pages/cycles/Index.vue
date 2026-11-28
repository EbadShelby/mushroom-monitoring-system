<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Sprout,
    Plus,
    Check,
    CheckCircle2,
    XCircle,
    Clock,
    Eye,
    Ban,
    SquareCheck,
    Filter,
    X,
    CalendarDays,
    FlaskConical,
    Pencil,
    Ruler,
    BarChart3,
    MoreHorizontal,
} from '@lucide/vue';
import type { ApexOptions } from 'apexcharts';
import axios from 'axios';
import { ref, computed, nextTick, watch } from 'vue';
import { toast } from 'vue-sonner';
import VueApexCharts from 'vue3-apexcharts';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import * as cyclesApi from '@/routes/cycles';
import type { GrowingCycle, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Growing Cycles', href: cyclesApi.index() }],
    },
});

interface ComparableCycleOption {
    id: number;
    name: string;
    mushroom_variety: string;
    status: string;
    start_date: string;
    end_date: string | null;
    measurements_count?: number;
}

interface ComparedCycle {
    id: number;
    name: string;
    mushroom_variety: string;
    substrate_type: string;
    status: string;
    start_date: string;
    end_date: string | null;
    day_count: number;
    readings_count: number;
    averages: {
        temperature: number | null;
        humidity: number | null;
        co2: number | null;
        light: number | null;
        soil_moisture: number | null;
    };
    breaches: {
        temperature: number;
        humidity: number;
        co2: number;
        soil_moisture: number;
        total: number;
    };
    harvest: {
        records_count: number;
        total_fruiting_bodies: number | null;
        avg_height_cm: number | null;
        avg_cap_diameter_cm: number | null;
    };
}

const props = defineProps<{
    cycles: Paginated<GrowingCycle>;
    comparableCycles: ComparableCycleOption[];
    filters: { status?: string | null };
    hasActiveCycle: boolean;
}>();

const page = usePage();
const userRole = computed(
    () => (page.props.auth as any)?.user?.role ?? 'student',
);

// ── Status filter ──────────────────────────────────────────────────────────────
const statusFilter = ref<string>(props.filters.status ?? '');

function applyFilter() {
    router.get(
        cyclesApi.index.url(),
        { status: statusFilter.value || undefined },
        { preserveState: true, replace: true },
    );
}

function clearFilter() {
    statusFilter.value = '';
    applyFilter();
}

// ── Create cycle modal ─────────────────────────────────────────────────────────
const MUSHROOM_VARIETIES = [
    'Gray Oyster Mushroom',
    'White Oyster Mushroom',
    'Pink Oyster Mushroom',
    'Blue Oyster Mushroom',
    'Golden / Yellow Oyster Mushroom',
    'King Oyster Mushroom',
    'Phoenix / Indian Oyster Mushroom',
] as const;

interface BagRow {
    selectedVariety: string;
    customVariety: string;
    count: number;
}

const showModal = ref(false);
const editingCycleId = ref<number | null>(null);
const creating = ref(false);
const bagRows = ref<BagRow[]>([
    { selectedVariety: MUSHROOM_VARIETIES[0], customVariety: '', count: 1 },
]);
const form = ref({
    name: '',
    substrate_type: '',
    start_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const totalBags = computed(() =>
    bagRows.value.reduce((sum, r) => sum + (Number(r.count) || 0), 0),
);

function addBagRow() {
    bagRows.value.push({
        selectedVariety: MUSHROOM_VARIETIES[1],
        customVariety: '',
        count: 1,
    });
}

function removeBagRow(idx: number) {
    if (bagRows.value.length > 1) {
        bagRows.value.splice(idx, 1);
    }
}

function resetBagRows() {
    bagRows.value = [
        { selectedVariety: MUSHROOM_VARIETIES[0], customVariety: '', count: 1 },
    ];
}

const formErrors = ref<Record<string, string>>({});

// ── Common substrates checklist ───────────────────────────────────────────────
const COMMON_SUBSTRATES = [
    { id: 'sawdust', label: 'Sawdust' },
    { id: 'rice_straw', label: 'Rice Straw' },
    { id: 'sugarcane_bagasse', label: 'Sugarcane Bagasse' },
    { id: 'corn_cobs', label: 'Corn Cobs' },
    { id: 'coco_coir', label: 'Coco Coir' },
    { id: 'coffee_grounds', label: 'Coffee Grounds' },
    { id: 'wheat_straw', label: 'Wheat Straw' },
] as const;

const selectedSubstrates = ref<string[]>([]);
const hasOtherSubstrate = ref(false);
const customSubstrate = ref('');
const isManualOverride = ref(false);
const customSubstrateInputRef = ref<HTMLInputElement | null>(null);

function syncSubstrateType() {
    if (isManualOverride.value) {
        return;
    }

    const items: string[] = [...selectedSubstrates.value];

    if (hasOtherSubstrate.value && customSubstrate.value.trim()) {
        items.push(customSubstrate.value.trim());
    }

    form.value.substrate_type = items.join(', ');
}

function toggleCommonSubstrate(label: string) {
    const idx = selectedSubstrates.value.indexOf(label);

    if (idx >= 0) {
        selectedSubstrates.value.splice(idx, 1);
    } else {
        selectedSubstrates.value.push(label);
    }

    syncSubstrateType();
}

function toggleOtherSubstrate() {
    hasOtherSubstrate.value = !hasOtherSubstrate.value;
    syncSubstrateType();

    if (hasOtherSubstrate.value) {
        nextTick(() => {
            customSubstrateInputRef.value?.focus();
        });
    }
}

function onCustomSubstrateInput() {
    syncSubstrateType();
}

function clearSubstrates() {
    selectedSubstrates.value = [];
    hasOtherSubstrate.value = false;
    customSubstrate.value = '';
    form.value.substrate_type = '';
}

function toggleManualOverride() {
    isManualOverride.value = !isManualOverride.value;

    if (!isManualOverride.value) {
        syncSubstrateType();
    }
}

function resetModalForm() {
    editingCycleId.value = null;
    form.value = {
        name: '',
        substrate_type: '',
        start_date: new Date().toISOString().split('T')[0],
        notes: '',
    };
    resetBagRows();
    clearSubstrates();
    isManualOverride.value = false;
    formErrors.value = {};
}

function openModal() {
    if (props.hasActiveCycle) {
        toast.error(
            'An active growing cycle is already running. Please end or cancel it first.',
        );

        return;
    }

    resetModalForm();
    showModal.value = true;
}

function openEditModal(cycle: GrowingCycle) {
    resetModalForm();
    editingCycleId.value = cycle.id;

    form.value = {
        name: cycle.name,
        substrate_type: cycle.substrate_type ?? '',
        start_date: (cycle.start_date ?? '').slice(0, 10),
        notes: cycle.notes ?? '',
    };

    if (cycle.fruiting_bags && cycle.fruiting_bags.length > 0) {
        bagRows.value = cycle.fruiting_bags.map((bag) => {
            const isPreset = (MUSHROOM_VARIETIES as readonly string[]).includes(
                bag.variety,
            );

            return {
                selectedVariety: isPreset ? bag.variety : '__other__',
                customVariety: isPreset ? '' : bag.variety,
                count: bag.count || 1,
            };
        });
    } else if (cycle.mushroom_variety) {
        const isPreset = (MUSHROOM_VARIETIES as readonly string[]).includes(
            cycle.mushroom_variety,
        );
        bagRows.value = [
            {
                selectedVariety: isPreset
                    ? cycle.mushroom_variety
                    : '__other__',
                customVariety: isPreset ? '' : cycle.mushroom_variety,
                count: 1,
            },
        ];
    }

    const commonLabels = COMMON_SUBSTRATES.map((s) => s.label) as string[];
    const parts = (cycle.substrate_type ?? '')
        .split(',')
        .map((p) => p.trim())
        .filter(Boolean);
    const matchedCommon = parts.filter((p) => commonLabels.includes(p));
    const unmatched = parts.filter((p) => !commonLabels.includes(p));

    selectedSubstrates.value = matchedCommon;
    hasOtherSubstrate.value = unmatched.length > 0;
    customSubstrate.value = unmatched.join(', ');
    isManualOverride.value = false;

    showModal.value = true;
}

async function submitCycle() {
    formErrors.value = {};

    const fruiting_bags = bagRows.value.map((r) => ({
        variety:
            r.selectedVariety === '__other__'
                ? r.customVariety.trim()
                : r.selectedVariety,
        count: Number(r.count) || 0,
    }));

    if (fruiting_bags.some((b) => !b.variety || b.count < 1)) {
        formErrors.value = {
            fruiting_bags:
                'Please enter a variety name and at least 1 bag for each row.',
        };

        return;
    }

    creating.value = true;

    try {
        if (editingCycleId.value !== null) {
            await axios.put(`/api/cycles/${editingCycleId.value}`, {
                ...form.value,
                fruiting_bags,
            });
            toast.success('Growing cycle updated!');
        } else {
            await axios.post('/api/cycles', {
                ...form.value,
                fruiting_bags,
            });
            toast.success('Growing cycle created!');
        }

        showModal.value = false;
        resetModalForm();
        router.reload({ only: ['cycles', 'hasActiveCycle'] });
    } catch (e: any) {
        if (e.response?.status === 422) {
            formErrors.value = e.response.data.errors ?? {};

            if (e.response.data.message && !e.response.data.errors) {
                toast.error(e.response.data.message);
            }
        } else {
            toast.error(
                editingCycleId.value !== null
                    ? 'Failed to update cycle'
                    : 'Failed to create cycle',
            );
        }
    } finally {
        creating.value = false;
    }
}

// ── End / Cancel cycle ─────────────────────────────────────────────────────────
const actioning = ref<number | null>(null);
const pendingCancelCycle = ref<GrowingCycle | null>(null);
const endingCycle = ref<GrowingCycle | null>(null);
const endHarvestForm = ref({
    observed_date: new Date().toISOString().split('T')[0],
    fruiting_body_count: '' as string | number,
    height_cm: '' as string | number,
    cap_diameter_cm: '' as string | number,
    harvest_notes: '',
});

const hasEndHarvestInput = computed(
    () =>
        String(endHarvestForm.value.fruiting_body_count).trim() !== '' ||
        String(endHarvestForm.value.height_cm).trim() !== '' ||
        String(endHarvestForm.value.cap_diameter_cm).trim() !== '' ||
        endHarvestForm.value.harvest_notes.trim() !== '',
);

function endCycle(cycle: GrowingCycle) {
    endingCycle.value = cycle;
    endHarvestForm.value = {
        observed_date: new Date().toISOString().split('T')[0],
        fruiting_body_count: '',
        height_cm: '',
        cap_diameter_cm: '',
        harvest_notes: '',
    };
}

function closeEndModal() {
    if (actioning.value !== null) {
        return;
    }

    endingCycle.value = null;
}

async function confirmEndCycle() {
    const cycle = endingCycle.value;

    if (!cycle) {
        return;
    }

    actioning.value = cycle.id;

    try {
        const payload = hasEndHarvestInput.value
            ? {
                  observed_date: endHarvestForm.value.observed_date,
                  fruiting_body_count:
                      String(endHarvestForm.value.fruiting_body_count).trim() !==
                      ''
                          ? Number(endHarvestForm.value.fruiting_body_count)
                          : null,
                  height_cm:
                      String(endHarvestForm.value.height_cm).trim() !== ''
                          ? Number(endHarvestForm.value.height_cm)
                          : null,
                  cap_diameter_cm:
                      String(endHarvestForm.value.cap_diameter_cm).trim() !== ''
                          ? Number(endHarvestForm.value.cap_diameter_cm)
                          : null,
                  harvest_notes: endHarvestForm.value.harvest_notes.trim() || null,
              }
            : {};

        await axios.post(`/api/cycles/${cycle.id}/end`, payload);
        toast.success(
            hasEndHarvestInput.value
                ? 'Harvest saved and cycle marked as completed'
                : 'Cycle marked as completed',
        );
        endingCycle.value = null;
        router.reload({
            only: ['cycles', 'comparableCycles', 'hasActiveCycle'],
        });
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Failed to complete growing cycle',
        );
    } finally {
        actioning.value = null;
    }
}

function cancelCycle(cycle: GrowingCycle) {
    pendingCancelCycle.value = cycle;
}

async function confirmCancelCycle() {
    const cycle = pendingCancelCycle.value;

    if (!cycle) {
        return;
    }

    actioning.value = cycle.id;

    try {
        await axios.delete(`/api/cycles/${cycle.id}`);
        toast.success('Cycle cancelled');
        pendingCancelCycle.value = null;
        router.reload({
            only: ['cycles', 'comparableCycles', 'hasActiveCycle'],
        });
    } catch {
        toast.error('Failed to cancel cycle');
    } finally {
        actioning.value = null;
    }
}

// ── Quick Log Harvest Measurement Modal ────────────────────────────────────────
const measuringCycle = ref<GrowingCycle | null>(null);
const savingMeasurement = ref(false);
const measurementForm = ref({
    observed_date: new Date().toISOString().split('T')[0],
    fruiting_body_count: '' as string | number,
    height_cm: '' as string | number,
    cap_diameter_cm: '' as string | number,
    notes: '',
});

function openMeasurementModal(cycle: GrowingCycle) {
    measuringCycle.value = cycle;
    measurementForm.value = {
        observed_date: new Date().toISOString().split('T')[0],
        fruiting_body_count: '',
        height_cm: '',
        cap_diameter_cm: '',
        notes: '',
    };
}

function closeMeasurementModal() {
    if (savingMeasurement.value) {
        return;
    }

    measuringCycle.value = null;
}

async function submitMeasurement() {
    const cycle = measuringCycle.value;

    if (!cycle) {
        return;
    }

    savingMeasurement.value = true;

    try {
        await axios.post('/api/measurements', {
            growing_cycle_id: cycle.id,
            observed_date: measurementForm.value.observed_date,
            fruiting_body_count:
                String(measurementForm.value.fruiting_body_count).trim() !== ''
                    ? Number(measurementForm.value.fruiting_body_count)
                    : null,
            height_cm:
                String(measurementForm.value.height_cm).trim() !== ''
                    ? Number(measurementForm.value.height_cm)
                    : null,
            cap_diameter_cm:
                String(measurementForm.value.cap_diameter_cm).trim() !== ''
                    ? Number(measurementForm.value.cap_diameter_cm)
                    : null,
            notes: measurementForm.value.notes.trim() || null,
        });
        toast.success('Harvest measurement recorded');
        measuringCycle.value = null;
        router.reload({ only: ['cycles', 'comparableCycles'] });
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Failed to save measurement');
    } finally {
        savingMeasurement.value = false;
    }
}

// ── Cycle Comparison Modal ─────────────────────────────────────────────────────
type CompareTab = 'harvest' | 'climate';
type HarvestMetricKey = 'bodies' | 'height' | 'cap';
type ClimateMetricKey = 'temperature' | 'humidity' | 'soil_moisture' | 'co2';

const MAX_COMPARE_CYCLES = 10;
const DEFAULT_COMPARE_LIMIT = 5;

const showCompareModal = ref(false);
const selectedCompareIds = ref<number[]>([]);
const comparedCycles = ref<ComparedCycle[]>([]);
const loadingCompare = ref(false);
const compareTab = ref<CompareTab>('harvest');
let compareRequestId = 0;

const selectedHarvestMetrics = ref<Record<HarvestMetricKey, boolean>>({
    bodies: true,
    height: true,
    cap: true,
});

const selectedClimateMetrics = ref<Record<ClimateMetricKey, boolean>>({
    temperature: true,
    humidity: true,
    soil_moisture: true,
    co2: false,
});

const HARVEST_METRIC_OPTIONS: {
    key: HarvestMetricKey;
    label: string;
    dotClass: string;
}[] = [
    { key: 'bodies', label: 'Fruiting Bodies', dotClass: 'bg-emerald-500' },
    { key: 'height', label: 'Avg Height (cm)', dotClass: 'bg-blue-500' },
    { key: 'cap', label: 'Avg Cap Size (cm)', dotClass: 'bg-amber-500' },
];

const CLIMATE_METRIC_OPTIONS: {
    key: ClimateMetricKey;
    label: string;
    dotClass: string;
}[] = [
    { key: 'temperature', label: 'Avg Temp (°C)', dotClass: 'bg-orange-500' },
    { key: 'humidity', label: 'Avg Humidity (%)', dotClass: 'bg-cyan-500' },
    {
        key: 'soil_moisture',
        label: 'Avg Moisture (%)',
        dotClass: 'bg-amber-500',
    },
    { key: 'co2', label: 'Avg CO2 (ppm)', dotClass: 'bg-purple-500' },
];

const activeHarvestMetricCount = computed(
    () => Object.values(selectedHarvestMetrics.value).filter(Boolean).length,
);

const activeClimateMetricCount = computed(
    () => Object.values(selectedClimateMetrics.value).filter(Boolean).length,
);

function toggleHarvestMetric(key: HarvestMetricKey) {
    if (
        selectedHarvestMetrics.value[key] &&
        activeHarvestMetricCount.value <= 1
    ) {
        return;
    }

    selectedHarvestMetrics.value[key] = !selectedHarvestMetrics.value[key];
}

function toggleClimateMetric(key: ClimateMetricKey) {
    if (
        selectedClimateMetrics.value[key] &&
        activeClimateMetricCount.value <= 1
    ) {
        return;
    }

    selectedClimateMetrics.value[key] = !selectedClimateMetrics.value[key];
}

function openCompareModal() {
    showCompareModal.value = true;

    if (
        selectedCompareIds.value.length >= 2 &&
        comparedCycles.value.length === 0
    ) {
        fetchComparison();
    }
}

function closeCompareModal() {
    showCompareModal.value = false;
}

function toggleCompareCycle(id: number) {
    const idx = selectedCompareIds.value.indexOf(id);

    if (idx >= 0) {
        selectedCompareIds.value.splice(idx, 1);
    } else {
        if (selectedCompareIds.value.length >= MAX_COMPARE_CYCLES) {
            toast.info(
                `You can compare up to ${MAX_COMPARE_CYCLES} cycles at once.`,
            );

            return;
        }

        selectedCompareIds.value.push(id);
    }
}

function selectLatestCompareCycles() {
    selectedCompareIds.value = props.comparableCycles
        .slice(0, DEFAULT_COMPARE_LIMIT)
        .map((c) => c.id);
}

function selectAllCompareCycles() {
    selectedCompareIds.value = props.comparableCycles
        .slice(0, MAX_COMPARE_CYCLES)
        .map((c) => c.id);
}

function clearCompareSelection() {
    selectedCompareIds.value = [];
    comparedCycles.value = [];
}

async function fetchComparison() {
    if (selectedCompareIds.value.length < 2) {
        comparedCycles.value = [];
        loadingCompare.value = false;

        return;
    }

    const requestId = ++compareRequestId;
    loadingCompare.value = true;

    try {
        const res = await axios.get<{ cycles: ComparedCycle[] }>(
            '/api/cycles/compare',
            {
                params: { ids: selectedCompareIds.value },
            },
        );

        if (requestId === compareRequestId) {
            comparedCycles.value = res.data.cycles;

            const hasAnyHarvest = res.data.cycles.some(
                (c) => c.harvest.records_count > 0,
            );
            const hasAnyReadings = res.data.cycles.some(
                (c) => c.readings_count > 0,
            );

            if (!hasAnyHarvest && hasAnyReadings) {
                compareTab.value = 'climate';
            }
        }
    } catch {
        if (requestId === compareRequestId) {
            toast.error('Failed to load cycle comparison data');
        }
    } finally {
        if (requestId === compareRequestId) {
            loadingCompare.value = false;
        }
    }
}

watch(
    () => props.comparableCycles,
    (list) => {
        const validIds = new Set(list.map((c) => c.id));
        selectedCompareIds.value = selectedCompareIds.value.filter((id) =>
            validIds.has(id),
        );
    },
);

watch(
    () => [...selectedCompareIds.value],
    (ids) => {
        if (!showCompareModal.value) {
            return;
        }

        if (ids.length < 2) {
            compareRequestId++;
            comparedCycles.value = [];
            loadingCompare.value = false;

            return;
        }

        fetchComparison();
    },
);

const compareCycleNames = computed(() =>
    comparedCycles.value.map((c) => c.name),
);

const compareHasHarvestData = computed(() =>
    comparedCycles.value.some((c) => c.harvest.records_count > 0),
);

const compareHasClimateData = computed(() =>
    comparedCycles.value.some((c) => c.readings_count > 0),
);

interface BarSeriesDescriptor {
    name: string;
    color: string;
    axisGroup: 'left' | 'right';
    unit: string;
    decimals: number;
    data: number[];
}

const activeBarSeries = computed<BarSeriesDescriptor[]>(() => {
    const items: BarSeriesDescriptor[] = [];

    if (compareTab.value === 'harvest') {
        if (selectedHarvestMetrics.value.bodies) {
            items.push({
                name: 'Fruiting Bodies',
                color: '#10b981',
                axisGroup: 'left',
                unit: 'fruiting bodies',
                decimals: 0,
                data: comparedCycles.value.map(
                    (c) => c.harvest.total_fruiting_bodies ?? 0,
                ),
            });
        }

        if (selectedHarvestMetrics.value.height) {
            items.push({
                name: 'Avg Height',
                color: '#3b82f6',
                axisGroup: 'right',
                unit: 'cm',
                decimals: 1,
                data: comparedCycles.value.map(
                    (c) => c.harvest.avg_height_cm ?? 0,
                ),
            });
        }

        if (selectedHarvestMetrics.value.cap) {
            items.push({
                name: 'Avg Cap Size',
                color: '#f59e0b',
                axisGroup: 'right',
                unit: 'cm',
                decimals: 1,
                data: comparedCycles.value.map(
                    (c) => c.harvest.avg_cap_diameter_cm ?? 0,
                ),
            });
        }
    } else {
        if (selectedClimateMetrics.value.temperature) {
            items.push({
                name: 'Avg Temp',
                color: '#f97316',
                axisGroup: 'left',
                unit: '°C',
                decimals: 1,
                data: comparedCycles.value.map(
                    (c) => c.averages.temperature ?? 0,
                ),
            });
        }

        if (selectedClimateMetrics.value.humidity) {
            items.push({
                name: 'Avg Humidity',
                color: '#06b6d4',
                axisGroup: 'left',
                unit: '%',
                decimals: 1,
                data: comparedCycles.value.map((c) => c.averages.humidity ?? 0),
            });
        }

        if (selectedClimateMetrics.value.soil_moisture) {
            items.push({
                name: 'Avg Moisture',
                color: '#f59e0b',
                axisGroup: 'left',
                unit: '%',
                decimals: 1,
                data: comparedCycles.value.map(
                    (c) => c.averages.soil_moisture ?? 0,
                ),
            });
        }

        if (selectedClimateMetrics.value.co2) {
            items.push({
                name: 'Avg CO2',
                color: '#a855f7',
                axisGroup: 'right',
                unit: 'ppm',
                decimals: 0,
                data: comparedCycles.value.map((c) => c.averages.co2 ?? 0),
            });
        }
    }

    return items;
});

const compareChartSeries = computed(() =>
    activeBarSeries.value.map((s) => ({ name: s.name, data: s.data })),
);

const compareChartKey = computed(() =>
    compareTab.value === 'harvest'
        ? `h-${selectedHarvestMetrics.value.bodies}-${selectedHarvestMetrics.value.height}-${selectedHarvestMetrics.value.cap}`
        : `c-${selectedClimateMetrics.value.temperature}-${selectedClimateMetrics.value.humidity}-${selectedClimateMetrics.value.soil_moisture}-${selectedClimateMetrics.value.co2}`,
);

const compareChartOptions = computed<ApexOptions>(() => {
    const seriesList = activeBarSeries.value;
    const leftSeries = seriesList.filter((s) => s.axisGroup === 'left');
    const rightSeries = seriesList.filter((s) => s.axisGroup === 'right');
    const hasLeft = leftSeries.length > 0;

    const rawMaxLeft = Math.max(0, ...leftSeries.flatMap((s) => s.data));
    const maxLeft = rawMaxLeft > 0 ? Math.ceil(rawMaxLeft * 1.15) : 10;

    const rawMaxRight = Math.max(0, ...rightSeries.flatMap((s) => s.data));
    const maxRight = rawMaxRight > 0 ? Math.ceil(rawMaxRight * 1.15) : 10;

    const leftTitle =
        compareTab.value === 'harvest' ? 'Fruiting Bodies' : 'Temp / %';
    const leftColor = compareTab.value === 'harvest' ? '#10b981' : '#94a3b8';
    const rightTitle =
        compareTab.value === 'harvest' ? 'Size (cm)' : 'CO2 (ppm)';
    const rightColor = compareTab.value === 'harvest' ? '#94a3b8' : '#a855f7';

    let leftAxisShown = false;
    let rightAxisShown = false;

    const yaxis = seriesList.map((s) => {
        if (s.axisGroup === 'left') {
            const show = !leftAxisShown;
            leftAxisShown = true;

            return {
                show,
                min: 0,
                max: maxLeft,
                title: show
                    ? {
                          text: leftTitle,
                          style: { color: leftColor, fontSize: '11px' },
                      }
                    : undefined,
                labels: {
                    style: { colors: leftColor, fontSize: '11px' },
                    formatter: (v: number) =>
                        compareTab.value === 'harvest'
                            ? `${Math.round(v)}`
                            : `${v.toFixed(0)}`,
                },
            };
        }

        const show = !rightAxisShown;
        rightAxisShown = true;

        return {
            opposite: hasLeft,
            show,
            min: 0,
            max: maxRight,
            title: show
                ? {
                      text: rightTitle,
                      style: { color: rightColor, fontSize: '11px' },
                  }
                : undefined,
            labels: {
                style: { colors: rightColor, fontSize: '11px' },
                formatter: (v: number) =>
                    compareTab.value === 'harvest'
                        ? `${v.toFixed(1)} cm`
                        : `${Math.round(v)} ppm`,
            },
        };
    });

    return {
        chart: {
            type: 'bar' as const,
            height: 280,
            toolbar: { show: false },
            background: 'transparent',
        },
        colors: seriesList.map((s) => s.color),
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth:
                    seriesList.length === 1
                        ? '35%'
                        : seriesList.length === 2
                          ? '50%'
                          : '64%',
            },
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
            categories: compareCycleNames.value,
            labels: {
                trim: true,
                maxHeight: 60,
                style: { colors: '#94a3b8', fontSize: '11px' },
            },
            axisBorder: { show: false },
        },
        yaxis,
        grid: { borderColor: '#1e293b', strokeDashArray: 4 },
        tooltip: {
            theme: 'dark',
            y: {
                formatter: (v: number, opts?: { seriesIndex?: number }) => {
                    const meta = seriesList[opts?.seriesIndex ?? 0];

                    if (!meta) {
                        return `${v}`;
                    }

                    const formatted =
                        meta.decimals === 0 ? `${Math.round(v)}` : `${v}`;

                    return meta.unit === '°C' || meta.unit === '%'
                        ? `${formatted}${meta.unit}`
                        : `${formatted} ${meta.unit}`;
                },
            },
        },
    };
});

const lowestBreachesValue = computed(() => {
    const withReadings = comparedCycles.value.filter(
        (c) => c.readings_count > 0,
    );

    if (withReadings.length < 2) {
        return null;
    }

    return Math.min(...withReadings.map((c) => c.breaches.total));
});

const highestHarvestValue = computed(() => {
    const counts = comparedCycles.value
        .map((c) => c.harvest.total_fruiting_bodies)
        .filter((v): v is number => v !== null && v > 0);

    if (counts.length < 2) {
        return null;
    }

    return Math.max(...counts);
});

// ── Pagination ─────────────────────────────────────────────────────────────────
function goToPage(page: number) {
    router.get(
        cyclesApi.index.url(),
        {
            page,
            status: statusFilter.value || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
}

// ── Status helpers ─────────────────────────────────────────────────────────────
const statusConfig = {
    active: {
        label: 'Active',
        class: 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/20 dark:text-emerald-300 dark:ring-emerald-500/30',
        icon: Clock,
    },
    completed: {
        label: 'Completed',
        class: 'bg-blue-100 text-blue-700 ring-blue-600/20 dark:bg-blue-500/20 dark:text-blue-300 dark:ring-blue-500/30',
        icon: CheckCircle2,
    },
    cancelled: {
        label: 'Cancelled',
        class: 'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-500/20 dark:text-red-300 dark:ring-red-500/30',
        icon: XCircle,
    },
} as const;

function statusCfg(status: string) {
    return (
        statusConfig[status as keyof typeof statusConfig] ?? statusConfig.active
    );
}

function formatDate(d: string | null) {
    if (!d) {
        return '—';
    }

    return new Date(d).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Growing Cycles" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8 md:pt-6">
        <!-- Page header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold text-foreground dark:text-white">
                    Growing Cycles
                </h1>
                <p
                    class="mt-1 text-sm text-muted-foreground dark:text-slate-400"
                >
                    Manage oyster mushroom cultivation cycles
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                <button
                    v-if="comparableCycles.length >= 2"
                    type="button"
                    class="flex items-center gap-2 rounded-xl border border-border/60 bg-card px-4 py-2.5 text-sm font-semibold text-foreground shadow-sm transition hover:bg-muted active:scale-95"
                    @click="openCompareModal"
                >
                    <BarChart3 class="h-4 w-4 text-emerald-500" />
                    Compare Cycles
                </button>
                <button
                    v-if="userRole !== 'student'"
                    id="create-cycle-btn"
                    :disabled="hasActiveCycle"
                    :title="
                        hasActiveCycle
                            ? 'End or cancel the active growing cycle before starting a new one'
                            : 'Create a new growing cycle'
                    "
                    class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-foreground shadow-lg shadow-emerald-900/40 transition hover:bg-emerald-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-emerald-600 disabled:active:scale-100 dark:text-white"
                    @click="openModal"
                >
                    <Plus class="h-4 w-4" />
                    New Cycle
                </button>
            </div>
        </div>

        <!-- Filter bar -->
        <div
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-border/50 bg-card/50 p-4 backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-800/50"
        >
            <Filter class="h-4 w-4 text-muted-foreground dark:text-slate-400" />
            <span class="text-sm text-muted-foreground dark:text-slate-400"
                >Filter by status:</span
            >
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opt in ['', 'active', 'completed', 'cancelled']"
                    :key="opt"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold capitalize transition"
                    :class="
                        statusFilter === opt
                            ? 'bg-emerald-600 text-foreground dark:text-white'
                            : 'bg-muted text-foreground/80 hover:bg-muted-foreground/20 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'
                    "
                    @click="
                        statusFilter = opt;
                        applyFilter();
                    "
                >
                    {{ opt || 'All' }}
                </button>
            </div>
            <button
                v-if="statusFilter"
                class="ml-auto flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground dark:text-slate-400 dark:hover:text-white"
                @click="clearFilter"
            >
                <X class="h-3 w-3" /> Clear
            </button>
        </div>

        <!-- Cycles table -->
        <div
            class="overflow-hidden rounded-2xl border border-border/50 bg-card/50 backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-800/50"
        >
            <div
                v-if="cycles.data.length === 0"
                class="flex flex-col items-center gap-4 py-16 text-center"
            >
                <div class="rounded-full bg-muted/60 p-5 dark:bg-slate-700/60">
                    <Sprout class="h-10 w-10 text-emerald-400" />
                </div>
                <div>
                    <p class="font-semibold text-foreground dark:text-white">
                        No cycles found
                    </p>
                    <p
                        class="mt-1 text-sm text-muted-foreground dark:text-slate-400"
                    >
                        {{
                            userRole === 'student'
                                ? 'No growing cycles have been created yet.'
                                : 'Start your first growing cycle to get started.'
                        }}
                    </p>
                </div>
                <button
                    v-if="userRole !== 'student'"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-foreground hover:bg-emerald-500 dark:text-white"
                    @click="openModal"
                >
                    Create First Cycle
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead>
                        <tr
                            class="border-b border-border/50 bg-muted/30 dark:border-slate-700/50 dark:bg-slate-900/50"
                        >
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase dark:text-slate-400"
                            >
                                Cycle
                            </th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase dark:text-slate-400"
                            >
                                Variety
                            </th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase dark:text-slate-400"
                            >
                                Dates
                            </th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase dark:text-slate-400"
                            >
                                Status
                            </th>
                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase dark:text-slate-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-border/50 dark:divide-slate-700/30"
                    >
                        <tr
                            v-for="cycle in cycles.data"
                            :key="cycle.id"
                            class="group transition hover:bg-muted dark:bg-slate-700/20"
                        >
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10"
                                    >
                                        <Sprout
                                            class="h-4 w-4 text-emerald-400"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="font-semibold text-foreground dark:text-white"
                                        >
                                            {{ cycle.name }}
                                        </p>
                                        <p
                                            v-if="cycle.day_count"
                                            class="text-xs text-muted-foreground dark:text-slate-400"
                                        >
                                            Day {{ cycle.day_count }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-5 py-4 text-sm text-foreground/80 dark:text-slate-300"
                            >
                                {{ cycle.mushroom_variety }}
                            </td>
                            <td
                                class="px-5 py-4 text-sm whitespace-nowrap text-foreground/80 dark:text-slate-300"
                            >
                                <div class="flex items-center gap-1.5">
                                    <CalendarDays
                                        class="h-3.5 w-3.5 shrink-0 text-slate-500"
                                    />
                                    <span>
                                        {{ formatDate(cycle.start_date) }}
                                        <span class="text-muted-foreground">
                                            –
                                        </span>
                                        {{
                                            cycle.end_date
                                                ? formatDate(cycle.end_date)
                                                : cycle.status === 'active'
                                                  ? 'Present'
                                                  : '—'
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    :class="statusCfg(cycle.status).class"
                                >
                                    <component
                                        :is="statusCfg(cycle.status).icon"
                                        class="h-3 w-3"
                                    />
                                    {{ statusCfg(cycle.status).label }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Link
                                        :href="cyclesApi.show(cycle.id)"
                                        class="flex shrink-0 items-center gap-1.5 rounded-lg bg-muted px-3 py-1.5 text-xs font-medium whitespace-nowrap text-foreground transition hover:bg-muted-foreground/20 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                                    >
                                        <Eye class="h-3.5 w-3.5 shrink-0" />
                                        View
                                    </Link>
                                    <button
                                        v-if="
                                            cycle.status === 'active' &&
                                            userRole !== 'student'
                                        "
                                        :disabled="actioning === cycle.id"
                                        class="flex shrink-0 items-center gap-1.5 rounded-lg bg-blue-100 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-blue-700 ring-1 ring-blue-600/20 transition hover:bg-blue-200 disabled:opacity-50 dark:bg-blue-600/20 dark:text-blue-300 dark:ring-blue-500/30 dark:hover:bg-blue-600/30"
                                        @click="endCycle(cycle)"
                                    >
                                        <SquareCheck
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        Complete
                                    </button>
                                    <button
                                        v-else-if="
                                            cycle.status === 'completed' &&
                                            userRole !== 'student'
                                        "
                                        class="flex shrink-0 items-center gap-1.5 rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-emerald-700 ring-1 ring-emerald-600/20 transition hover:bg-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-300 dark:ring-emerald-500/30 dark:hover:bg-emerald-500/30"
                                        @click="openMeasurementModal(cycle)"
                                    >
                                        <Ruler class="h-3.5 w-3.5 shrink-0" />
                                        Log Harvest
                                    </button>
                                    <DropdownMenu v-if="userRole !== 'student'">
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                aria-label="More actions"
                                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-border/60 bg-background/60 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                            >
                                                <MoreHorizontal
                                                    class="h-4 w-4"
                                                />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-44"
                                        >
                                            <DropdownMenuItem
                                                v-if="cycle.status === 'active'"
                                                class="cursor-pointer gap-2"
                                                @select="
                                                    openMeasurementModal(cycle)
                                                "
                                            >
                                                <Ruler
                                                    class="h-4 w-4 text-emerald-500"
                                                />
                                                Log Harvest
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2"
                                                @select="openEditModal(cycle)"
                                            >
                                                <Pencil
                                                    class="h-4 w-4 text-amber-500"
                                                />
                                                Edit Cycle
                                            </DropdownMenuItem>
                                            <template
                                                v-if="cycle.status === 'active'"
                                            >
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    :disabled="
                                                        actioning === cycle.id
                                                    "
                                                    class="cursor-pointer gap-2 text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                    @select="cancelCycle(cycle)"
                                                >
                                                    <Ban class="h-4 w-4" />
                                                    Cancel Cycle
                                                </DropdownMenuItem>
                                            </template>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <PaginationBar
                :current-page="cycles.current_page"
                :last-page="cycles.last_page"
                :total="cycles.total"
                unit="cycles"
                @change="goToPage"
            />
        </div>
    </div>

    <!-- Create Cycle Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0, 0, 0, 0.7)"
                @click.self="showModal = false"
            >
                <Transition
                    enter-active-class="transition duration-200"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                >
                    <div
                        v-if="showModal"
                        class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-border/50 bg-card shadow-2xl dark:border-slate-700/50 dark:bg-slate-900"
                    >
                        <!-- Modal header -->
                        <div
                            class="sticky top-0 z-10 flex items-center justify-between border-b border-border/50 bg-card/95 px-6 py-4 backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-900/95"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10"
                                >
                                    <FlaskConical
                                        class="h-5 w-5 text-emerald-400"
                                    />
                                </div>
                                <h2
                                    class="text-lg font-semibold text-foreground dark:text-white"
                                >
                                    {{
                                        editingCycleId !== null
                                            ? 'Edit Growing Cycle'
                                            : 'New Growing Cycle'
                                    }}
                                </h2>
                            </div>
                            <button
                                class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                                @click="showModal = false"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Modal body -->
                        <form
                            class="space-y-4 p-6"
                            @submit.prevent="submitCycle"
                        >
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-foreground/80 dark:text-slate-300"
                                    >Cycle Name
                                    <span class="text-red-400">*</span></label
                                >
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Batch 2026-A"
                                    class="w-full rounded-xl border border-border bg-card px-4 py-2.5 text-sm text-foreground placeholder-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                                <p
                                    v-if="formErrors.name"
                                    class="mt-1 text-xs text-red-400"
                                >
                                    {{ formErrors.name }}
                                </p>
                            </div>

                            <div>
                                <div
                                    class="mb-1.5 flex items-center justify-between"
                                >
                                    <label
                                        class="block text-sm font-medium text-foreground/80 dark:text-slate-300"
                                        >Fruiting Bags
                                        <span class="text-red-400">*</span>
                                        <span
                                            class="ml-1.5 text-xs font-normal text-muted-foreground dark:text-slate-400"
                                            >({{ totalBags }} total)</span
                                        ></label
                                    >
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 text-xs font-medium text-emerald-600 transition hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300"
                                        @click="addBagRow"
                                    >
                                        <Plus class="h-3.5 w-3.5" /> Add variety
                                    </button>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(row, idx) in bagRows"
                                        :key="idx"
                                        class="space-y-1.5"
                                    >
                                        <div class="flex items-center gap-2">
                                            <select
                                                v-model="row.selectedVariety"
                                                class="min-w-0 flex-1 rounded-xl border border-border bg-card px-3 py-2 text-sm text-foreground focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            >
                                                <option
                                                    v-for="variety in MUSHROOM_VARIETIES"
                                                    :key="variety"
                                                    :value="variety"
                                                >
                                                    {{ variety }}
                                                </option>
                                                <option value="__other__">
                                                    Other / Custom...
                                                </option>
                                            </select>
                                            <input
                                                v-model.number="row.count"
                                                type="number"
                                                min="1"
                                                max="10000"
                                                placeholder="Qty"
                                                class="w-20 shrink-0 rounded-xl border border-border bg-card px-3 py-2 text-center text-sm text-foreground focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            />
                                            <button
                                                v-if="bagRows.length > 1"
                                                type="button"
                                                class="rounded-lg p-2 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive dark:text-slate-400"
                                                @click="removeBagRow(idx)"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <input
                                            v-if="
                                                row.selectedVariety ===
                                                '__other__'
                                            "
                                            v-model="row.customVariety"
                                            type="text"
                                            placeholder="Enter custom mushroom variety..."
                                            class="w-full rounded-xl border border-border bg-card px-3 py-2 text-sm text-foreground placeholder-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="
                                        formErrors.fruiting_bags ||
                                        formErrors.mushroom_variety
                                    "
                                    class="mt-1 text-xs text-red-400"
                                >
                                    {{
                                        formErrors.fruiting_bags ||
                                        formErrors.mushroom_variety
                                    }}
                                </p>
                            </div>

                            <!-- Substrate Type -->
                            <div>
                                <div
                                    class="mb-1.5 flex items-center justify-between"
                                >
                                    <label
                                        class="block text-sm font-medium text-foreground/80 dark:text-slate-300"
                                    >
                                        Substrate Type
                                        <span class="text-red-400">*</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="
                                                selectedSubstrates.length > 0 ||
                                                hasOtherSubstrate ||
                                                customSubstrate ||
                                                form.substrate_type
                                            "
                                            type="button"
                                            class="text-xs text-muted-foreground transition hover:text-foreground dark:text-slate-400 dark:hover:text-slate-200"
                                            @click="clearSubstrates"
                                        >
                                            Clear
                                        </button>
                                        <span
                                            v-if="
                                                selectedSubstrates.length > 0 ||
                                                hasOtherSubstrate ||
                                                customSubstrate ||
                                                form.substrate_type
                                            "
                                            class="text-xs text-muted-foreground/40 dark:text-slate-600"
                                            >&bull;</span
                                        >
                                        <button
                                            type="button"
                                            class="text-xs font-medium text-emerald-600 transition hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300"
                                            @click="toggleManualOverride"
                                        >
                                            {{
                                                isManualOverride
                                                    ? 'Use checklist'
                                                    : 'Type custom mix'
                                            }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Manual Raw Text Input -->
                                <div v-if="isManualOverride">
                                    <input
                                        v-model="form.substrate_type"
                                        type="text"
                                        placeholder="e.g. Sawdust, Rice straw (80/20 mix)"
                                        class="w-full rounded-xl border border-border bg-card px-4 py-2.5 text-sm text-foreground placeholder-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                    <p
                                        class="mt-1 text-xs text-muted-foreground dark:text-slate-400"
                                    >
                                        Directly enter your custom substrate
                                        formulation.
                                    </p>
                                </div>

                                <!-- Checklist Mode -->
                                <div v-else class="space-y-2.5">
                                    <div
                                        class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                    >
                                        <button
                                            v-for="sub in COMMON_SUBSTRATES"
                                            :key="sub.id"
                                            type="button"
                                            class="flex items-center gap-2.5 rounded-xl border px-3 py-2 text-left text-xs font-medium transition select-none"
                                            :class="
                                                selectedSubstrates.includes(
                                                    sub.label,
                                                )
                                                    ? 'border-emerald-500/60 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/50 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                    : 'border-border bg-card text-foreground/80 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-slate-600'
                                            "
                                            @click="
                                                toggleCommonSubstrate(sub.label)
                                            "
                                        >
                                            <div
                                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition"
                                                :class="
                                                    selectedSubstrates.includes(
                                                        sub.label,
                                                    )
                                                        ? 'border-emerald-500 bg-emerald-500 text-white dark:border-emerald-400 dark:bg-emerald-500'
                                                        : 'border-slate-400/60 bg-transparent dark:border-slate-600'
                                                "
                                            >
                                                <Check
                                                    v-if="
                                                        selectedSubstrates.includes(
                                                            sub.label,
                                                        )
                                                    "
                                                    class="h-3 w-3 stroke-3"
                                                />
                                            </div>
                                            <span class="truncate">{{
                                                sub.label
                                            }}</span>
                                        </button>

                                        <!-- Other / Custom toggle button -->
                                        <button
                                            type="button"
                                            class="flex items-center gap-2.5 rounded-xl border px-3 py-2 text-left text-xs font-medium transition select-none"
                                            :class="
                                                hasOtherSubstrate
                                                    ? 'border-emerald-500/60 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/50 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                    : 'border-dashed border-border bg-card text-muted-foreground hover:border-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:border-slate-600'
                                            "
                                            @click="toggleOtherSubstrate"
                                        >
                                            <div
                                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition"
                                                :class="
                                                    hasOtherSubstrate
                                                        ? 'border-emerald-500 bg-emerald-500 text-white dark:border-emerald-400 dark:bg-emerald-500'
                                                        : 'border-slate-400/60 bg-transparent dark:border-slate-600'
                                                "
                                            >
                                                <Check
                                                    v-if="hasOtherSubstrate"
                                                    class="h-3 w-3 stroke-3"
                                                />
                                            </div>
                                            <span class="truncate"
                                                >Other / Custom...</span
                                            >
                                        </button>
                                    </div>

                                    <!-- Custom substrate text field when 'Other' is checked -->
                                    <div
                                        v-if="hasOtherSubstrate"
                                        class="pt-0.5"
                                    >
                                        <input
                                            ref="customSubstrateInputRef"
                                            v-model="customSubstrate"
                                            type="text"
                                            placeholder="Specify other substrate / supplement (e.g. Cotton waste, Wheat bran)"
                                            class="w-full rounded-xl border border-border bg-card px-3.5 py-2 text-xs text-foreground placeholder-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            @input="onCustomSubstrateInput"
                                        />
                                    </div>

                                    <!-- Selection Summary Badge / Preview -->
                                    <div
                                        v-if="form.substrate_type"
                                        class="flex flex-wrap items-center gap-1.5 pt-0.5 text-xs"
                                    >
                                        <span
                                            class="text-[11px] font-medium text-muted-foreground dark:text-slate-400"
                                        >
                                            Selected:
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 font-medium text-emerald-700 dark:text-emerald-300"
                                        >
                                            {{ form.substrate_type }}
                                        </span>
                                    </div>
                                </div>

                                <p
                                    v-if="formErrors.substrate_type"
                                    class="mt-1 text-xs text-red-400"
                                >
                                    {{ formErrors.substrate_type }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-foreground/80 dark:text-slate-300"
                                    >Start Date
                                    <span class="text-red-400">*</span></label
                                >
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    class="w-full rounded-xl border border-border bg-card px-4 py-2.5 text-sm text-foreground focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    style="color-scheme: dark"
                                />
                                <p
                                    v-if="formErrors.start_date"
                                    class="mt-1 text-xs text-red-400"
                                >
                                    {{ formErrors.start_date }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-foreground/80 dark:text-slate-300"
                                    >Notes</label
                                >
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Optional notes about this batch..."
                                    class="w-full resize-none rounded-xl border border-border bg-card px-4 py-2.5 text-sm text-foreground placeholder-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                />
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border border-border px-4 py-2.5 text-sm font-medium text-foreground/80 transition hover:bg-card dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    @click="showModal = false"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="creating"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-foreground shadow-lg transition hover:bg-emerald-500 disabled:opacity-60 dark:text-white"
                                >
                                    <span
                                        v-if="creating"
                                        class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                                    />
                                    {{
                                        creating
                                            ? editingCycleId !== null
                                                ? 'Saving...'
                                                : 'Creating...'
                                            : editingCycleId !== null
                                              ? 'Save Changes'
                                              : 'Create Cycle'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <!-- Quick Log Harvest Measurement Modal -->
    <Teleport to="body">
        <div
            v-if="measuringCycle"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="closeMeasurementModal"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-2xl border border-border/50 bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border/50 px-6 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500"
                        >
                            <Ruler class="h-4 w-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-foreground">
                                Log Harvest Measurement
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ measuringCycle.name }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="closeMeasurementModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4 p-6" @submit.prevent="submitMeasurement">
                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-muted-foreground"
                        >
                            Harvest Date *
                        </label>
                        <input
                            v-model="measurementForm.observed_date"
                            type="date"
                            required
                            class="w-full rounded-lg border border-border/60 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-muted-foreground"
                        >
                            Fruiting Bodies (count)
                        </label>
                        <input
                            v-model="measurementForm.fruiting_body_count"
                            type="number"
                            min="0"
                            placeholder="e.g., 120"
                            class="w-full rounded-lg border border-border/60 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                Avg Height (cm)
                            </label>
                            <input
                                v-model="measurementForm.height_cm"
                                type="number"
                                step="0.1"
                                min="0"
                                placeholder="e.g., 8.5"
                                class="w-full rounded-lg border border-border/60 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                Avg Cap Size (cm)
                            </label>
                            <input
                                v-model="measurementForm.cap_diameter_cm"
                                type="number"
                                step="0.1"
                                min="0"
                                placeholder="e.g., 6.0"
                                class="w-full rounded-lg border border-border/60 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-xs font-medium text-muted-foreground"
                        >
                            Notes (optional)
                        </label>
                        <textarea
                            v-model="measurementForm.notes"
                            rows="2"
                            placeholder="Harvest observations..."
                            class="w-full resize-none rounded-lg border border-border/60 bg-background px-3 py-2 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-lg border border-border/60 px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            @click="closeMeasurementModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="savingMeasurement"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-50"
                        >
                            {{
                                savingMeasurement
                                    ? 'Saving...'
                                    : 'Save Harvest'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Complete Growing Cycle Modal (with optional harvest logging) -->
    <Teleport to="body">
        <div
            v-if="endingCycle"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="closeEndModal"
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-2xl border border-border/50 bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border/50 px-6 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="rounded-lg bg-blue-500/10 p-2 text-blue-500"
                        >
                            <SquareCheck class="h-4 w-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-foreground">
                                Complete Growing Cycle
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ endingCycle.name }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="closeEndModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4 p-6" @submit.prevent="confirmEndCycle">
                    <p class="text-sm text-muted-foreground">
                        Mark
                        <span class="font-medium text-foreground">
                            “{{ endingCycle.name }}”
                        </span>
                        as completed? Automated monitoring alerts will pause
                        until a new cycle starts.
                    </p>

                    <div
                        v-if="(endingCycle.measurements_count ?? 0) > 0"
                        class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-2.5 text-xs text-emerald-700 dark:text-emerald-300"
                    >
                        <CheckCircle2 class="h-4 w-4 shrink-0" />
                        <span>
                            Harvest measurements already logged ({{
                                endingCycle.measurements_count
                            }}
                            {{
                                endingCycle.measurements_count === 1
                                    ? 'record'
                                    : 'records'
                            }}).
                        </span>
                    </div>

                    <div
                        v-else
                        class="space-y-3 rounded-xl border border-border/60 bg-muted/20 p-4"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-foreground uppercase"
                            >
                                Log Harvest Results (Optional)
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Record your harvest now or leave blank to log it
                                later.
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-muted-foreground"
                            >
                                Fruiting Bodies (count)
                            </label>
                            <input
                                v-model="endHarvestForm.fruiting_body_count"
                                type="number"
                                min="0"
                                placeholder="e.g., 120"
                                class="w-full rounded-lg border border-border/60 bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-muted-foreground"
                                >
                                    Avg Height (cm)
                                </label>
                                <input
                                    v-model="endHarvestForm.height_cm"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="e.g., 8.5"
                                    class="w-full rounded-lg border border-border/60 bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-muted-foreground"
                                >
                                    Avg Cap Size (cm)
                                </label>
                                <input
                                    v-model="endHarvestForm.cap_diameter_cm"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    placeholder="e.g., 6.0"
                                    class="w-full rounded-lg border border-border/60 bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs font-medium text-muted-foreground"
                            >
                                Notes
                            </label>
                            <input
                                v-model="endHarvestForm.harvest_notes"
                                type="text"
                                placeholder="Optional harvest notes..."
                                class="w-full rounded-lg border border-border/60 bg-background px-3 py-1.5 text-sm text-foreground focus:ring-2 focus:ring-emerald-500/50 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            class="rounded-lg border border-border/60 px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            @click="closeEndModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="actioning !== null"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-50"
                        >
                            {{
                                actioning !== null
                                    ? 'Completing...'
                                    : hasEndHarvestInput
                                      ? 'Save Harvest & Complete'
                                      : 'Mark Completed'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Compare Cycles Modal -->
    <Teleport to="body">
        <div
            v-if="showCompareModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm"
            @click.self="closeCompareModal"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-border/50 bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border/50 px-6 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500"
                        >
                            <BarChart3 class="h-4 w-4" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-foreground">
                                Compare Growing Cycles
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Compare harvest results and average growing
                                conditions across cycles.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="closeCompareModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <!-- Cycle Picker -->
                    <div class="space-y-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Select Cycles ({{ selectedCompareIds.length }}/{{
                                    Math.min(
                                        comparableCycles.length,
                                        MAX_COMPARE_CYCLES,
                                    )
                                }})
                            </span>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <button
                                    v-if="
                                        comparableCycles.length >
                                        DEFAULT_COMPARE_LIMIT
                                    "
                                    type="button"
                                    class="rounded-lg border border-border/50 px-2.5 py-1 font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    @click="selectLatestCompareCycles"
                                >
                                    Latest {{ DEFAULT_COMPARE_LIMIT }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 font-medium text-emerald-600 transition hover:bg-emerald-500/20 dark:text-emerald-300"
                                    @click="selectAllCompareCycles"
                                >
                                    Compare All{{
                                        comparableCycles.length >
                                        MAX_COMPARE_CYCLES
                                            ? ` (First ${MAX_COMPARE_CYCLES})`
                                            : ''
                                    }}
                                </button>
                                <button
                                    v-if="selectedCompareIds.length > 0"
                                    type="button"
                                    class="rounded-lg border border-border/50 px-2.5 py-1 font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    @click="clearCompareSelection"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div
                            class="flex max-h-24 flex-wrap items-center gap-2 overflow-y-auto pr-1"
                        >
                            <button
                                v-for="cycle in comparableCycles"
                                :key="cycle.id"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    selectedCompareIds.includes(cycle.id)
                                        ? 'border-emerald-500/60 bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'
                                        : 'border-border/50 bg-muted/20 text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                                "
                                @click="toggleCompareCycle(cycle.id)"
                            >
                                <span>{{ cycle.name }}</span>
                                <span
                                    v-if="cycle.status === 'active'"
                                    class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                    title="Active cycle"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Empty / 1-selected prompt -->
                    <div
                        v-if="selectedCompareIds.length < 2"
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/15 py-12 text-center"
                    >
                        <BarChart3
                            class="mb-2 h-8 w-8 text-muted-foreground/40"
                        />
                        <p class="text-sm font-medium text-foreground">
                            {{
                                selectedCompareIds.length === 1
                                    ? 'Select 1 more cycle to compare'
                                    : 'Select 2 or more cycles to compare'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Click cycle names above, or press Compare All to see
                            harvest results and growing conditions side-by-side.
                        </p>
                    </div>

                    <!-- Loading state -->
                    <div
                        v-else-if="loadingCompare && comparedCycles.length === 0"
                        class="flex flex-col items-center justify-center rounded-xl border border-border/40 bg-muted/15 py-14"
                    >
                        <span
                            class="mb-2.5 h-6 w-6 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent"
                        />
                        <p class="text-xs text-muted-foreground">
                            Loading cycle comparison...
                        </p>
                    </div>

                    <!-- Comparison Content -->
                    <template v-else-if="comparedCycles.length >= 2">
                        <!-- View Tabs (controls both chart and table) -->
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div
                                class="inline-flex rounded-xl border border-border/60 bg-muted/30 p-1 text-xs"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg px-3.5 py-1.5 font-semibold transition"
                                    :class="
                                        compareTab === 'harvest'
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="compareTab = 'harvest'"
                                >
                                    Harvest Results
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3.5 py-1.5 font-semibold transition"
                                    :class="
                                        compareTab === 'climate'
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="compareTab = 'climate'"
                                >
                                    Growing Conditions
                                </button>
                            </div>
                        </div>

                        <!-- Full-Width Bar Chart with Checkboxes -->
                        <div
                            class="rounded-xl border border-border/40 bg-muted/20 p-4"
                        >
                            <div
                                class="mb-3 flex flex-wrap items-center justify-between gap-3"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        compareTab === 'harvest'
                                            ? 'Harvest Comparison'
                                            : 'Average Growing Conditions'
                                    }}
                                </p>

                                <!-- Metric Checkboxes -->
                                <div
                                    v-if="compareTab === 'harvest'"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <label
                                        v-for="m in HARVEST_METRIC_OPTIONS"
                                        :key="m.key"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1 text-xs font-medium transition select-none"
                                        :class="
                                            selectedHarvestMetrics[m.key]
                                                ? 'border-border bg-card text-foreground shadow-xs'
                                                : 'border-border/40 bg-transparent text-muted-foreground/70 hover:text-foreground'
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedHarvestMetrics[m.key]
                                            "
                                            :disabled="
                                                selectedHarvestMetrics[m.key] &&
                                                activeHarvestMetricCount <= 1
                                            "
                                            class="h-3.5 w-3.5 rounded accent-emerald-500 disabled:cursor-not-allowed"
                                            @change="toggleHarvestMetric(m.key)"
                                        />
                                        <span
                                            class="h-2.5 w-2.5 rounded-full"
                                            :class="m.dotClass"
                                        />
                                        {{ m.label }}
                                    </label>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <label
                                        v-for="m in CLIMATE_METRIC_OPTIONS"
                                        :key="m.key"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-1 text-xs font-medium transition select-none"
                                        :class="
                                            selectedClimateMetrics[m.key]
                                                ? 'border-border bg-card text-foreground shadow-xs'
                                                : 'border-border/40 bg-transparent text-muted-foreground/70 hover:text-foreground'
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedClimateMetrics[m.key]
                                            "
                                            :disabled="
                                                selectedClimateMetrics[m.key] &&
                                                activeClimateMetricCount <= 1
                                            "
                                            class="h-3.5 w-3.5 rounded accent-emerald-500 disabled:cursor-not-allowed"
                                            @change="toggleClimateMetric(m.key)"
                                        />
                                        <span
                                            class="h-2.5 w-2.5 rounded-full"
                                            :class="m.dotClass"
                                        />
                                        {{ m.label }}
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="
                                    compareTab === 'harvest' &&
                                    !compareHasHarvestData
                                "
                                class="flex items-center justify-center rounded-lg border border-dashed border-border/50 py-10 text-center text-xs text-muted-foreground"
                            >
                                No harvest measurements logged for these cycles
                                yet. Switch to Growing Conditions to compare
                                sensor readings.
                            </div>
                            <div
                                v-else-if="
                                    compareTab === 'climate' &&
                                    !compareHasClimateData
                                "
                                class="flex items-center justify-center rounded-lg border border-dashed border-border/50 py-10 text-center text-xs text-muted-foreground"
                            >
                                No sensor readings recorded yet for the selected
                                cycles.
                            </div>
                            <div v-else class="overflow-x-auto">
                                <div
                                    :style="{
                                        minWidth:
                                            comparedCycles.length > 6
                                                ? `${comparedCycles.length * 110}px`
                                                : '100%',
                                    }"
                                >
                                    <VueApexCharts
                                        :key="compareChartKey"
                                        type="bar"
                                        height="280"
                                        :options="compareChartOptions"
                                        :series="compareChartSeries"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Harvest Results Table -->
                        <div
                            v-if="compareTab === 'harvest'"
                            class="overflow-x-auto rounded-xl border border-border/40"
                        >
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-border/50 bg-muted/30"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Cycle
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Days
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Fruiting Bodies
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Avg Height
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Avg Cap Size
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in comparedCycles"
                                        :key="row.id"
                                        class="border-b border-border/30 transition-colors hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <Link
                                                    :href="cyclesApi.show.url(row.id)"
                                                    class="font-medium whitespace-nowrap text-foreground hover:text-emerald-500 hover:underline"
                                                    @click="closeCompareModal"
                                                >
                                                    {{ row.name }}
                                                </Link>
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
                                                    :class="
                                                        row.status === 'active'
                                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                            : 'bg-blue-500/15 text-blue-600 dark:text-blue-400'
                                                    "
                                                >
                                                    {{ row.status }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-0.5 text-xs text-muted-foreground"
                                            >
                                                {{ row.mushroom_variety }} ·
                                                {{ row.substrate_type }}
                                            </p>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap text-muted-foreground"
                                        >
                                            {{ row.day_count }}d
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap"
                                            :class="
                                                highestHarvestValue !== null &&
                                                row.harvest
                                                    .total_fruiting_bodies ===
                                                    highestHarvestValue
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{
                                                row.harvest
                                                    .total_fruiting_bodies !==
                                                null
                                                    ? row.harvest
                                                          .total_fruiting_bodies
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap"
                                        >
                                            {{
                                                row.harvest.avg_height_cm !==
                                                null
                                                    ? `${row.harvest.avg_height_cm} cm`
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap"
                                        >
                                            {{
                                                row.harvest
                                                    .avg_cap_diameter_cm !==
                                                null
                                                    ? `${row.harvest.avg_cap_diameter_cm} cm`
                                                    : '—'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Growing Conditions Table -->
                        <div
                            v-else
                            class="overflow-x-auto rounded-xl border border-border/40"
                        >
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-border/50 bg-muted/30"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Cycle
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Days
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Avg Temp
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Avg Humidity
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Avg CO2
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Avg Moisture
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right text-xs font-semibold tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                        >
                                            Out of Range
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in comparedCycles"
                                        :key="row.id"
                                        class="border-b border-border/30 transition-colors hover:bg-muted/20"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <Link
                                                    :href="cyclesApi.show.url(row.id)"
                                                    class="font-medium whitespace-nowrap text-foreground hover:text-emerald-500 hover:underline"
                                                    @click="closeCompareModal"
                                                >
                                                    {{ row.name }}
                                                </Link>
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
                                                    :class="
                                                        row.status === 'active'
                                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                            : 'bg-blue-500/15 text-blue-600 dark:text-blue-400'
                                                    "
                                                >
                                                    {{ row.status }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-0.5 text-xs text-muted-foreground"
                                            >
                                                {{ row.mushroom_variety }} ·
                                                {{ row.substrate_type }}
                                            </p>
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap text-muted-foreground"
                                        >
                                            {{ row.day_count }}d
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap"
                                        >
                                            {{
                                                row.averages.temperature !==
                                                null
                                                    ? `${row.averages.temperature}°C`
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap"
                                        >
                                            {{
                                                row.averages.humidity !== null
                                                    ? `${row.averages.humidity}%`
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap"
                                        >
                                            {{
                                                row.averages.co2 !== null
                                                    ? `${Math.round(row.averages.co2)} ppm`
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono whitespace-nowrap"
                                        >
                                            {{
                                                row.averages.soil_moisture !==
                                                null
                                                    ? `${row.averages.soil_moisture}%`
                                                    : '—'
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right whitespace-nowrap"
                                        >
                                            <template
                                                v-if="row.readings_count > 0"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 font-mono text-xs font-semibold"
                                                    :class="
                                                        lowestBreachesValue !==
                                                            null &&
                                                        row.breaches.total ===
                                                            lowestBreachesValue
                                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                            : row.breaches
                                                                    .total > 0
                                                              ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                                              : 'bg-muted text-muted-foreground'
                                                    "
                                                    :title="`Temp: ${row.breaches.temperature}, Humidity: ${row.breaches.humidity}, CO2: ${row.breaches.co2}, Moisture: ${row.breaches.soil_moisture}`"
                                                >
                                                    {{ row.breaches.total }}
                                                </span>
                                                <p
                                                    class="mt-0.5 font-mono text-[10px] text-muted-foreground"
                                                >
                                                    T:{{
                                                        row.breaches.temperature
                                                    }}
                                                    · RH:{{
                                                        row.breaches.humidity
                                                    }}
                                                    · CO2:{{
                                                        row.breaches.co2
                                                    }}
                                                    · M:{{
                                                        row.breaches
                                                            .soil_moisture
                                                    }}
                                                </p>
                                            </template>
                                            <span
                                                v-else
                                                class="text-xs text-muted-foreground"
                                            >
                                                No data
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </Teleport>

    <ConfirmDialog
        :open="pendingCancelCycle !== null"
        title="Cancel Growing Cycle?"
        :description="
            pendingCancelCycle
                ? `Cancel “${pendingCancelCycle.name}”? This cannot be undone.`
                : ''
        "
        confirm-label="Cancel Cycle"
        variant="danger"
        :loading="actioning !== null"
        @confirm="confirmCancelCycle"
        @cancel="pendingCancelCycle = null"
    />
</template>
