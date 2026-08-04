<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';
import type { GrowingCycle, Paginated } from '@/types';
import * as cyclesApi from '@/routes/cycles';
import {
    Sprout,
    Plus,
    ChevronLeft,
    ChevronRight,
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
    Microscope,
    Layers,
    ArrowLeftRight,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Growing Cycles', href: cyclesApi.index() }],
    },
});

const props = defineProps<{
    cycles: Paginated<GrowingCycle>;
    filters: { status?: string | null };
}>();

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
const showModal = ref(false);
const creating = ref(false);
const form = ref({
    name: '',
    mushroom_variety: 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
    substrate_type: '',
    start_date: new Date().toISOString().split('T')[0],
    growing_stage: 'colonization' as 'colonization' | 'fruiting',
    notes: '',
});

const formErrors = ref<Record<string, string>>({});

async function createCycle() {
    formErrors.value = {};
    creating.value = true;
    try {
        await axios.post('/api/cycles', form.value);
        toast.success('Growing cycle created!');
        showModal.value = false;
        form.value = {
            name: '',
            mushroom_variety: 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
            substrate_type: '',
            start_date: new Date().toISOString().split('T')[0],
            growing_stage: 'colonization',
            notes: '',
        };
        router.reload({ only: ['cycles'] });
    } catch (e: any) {
        if (e.response?.status === 422) {
            formErrors.value = e.response.data.errors ?? {};
        } else {
            toast.error('Failed to create cycle');
        }
    } finally {
        creating.value = false;
    }
}

// ── Stage switching ────────────────────────────────────────────────────────────
const switchingStage = ref<number | null>(null);

async function switchStage(cycle: GrowingCycle) {
    const nextStage = cycle.growing_stage === 'colonization' ? 'fruiting' : 'colonization';
    const stageLabel = nextStage === 'fruiting' ? 'Fruiting' : 'Colonization';

    if (!confirm(`Switch "${cycle.name}" to ${stageLabel} stage? This changes the active thresholds and automation logic.`)) {
        return;
    }
    switchingStage.value = cycle.id;
    try {
        await axios.post(`/api/cycles/${cycle.id}/switch-stage`, { growing_stage: nextStage });
        toast.success(`Switched to ${stageLabel} stage — thresholds updated.`);
        router.reload({ only: ['cycles'] });
    } catch (e: any) {
        toast.error(e.response?.data?.error ?? 'Failed to switch stage');
    } finally {
        switchingStage.value = null;
    }
}

// ── End / Cancel cycle ─────────────────────────────────────────────────────────
const actioning = ref<number | null>(null);

async function endCycle(cycle: GrowingCycle) {
    if (!confirm(`Mark "${cycle.name}" as completed?`)) {
        return;
    }
    actioning.value = cycle.id;
    try {
        await axios.post(`/api/cycles/${cycle.id}/end`);
        toast.success('Cycle marked as completed');
        router.reload({ only: ['cycles'] });
    } catch {
        toast.error('Failed to end cycle');
    } finally {
        actioning.value = null;
    }
}

async function cancelCycle(cycle: GrowingCycle) {
    if (!confirm(`Cancel "${cycle.name}"? This cannot be undone.`)) {
        return;
    }
    actioning.value = cycle.id;
    try {
        await axios.delete(`/api/cycles/${cycle.id}`);
        toast.success('Cycle cancelled');
        router.reload({ only: ['cycles'] });
    } catch {
        toast.error('Failed to cancel cycle');
    } finally {
        actioning.value = null;
    }
}

// ── Pagination ─────────────────────────────────────────────────────────────────
function goToPage(url: string | null) {
    if (!url) {
        return;
    }
    router.visit(url, { preserveScroll: true });
}

// ── Status helpers ─────────────────────────────────────────────────────────────
const statusConfig = {
    active:    { label: 'Active',    class: 'bg-emerald-500/20 text-emerald-300 ring-emerald-500/30', icon: Clock },
    completed: { label: 'Completed', class: 'bg-blue-500/20 text-blue-300 ring-blue-500/30',         icon: CheckCircle2 },
    cancelled: { label: 'Cancelled', class: 'bg-red-500/20 text-red-300 ring-red-500/30',            icon: XCircle },
} as const;

