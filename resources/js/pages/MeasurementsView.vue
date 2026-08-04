<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Ruler, Plus, X, ListTodo, Activity } from '@lucide/vue';
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
        growing_cycle_id: props.activeCycles.length > 0 ? String(props.activeCycles[0].id) : '',
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
    if (!confirm('Are you sure you want to delete this measurement?')) return;
    try {
        await axios.delete(`/api/measurements/${id}`);
        toast.success('Measurement deleted');
        router.reload({ only: ['measurements'] });
    } catch (error) {
        toast.error('Failed to delete measurement');
    }
}
</script>

<template>
    <Head title="Measurements" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                        Measurements & Harvests
                    </h1>
                    <p class="mt-1 text-muted-foreground">Log daily measurements and harvest yields.</p>
                </div>
                <button
                    @click="openModal"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition-all active:scale-95"
                >
                    <Plus class="h-4 w-4" />
                    Log Measurement
                </button>
            </div>

            <!-- Measurements Table -->
            <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md shadow-sm overflow-hidden">
                <div class="p-6 pb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-amber-500/10 p-2 text-amber-500 shadow-inner">
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
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Cycle</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">Flush</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Weight (g)</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Count</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Logger</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!measurements.data.length">
                                <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">No measurements logged yet.</td>
                            </tr>
                            <tr
                                v-for="m in measurements.data"
                                :key="m.id"
                                class="border-b border-border/30 transition-colors hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ new Date(m.observed_date).toLocaleDateString() }}</td>
                                <td class="px-4 py-3 font-medium text-foreground">{{ m.growing_cycle?.name }}</td>
                                <td class="px-4 py-3 text-center"><span class="bg-muted px-2 py-0.5 rounded-full text-xs">{{ m.flush_number }}</span></td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-500 font-bold">{{ m.weight_g ? m.weight_g : '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ m.fruiting_body_count || '-' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ m.user?.name }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="deleteMeasurement(m.id)" class="text-xs text-destructive hover:underline">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Modal for Logging Measurement -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm p-4">
        <div class="w-full max-w-lg rounded-2xl border border-border/50 bg-card shadow-lg flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between border-b border-border/50 p-4">
                <h3 class="text-lg font-semibold flex items-center gap-2"><Ruler class="h-5 w-5 text-primary" /> Log Measurement / Harvest</h3>
                <button @click="closeModal" class="rounded-full p-1 hover:bg-muted"><X class="h-5 w-5" /></button>
            </div>
            <div class="p-6 space-y-4 overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1 col-span-2">
                        <label class="text-xs font-medium uppercase text-muted-foreground">Growing Cycle</label>
                        <select v-model="form.growing_cycle_id" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                            <option value="" disabled>Select Cycle</option>
                            <option v-for="cycle in activeCycles" :key="cycle.id" :value="cycle.id">{{ cycle.name }}</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-medium uppercase text-muted-foreground">Date</label>
                        <input type="date" v-model="form.observed_date" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-medium uppercase text-muted-foreground">Flush Number</label>
                        <input type="number" min="1" v-model="form.flush_number" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" />
                    </div>
                </div>

                <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4 space-y-4 mt-4">
                    <h4 class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        <Activity class="h-4 w-4" /> Harvest Data
                    </h4>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-medium uppercase text-muted-foreground">Weight (g)</label>
                            <input type="number" step="0.1" placeholder="e.g. 500" v-model="form.weight_g" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium uppercase text-muted-foreground">Fruiting Body Count</label>
                            <input type="number" placeholder="Optional" v-model="form.fruiting_body_count" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="space-y-1">
                        <label class="text-xs font-medium uppercase text-muted-foreground">Avg Height (cm)</label>
                        <input type="number" step="0.1" placeholder="Optional" v-model="form.height_cm" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium uppercase text-muted-foreground">Avg Cap Diameter (cm)</label>
                        <input type="number" step="0.1" placeholder="Optional" v-model="form.cap_diameter_cm" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" />
                    </div>
                </div>

                <div class="space-y-1 mt-4">
                    <label class="text-xs font-medium uppercase text-muted-foreground">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-border/50 bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50" placeholder="Observations..."></textarea>
                </div>
            </div>
            
            <div class="border-t border-border/50 p-4 flex justify-end gap-2 bg-muted/20 rounded-b-2xl">
                <button @click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium hover:bg-muted transition-colors">Cancel</button>
                <button @click="submitMeasurement" :disabled="isSubmitting" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 transition-all disabled:opacity-50">
                    {{ isSubmitting ? 'Saving...' : 'Save Measurement' }}
                </button>
            </div>
        </div>
    </div>
</template>
