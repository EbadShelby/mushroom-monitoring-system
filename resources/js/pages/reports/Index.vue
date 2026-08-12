<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    FileText,
    Download,
    CalendarDays,
    FlaskConical,
    Search,
} from '@lucide/vue';
import { ref, computed } from 'vue';
import * as reports from '@/routes/reports';
import type { GrowingCycle } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reports', href: reports.index() }],
    },
});

const props = defineProps<{
    cycles: Pick<
        GrowingCycle,
        | 'id'
        | 'name'
        | 'mushroom_variety'
        | 'start_date'
        | 'end_date'
        | 'status'
        | 'created_at'
    >[];
}>();

const searchQuery = ref('');

const filteredCycles = computed(() => {
    if (!searchQuery.value) {
        return props.cycles;
    }

    const q = searchQuery.value.toLowerCase();

    return props.cycles.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            (c.mushroom_variety ?? '').toLowerCase().includes(q),
    );
});

function formatDate(dateStr: string | null) {
    if (!dateStr) {
        return '—';
    }

    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function statusColor(status: string) {
    switch (status) {
        case 'active':
            return 'bg-emerald-100 text-emerald-700 border-emerald-600/20 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30';
        case 'completed':
            return 'bg-blue-100 text-blue-700 border-blue-600/20 dark:bg-blue-500/20 dark:text-blue-400 dark:border-blue-500/30';
        case 'cancelled':
            return 'bg-red-100 text-red-700 border-red-600/20 dark:bg-red-500/20 dark:text-red-400 dark:border-red-500/30';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-600/20 dark:bg-slate-500/20 dark:text-slate-400 dark:border-slate-500/30';
    }
}
</script>

<template>
    <Head title="Cycle Reports" />

    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold text-foreground dark:text-white">
                    Cycle Reports
                </h1>
                <p
                    class="mt-1 text-sm text-muted-foreground dark:text-slate-400"
                >
                    Generate and download PDF reports for your growing cycles.
                </p>
            </div>

            <div class="relative w-full sm:w-64">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground dark:text-slate-400"
                />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search cycles..."
                    class="w-full rounded-xl border border-slate-700 bg-card/50 py-2 pr-4 pl-10 text-sm text-foreground placeholder-slate-500 focus:border-emerald-500 focus:outline-none dark:bg-slate-800/50 dark:text-white"
                />
            </div>
        </div>

        <div
            v-if="filteredCycles.length === 0"
            class="flex flex-col items-center justify-center rounded-2xl border border-border/50 bg-card/50 py-20 text-center backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-800/50"
        >
            <div
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/60 dark:bg-slate-700/60"
            >
                <FileText class="h-8 w-8 text-slate-500" />
            </div>
            <p class="mt-4 font-semibold text-foreground dark:text-white">
                No cycles found
            </p>
            <p class="text-sm text-muted-foreground dark:text-slate-400">
                Adjust your search or start a new cycle.
            </p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="cycle in filteredCycles"
                :key="cycle.id"
                class="flex flex-col rounded-2xl border border-border/50 bg-card/50 p-5 shadow-sm backdrop-blur-sm transition-all hover:bg-muted hover:shadow-lg dark:border-slate-700/50 dark:bg-slate-800/50 dark:hover:bg-slate-800/80"
            >
                <!-- Card Header -->
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h3
                            class="line-clamp-1 font-bold text-foreground dark:text-white"
                            :title="cycle.name"
                        >
                            {{ cycle.name }}
                        </h3>
                        <div
                            class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground dark:text-slate-400"
                        >
                            <FlaskConical class="h-3 w-3" />
                            <span class="truncate">{{
                                cycle.mushroom_variety || 'Oyster Mushroom'
                            }}</span>
                        </div>
                    </div>
                    <span
                        class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                        :class="statusColor(cycle.status)"
                    >
                        {{ cycle.status }}
                    </span>
                </div>

                <!-- Card Body -->
                <div class="mb-6 space-y-2 text-sm">
                    <div
                        class="flex items-center gap-2 text-foreground/80 dark:text-slate-300"
                    >
                        <CalendarDays class="h-4 w-4 text-slate-500" />
                        <span class="text-slate-500">Started:</span>
                        <span>{{ formatDate(cycle.start_date) }}</span>
                    </div>
                    <div
                        class="flex items-center gap-2 text-foreground/80 dark:text-slate-300"
                    >
                        <CalendarDays class="h-4 w-4 text-slate-500" />
                        <span class="text-slate-500">Ended:</span>
                        <span>{{
                            cycle.end_date
                                ? formatDate(cycle.end_date)
                                : 'Ongoing'
                        }}</span>
                    </div>
                </div>

                <!-- Card Footer (Button) -->
                <div
                    class="mt-auto border-t border-border/50 pt-4 dark:border-slate-700/50"
                >
                    <a
                        :href="reports.show.url({ cycle: cycle.id })"
                        target="_blank"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600/10 px-4 py-2.5 text-sm font-semibold text-emerald-500 transition-colors hover:bg-emerald-600 hover:text-foreground dark:text-white"
                    >
                        <Download class="h-4 w-4" />
                        Generate PDF Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
