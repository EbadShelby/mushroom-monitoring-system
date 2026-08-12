<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import {
    ScrollText,
    Filter,
    ChevronLeft,
    ChevronRight,
    ShieldX,
} from '@lucide/vue';
import { ref, computed, onMounted } from 'vue';
import type { UserLog, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'User Logs', href: '/user-logs' }],
    },
});

const props = defineProps<{
    logs?: Paginated<UserLog>;
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role ?? 'student');
const isAdmin = computed(() => userRole.value === 'admin');

// Redirect if not admin
onMounted(() => {
    if (!isAdmin.value) {
        router.visit('/dashboard');
    }
});

const logsData = ref<Paginated<UserLog> | null>(props.logs ?? null);

// Filters
const userIdFilter = ref('');
const fromDate = ref('');
const toDate = ref('');

function applyFilters() {
    router.get(
        '/user-logs',
        {
            ...(userIdFilter.value ? { user_id: userIdFilter.value } : {}),
            ...(fromDate.value ? { from: fromDate.value } : {}),
            ...(toDate.value ? { to: toDate.value } : {}),
        },
        {
            preserveState: true,
            onSuccess: (p) => {
                logsData.value = (p.props as any).logs ?? logsData.value;
            },
        },
    );
}

function goToPage(p: number) {
    router.get(
        '/user-logs',
        {
            page: p,
            ...(userIdFilter.value ? { user_id: userIdFilter.value } : {}),
            ...(fromDate.value ? { from: fromDate.value } : {}),
            ...(toDate.value ? { to: toDate.value } : {}),
        },
        {
            preserveState: true,
            onSuccess: (pg) => {
                logsData.value = (pg.props as any).logs ?? logsData.value;
            },
        },
    );
}
</script>

<template>
    <Head title="User Logs" />

    <div class="relative flex h-full min-h-[calc(100vh-theme(spacing.16))] flex-1 flex-col bg-gradient-to-br from-primary/5 via-background to-secondary/10">
        <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col space-y-8 p-4 md:p-8 md:pt-6 z-10">

            <!-- Access Denied -->
            <div v-if="!isAdmin" class="flex flex-col items-center justify-center py-24 text-center">
                <ShieldX class="mb-4 h-16 w-16 text-destructive opacity-50" />
                <h2 class="text-2xl font-bold text-foreground">Access Denied</h2>
                <p class="mt-2 text-muted-foreground">This page is restricted to administrators only.</p>
            </div>

            <template v-else>
                <!-- Header -->
                <div>
                    <h1 class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
                        User Logs
                    </h1>
                    <p class="mt-1 text-muted-foreground">Audit trail of user actions.</p>
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
                            <label for="user-id-filter" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">User ID</label>
                            <input
                                id="user-id-filter"
                                v-model="userIdFilter"
                                type="number"
                                placeholder="e.g. 2"
                                class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50"
                            />
                        </div>
                        <div class="space-y-1">
                            <label for="log-from" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">From</label>
                            <input id="log-from" v-model="fromDate" type="date" class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50" />
                        </div>
                        <div class="space-y-1">
                            <label for="log-to" class="text-xs font-medium uppercase tracking-wider text-muted-foreground">To</label>
                            <input id="log-to" v-model="toDate" type="date" class="w-full rounded-lg border border-border/50 bg-background/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/50" />
                        </div>
                        <div class="flex items-end">
                            <button
                                id="btn-log-filter"
                                @click="applyFilters"
                                class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                            >
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="rounded-2xl border border-border/50 bg-card/60 backdrop-blur-md shadow-sm overflow-hidden">
                    <div class="p-6 pb-4 flex items-center gap-2">
                        <div class="rounded-lg bg-secondary/60 p-2 text-muted-foreground shadow-inner">
                            <ScrollText class="h-4 w-4" />
                        </div>
                        <h2 class="font-semibold text-muted-foreground">
                            ACTIVITY LOG
                            <span v-if="logsData" class="ml-2 text-xs text-muted-foreground/60">({{ logsData.total }} entries)</span>
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border/50 bg-muted/30">
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Action</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Details</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Skeleton -->
                                <tr v-if="!logsData">
                                    <td colspan="6" class="px-4 py-8 text-center">
                                        <div class="animate-pulse space-y-2 max-w-lg mx-auto">
                                            <div class="h-3 w-full rounded bg-muted"></div>
                                            <div class="h-3 w-3/4 rounded bg-muted"></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="log in logsData.data"
                                    :key="log.id"
                                    class="border-b border-border/30 transition-colors hover:bg-muted/20"
                                >
                                    <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                        {{ new Date(log.performed_at).toLocaleString('en-PH') }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-foreground">{{ log.user?.name ?? `User #${log.user_id}` }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary capitalize">
                                            {{ log.user?.role ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-foreground">{{ log.action }}</td>
                                    <td class="px-4 py-3 max-w-xs text-muted-foreground truncate">{{ log.details ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
                                </tr>
                                <tr v-if="logsData && !logsData.data.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No logs found for the selected filters.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logsData && logsData.last_page > 1" class="flex items-center justify-between border-t border-border/50 px-6 py-4">
                        <p class="text-sm text-muted-foreground">Page {{ logsData.current_page }} of {{ logsData.last_page }}</p>
                        <div class="flex items-center gap-2">
                            <button id="btn-log-prev" @click="goToPage(logsData!.current_page - 1)" :disabled="logsData.current_page <= 1"
                                class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed">
                                <ChevronLeft class="h-4 w-4" /> Prev
                            </button>
                            <button id="btn-log-next" @click="goToPage(logsData!.current_page + 1)" :disabled="logsData.current_page >= logsData.last_page"
                                class="flex items-center gap-1 rounded-lg border border-border/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted/50 disabled:opacity-40 disabled:cursor-not-allowed">
                                Next <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>
