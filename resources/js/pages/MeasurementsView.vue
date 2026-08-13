<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Ruler, Plus, X, ListTodo, Activity } from '@lucide/vue';
import axios from 'axios';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import type { Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Measurements', href: '/measurements' }],
    },
});

interface Measurement {
    id: number;
    growing_cycle_id: number;
    user_id: number;
    observed_date: string;
    flush_number: number;
    weight_g: number | null;
    height_cm: number | null;
    cap_diameter_cm: number | null;
    fruiting_body_count: number | null;
    photo_path: string | null;
    notes: string | null;
    growing_cycle?: { id: number; name: string };
    user?: { id: number; name: string };
}

const props = defineProps<{
    measurements: Paginated<Measurement>;
    activeCycles: { id: number; name: string }[];
}>();

const page = usePage();
const userRole = computed(
    () => (page.props.auth as any)?.user?.role ?? 'student',
);

const isModalOpen = ref(false);
const isSubmitting = ref(false);

const form = ref({
    growing_cycle_id: '',
    observed_date: new Date().toISOString().split('T')[0],
    flush_number: 1,
    weight_g: null as number | null,
    height_cm: null as number | null,
    cap_diameter_cm: null as number | null,
    fruiting_body_count: null as number | null,
    notes: '',
});

function openModal() {
    form.value = {
        growing_cycle_id:
            props.activeCycles.length > 0
                ? String(props.activeCycles[0].id)
                : '',
        observed_date: new Date().toISOString().split('T')[0],
        flush_number: 1,
        weight_g: null,
        height_cm: null,
        cap_diameter_cm: null,
        fruiting_body_count: null,
        notes: '',
    };
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
}

async function submitMeasurement() {
    if (!form.value.growing_cycle_id) {
        toast.error('Please select a growing cycle');

        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post('/api/measurements', form.value);
        toast.success('Measurement logged successfully');
        closeModal();
        router.reload({ only: ['measurements'] });
    } catch (error: any) {
        console.error(error);
        toast.error('Failed to log measurement. Check your inputs.');
    } finally {
        isSubmitting.value = false;
    }
}

async function deleteMeasurement(id: number) {
    if (!confirm('Are you sure you want to delete this measurement?')) {
        return;
    }

    try {
        await axios.delete(`/api/measurements/${id}`);
        toast.success('Measurement deleted');
        router.reload({ only: ['measurements'] });
    } catch {
        toast.error('Failed to delete measurement');
    }
}
</script>