function statusCfg(status: string) {
    return statusConfig[status as keyof typeof statusConfig] ?? statusConfig.active;
}

// ── Stage helpers ──────────────────────────────────────────────────────────────
const stageConfig = {
    colonization: {
        label: 'Colonization',
        class: 'bg-amber-500/20 text-amber-300 ring-amber-500/30',
        icon: Microscope,
        description: 'Mycelium spreading through substrate. Dark, warm, high CO₂ OK.',
    },
    fruiting: {
        label: 'Fruiting',
        class: 'bg-purple-500/20 text-purple-300 ring-purple-500/30',
        icon: Layers,
        description: 'Mushrooms forming. Needs light, fresh air, high humidity.',
    },
} as const;

function stageCfg(stage: string) {
    return stageConfig[stage as keyof typeof stageConfig] ?? stageConfig.colonization;
}

function formatDate(d: string | null) {
    if (!d) {
        return '—';
    }
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head title="Growing Cycles" />

    <div class="space-y-6">
        <!-- Page header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Growing Cycles</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Manage oyster mushroom cultivation cycles
                </p>
            </div>
            <button
                id="create-cycle-btn"
                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/40 transition hover:bg-emerald-500 active:scale-95"
                @click="showModal = true"
            >
                <Plus class="h-4 w-4" />
                New Cycle
            </button>
        </div>

        <!-- Stage legend -->
        <div class="flex flex-wrap gap-3">
            <div
                v-for="(cfg, key) in stageConfig"
                :key="key"
                class="flex items-start gap-2.5 rounded-xl border border-slate-700/50 bg-slate-800/40 px-4 py-3 backdrop-blur-sm"
            >
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg"
                    :class="key === 'colonization' ? 'bg-amber-500/20' : 'bg-purple-500/20'">
                    <component :is="cfg.icon" class="h-3.5 w-3.5" :class="key === 'colonization' ? 'text-amber-400' : 'text-purple-400'" />
                </div>
                <div>
                    <p class="text-xs font-semibold" :class="key === 'colonization' ? 'text-amber-300' : 'text-purple-300'">
                        {{ cfg.label }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ cfg.description }}</p>
                </div>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-700/50 bg-slate-800/50 p-4 backdrop-blur-sm">
            <Filter class="h-4 w-4 text-slate-400" />
            <span class="text-sm text-slate-400">Filter by status:</span>
            <div class="flex gap-2">
                <button
                    v-for="opt in ['', 'active', 'completed', 'cancelled']"
                    :key="opt"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold capitalize transition"
                    :class="statusFilter === opt
                        ? 'bg-emerald-600 text-white'
                        : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                    @click="statusFilter = opt; applyFilter()"
                >
                    {{ opt || 'All' }}
                </button>
            </div>
            <button
                v-if="statusFilter"
                class="ml-auto flex items-center gap-1 text-xs text-slate-400 hover:text-white"
                @click="clearFilter"
            >
                <X class="h-3 w-3" /> Clear
            </button>
        </div>

        <!-- Cycles table -->
        <div class="overflow-hidden rounded-2xl border border-slate-700/50 bg-slate-800/50 backdrop-blur-sm">
            <div v-if="cycles.data.length === 0" class="flex flex-col items-center gap-4 py-16 text-center">
                <div class="rounded-full bg-slate-700/60 p-5">
                    <Sprout class="h-10 w-10 text-emerald-400" />
                </div>
                <div>
                    <p class="font-semibold text-white">No cycles found</p>
                    <p class="mt-1 text-sm text-slate-400">Start your first growing cycle to get started.</p>
                </div>
                <button
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    @click="showModal = true"
                >
                    Create First Cycle
                </button>
            </div>

            <table v-else class="w-full">
                <thead>
                    <tr class="border-b border-slate-700/50 bg-slate-900/50">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Cycle</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Variety</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Stage</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Start Date</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">End Date</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    <tr
                        v-for="cycle in cycles.data"
                        :key="cycle.id"
                        class="group transition hover:bg-slate-700/20"
                    >
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10">
                                    <Sprout class="h-4 w-4 text-emerald-400" />
                                </div>
                                <div>
                                    <p class="font-semibold text-white">{{ cycle.name }}</p>
                                    <p v-if="cycle.day_count" class="text-xs text-slate-400">Day {{ cycle.day_count }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-300">{{ cycle.mushroom_variety }}</td>
                        <td class="px-5 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="stageCfg(cycle.growing_stage).class"
                                :title="stageCfg(cycle.growing_stage).description"
                            >
                                <component :is="stageCfg(cycle.growing_stage).icon" class="h-3 w-3" />
                                {{ stageCfg(cycle.growing_stage).label }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-300">
                            <div class="flex items-center gap-1.5">
                                <CalendarDays class="h-3.5 w-3.5 text-slate-500" />
                                {{ formatDate(cycle.start_date) }}
                            </div>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-300">{{ formatDate(cycle.end_date) }}</td>
                        <td class="px-5 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusCfg(cycle.status).class"
                            >
                                <component :is="statusCfg(cycle.status).icon" class="h-3 w-3" />
                                {{ statusCfg(cycle.status).label }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <Link
                                    :href="cyclesApi.show(cycle.id)"
                                    class="flex items-center gap-1.5 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-slate-600"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    View
                                </Link>
                                <!-- Switch Stage button (active cycles only) -->
                                <button
                                    v-if="cycle.status === 'active'"
                                    :disabled="switchingStage === cycle.id"
                                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium ring-1 transition disabled:opacity-50"
                                    :class="cycle.growing_stage === 'colonization'
                                        ? 'bg-purple-600/20 text-purple-300 ring-purple-500/30 hover:bg-purple-600/30'
                                        : 'bg-amber-600/20 text-amber-300 ring-amber-500/30 hover:bg-amber-600/30'"
                                    :title="`Switch to ${cycle.growing_stage === 'colonization' ? 'Fruiting' : 'Colonization'} stage`"
                                    @click="switchStage(cycle)"
                                >
                                    <span v-if="switchingStage === cycle.id" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    <ArrowLeftRight v-else class="h-3.5 w-3.5" />
                                    → {{ cycle.growing_stage === 'colonization' ? 'Fruiting' : 'Colonization' }}
                                </button>
                                <button
                                    v-if="cycle.status === 'active'"
                                    :disabled="actioning === cycle.id"
                                    class="flex items-center gap-1.5 rounded-lg bg-blue-600/20 px-3 py-1.5 text-xs font-medium text-blue-300 ring-1 ring-blue-500/30 transition hover:bg-blue-600/30 disabled:opacity-50"
                                    @click="endCycle(cycle)"
                                >
                                    <SquareCheck class="h-3.5 w-3.5" />
                                    End
                                </button>
                                <button
                                    v-if="cycle.status === 'active'"
                                    :disabled="actioning === cycle.id"
                                    class="flex items-center gap-1.5 rounded-lg bg-red-600/20 px-3 py-1.5 text-xs font-medium text-red-300 ring-1 ring-red-500/30 transition hover:bg-red-600/30 disabled:opacity-50"
                                    @click="cancelCycle(cycle)"
                                >
                                    <Ban class="h-3.5 w-3.5" />
                                    Cancel
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div
                v-if="cycles.last_page > 1"
                class="flex items-center justify-between border-t border-slate-700/50 px-5 py-3"
            >
                <p class="text-xs text-slate-400">
                    Showing {{ cycles.from }}–{{ cycles.to }} of {{ cycles.total }} cycles
                </p>
                <div class="flex gap-1">
                    <button
                        :disabled="cycles.current_page === 1"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-700 disabled:opacity-40"
                        @click="goToPage(cycles.links[0]?.url)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button
                        v-for="link in cycles.links.slice(1, -1)"
                        :key="link.label"
                        :disabled="!link.url"
                        class="min-w-[32px] rounded-lg px-2 py-1 text-xs font-medium transition"
                        :class="link.active
                            ? 'bg-emerald-600 text-white'
                            : 'text-slate-400 hover:bg-slate-700 hover:text-white'"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                    <button
                        :disabled="cycles.current_page === cycles.last_page"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-700 disabled:opacity-40"
                        @click="goToPage(cycles.links[cycles.links.length - 1]?.url)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
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
                style="background: rgba(0,0,0,0.7);"
                @click.self="showModal = false"
            >
                <Transition
                    enter-active-class="transition duration-200"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                >
                    <div
                        v-if="showModal"
                        class="w-full max-w-lg rounded-2xl border border-slate-700/50 bg-slate-900 shadow-2xl"
                    >
                        <!-- Modal header -->
                        <div class="flex items-center justify-between border-b border-slate-700/50 px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10">
                                    <FlaskConical class="h-5 w-5 text-emerald-400" />
                                </div>
                                <h2 class="text-lg font-semibold text-white">New Growing Cycle</h2>
                            </div>
                            <button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-700 hover:text-white" @click="showModal = false">
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Modal body -->
                        <form class="space-y-4 p-6" @submit.prevent="createCycle">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Cycle Name <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g. Batch 2024-A"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                                <p v-if="formErrors.name" class="mt-1 text-xs text-red-400">{{ formErrors.name }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Mushroom Variety <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.mushroom_variety"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                                <p v-if="formErrors.mushroom_variety" class="mt-1 text-xs text-red-400">{{ formErrors.mushroom_variety }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Substrate Type <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.substrate_type"
                                    type="text"
                                    placeholder="e.g. Rice straw, Sawdust"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                                <p v-if="formErrors.substrate_type" class="mt-1 text-xs text-red-400">{{ formErrors.substrate_type }}</p>
                            </div>

                            <!-- Starting Stage -->
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Starting Stage <span class="text-red-400">*</span></label>
                                <div class="grid grid-cols-2 gap-3">
                                    <button
                                        v-for="stage in ['colonization', 'fruiting'] as const"
                                        :key="stage"
                                        type="button"
                                        class="flex items-center gap-2.5 rounded-xl border px-4 py-3 text-left text-sm transition"
                                        :class="form.growing_stage === stage
                                            ? stage === 'colonization'
                                                ? 'border-amber-500/60 bg-amber-500/10 text-amber-300'
                                                : 'border-purple-500/60 bg-purple-500/10 text-purple-300'
                                            : 'border-slate-700 bg-slate-800 text-slate-400 hover:border-slate-600'"
                                        @click="form.growing_stage = stage"
                                    >
                                        <component
                                            :is="stageCfg(stage).icon"
                                            class="h-4 w-4 shrink-0"
                                            :class="form.growing_stage === stage
                                                ? stage === 'colonization' ? 'text-amber-400' : 'text-purple-400'
                                                : 'text-slate-500'"
                                        />
                                        <div>
                                            <p class="font-semibold capitalize">{{ stage }}</p>
                                            <p class="text-xs opacity-70">
                                                {{ stage === 'colonization' ? 'Spawn running phase' : 'Mushroom forming phase' }}
                                            </p>
                                        </div>
                                    </button>
                                </div>
                                <p v-if="formErrors.growing_stage" class="mt-1 text-xs text-red-400">{{ formErrors.growing_stage }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Start Date <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    style="color-scheme: dark"
                                />
                                <p v-if="formErrors.start_date" class="mt-1 text-xs text-red-400">{{ formErrors.start_date }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-300">Notes</label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Optional notes about this batch..."
                                    class="w-full resize-none rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                                    @click="showModal = false"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="creating"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:bg-emerald-500 disabled:opacity-60"
                                >
                                    <span v-if="creating" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                    {{ creating ? 'Creating...' : 'Create Cycle' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
