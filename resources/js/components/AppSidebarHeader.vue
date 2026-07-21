<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useSensorStore } from '@/stores/useSensorStore';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const store = useSensorStore();
const page = usePage();
const auth = computed(() => (page.props.auth as any)?.user);
const roleLabel = computed(() => {
    const role = auth.value?.role;
    if (role === 'admin') { return 'Admin'; }
    if (role === 'faculty') { return 'Faculty'; }
    return 'Student';
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Right side: firebase live indicator + role badge -->
        <div class="flex items-center gap-3">
            <!-- Firebase live dot -->
            <div
                class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                :class="
                    store.isLoading
                        ? 'border-yellow-500/30 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400'
                        : store.isConnected
                          ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                          : 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'
                "
            >
                <span
                    class="size-1.5 rounded-full"
                    :class="
                        store.isLoading
                            ? 'animate-pulse bg-yellow-500'
                            : store.isConnected
                              ? 'animate-pulse bg-emerald-500'
                              : 'bg-red-500'
                    "
                />
                <span v-if="store.isLoading">Connecting…</span>
                <span v-else-if="store.isConnected">Live</span>
                <span v-else>Offline</span>
            </div>

            <!-- Role badge -->
            <span
                v-if="auth"
                class="hidden rounded-full border border-border px-2.5 py-0.5 text-[11px] font-semibold text-muted-foreground sm:inline-block"
            >
                {{ roleLabel }}
            </span>
        </div>
    </header>
</template>