<template>
    <Head title="Measurements" />

    <div
        class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10"
    >
        <div
            class="z-10 mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6"
        >
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent"
                    >
                        Measurements & Harvests
                    </h1>
                    <p class="mt-1 text-muted-foreground">
                        <template v-if="userRole === 'student'">View logged measurements and harvest data.</template>
                        <template v-else>Log daily measurements and harvest yields.</template>
                    </p>
                </div>
                <button
                    v-if="userRole !== 'student'"
                    @click="openModal"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90 active:scale-95"
                >
                    <Plus class="h-4 w-4" />
                    Log Measurement
                </button>
            </div>

            <!-- Measurements Table -->
            <div
                class="overflow-hidden rounded-2xl border border-border/50 bg-card/60 shadow-sm backdrop-blur-md"
            >
                <div class="flex items-center gap-2 p-6 pb-4">
                    <div
                        class="rounded-lg bg-amber-500/10 p-2 text-amber-500 shadow-inner"
                    >
                        <ListTodo class="h-4 w-4" />
                    </div>
                    <h2 class="font-semibold text-muted-foreground">
                        RECENT MEASUREMENTS
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 bg-muted/30">
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Date
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Cycle
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Flush
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Weight (g)
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Count
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Logger
                                </th>
                                <th
                                    v-if="userRole !== 'student'"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!measurements.data.length">
                                <td
                                    :colspan="userRole !== 'student' ? 7 : 6"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No measurements logged yet.
                                </td>
                            </tr>
                            <tr
                                v-for="m in measurements.data"
                                :key="m.id"
                                class="border-b border-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{
                                        new Date(
                                            m.observed_date,
                                        ).toLocaleDateString()
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ m.growing_cycle?.name }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                        >{{ m.flush_number }}</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-bold text-emerald-500"
                                >
                                    {{ m.weight_g ? m.weight_g : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ m.fruiting_body_count || '-' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ m.user?.name }}
                                </td>
                                <td
                                    v-if="userRole !== 'student'"
                                    class="px-4 py-3 text-right"
                                >
                                    <button
                                        @click="deleteMeasurement(m.id)"
                                        class="text-xs text-destructive hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Logging Measurement -->
    <div
        v-if="isModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-border/50 bg-card shadow-lg"
        >
            <div
                class="flex items-center justify-between border-b border-border/50 p-4"
            >
                <h3 class="flex items-center gap-2 text-lg font-semibold">
                    <Ruler class="h-5 w-5 text-primary" /> Log Measurement /
                    Harvest
                </h3>
                <button
                    @click="closeModal"
                    class="rounded-full p-1 hover:bg-muted"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
            <div class="space-y-4 overflow-y-auto p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 space-y-1">
                        <label
                            class="text-xs font-medium text-muted-foreground uppercase"
                            >Growing Cycle</label
                        >
                        <select
                            v-model="form.growing_cycle_id"
                            class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        >
                            <option value="" disabled>Select Cycle</option>
                            <option
                                v-for="cycle in activeCycles"
                                :key="cycle.id"
                                :value="cycle.id"
                            >
                                {{ cycle.name }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label
                            class="text-xs font-medium text-muted-foreground uppercase"
                            >Date</label
                        >
                        <input
                            type="date"
                            v-model="form.observed_date"
                            class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1">
                        <label
                            class="text-xs font-medium text-muted-foreground uppercase"
                            >Flush Number</label
                        >
                        <input
                            type="number"
                            min="1"
                            v-model="form.flush_number"
                            class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                </div>

                <div
                    class="mt-4 space-y-4 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4"
                >
                    <h4
                        class="flex items-center gap-1 text-sm font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        <Activity class="h-4 w-4" /> Harvest Data
                    </h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label
                                class="text-xs font-medium text-muted-foreground uppercase"
                                >Weight (g)</label
                            >
                            <input
                                type="number"
                                step="0.1"
                                placeholder="e.g. 500"
                                v-model="form.weight_g"
                                class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="text-xs font-medium text-muted-foreground uppercase"
                                >Fruiting Body Count</label
                            >
                            <input
                                type="number"
                                placeholder="Optional"
                                v-model="form.fruiting_body_count"
                                class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label
                            class="text-xs font-medium text-muted-foreground uppercase"
                            >Avg Height (cm)</label
                        >
                        <input
                            type="number"
                            step="0.1"
                            placeholder="Optional"
                            v-model="form.height_cm"
                            class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-xs font-medium text-muted-foreground uppercase"
                            >Avg Cap Diameter (cm)</label
                        >
                        <input
                            type="number"
                            step="0.1"
                            placeholder="Optional"
                            v-model="form.cap_diameter_cm"
                            class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="mt-4 space-y-1">
                    <label
                        class="text-xs font-medium text-muted-foreground uppercase"
                        >Notes</label
                    >
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                        placeholder="Observations..."
                    ></textarea>
                </div>
            </div>

            <div
                class="flex justify-end gap-2 rounded-b-2xl border-t border-border/50 bg-muted/20 p-4"
            >
                <button
                    @click="closeModal"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    Cancel
                </button>
                <button
                    @click="submitMeasurement"
                    :disabled="isSubmitting"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary/90 disabled:opacity-50"
                >
                    {{ isSubmitting ? 'Saving...' : 'Save Measurement' }}
                </button>
            </div>
        </div>
    </div>
</template>
