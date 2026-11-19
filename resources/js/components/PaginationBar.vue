<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    total?: number;
    unit?: string;
    loading?: boolean;
}>();

const emit = defineEmits<{
    change: [page: number];
}>();

const jumpInput = ref<number | null>(null);

watch(
    () => props.currentPage,
    () => {
        jumpInput.value = null;
    },
);

/**
 * Windowed page list: always shows first, last and 2 pages around the current one,
 * collapsing gaps into ellipses (e.g. 1 … 8 9 [10] 11 12 … 40).
 */
const pages = computed<(number | '…')[]>(() => {
    const last = props.lastPage;
    const current = props.currentPage;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const set = new Set<number>([1, last]);

    for (let p = current - 2; p <= current + 2; p++) {
        if (p >= 1 && p <= last) {
            set.add(p);
        }
    }

    const sorted = [...set].sort((a, b) => a - b);
    const result: (number | '…')[] = [];

    sorted.forEach((p, idx) => {
        if (idx > 0 && p - sorted[idx - 1] > 1) {
            result.push('…');
        }

        result.push(p);
    });

    return result;
});

function go(page: number) {
    const target = Math.min(Math.max(1, Math.trunc(page)), props.lastPage);

    if (target === props.currentPage || props.loading) {
        return;
    }

    emit('change', target);
}

function submitJump() {
    if (jumpInput.value === null || Number.isNaN(jumpInput.value)) {
        return;
    }

    go(jumpInput.value);
    jumpInput.value = null;
}

const buttonBase =
    'flex h-8 min-w-8 items-center justify-center rounded-lg border border-border/50 px-2 text-sm text-muted-foreground transition-all hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-40';
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex flex-col gap-3 border-t border-border/50 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
    >
        <p class="text-sm text-muted-foreground">
            Page <span class="font-semibold">{{ currentPage }}</span> of
            {{ lastPage }}
            <template v-if="total !== undefined">
                · {{ total.toLocaleString() }} {{ unit ?? 'records' }}
            </template>
        </p>

        <div class="flex flex-wrap items-center gap-1.5">
            <button
                type="button"
                title="First page"
                :class="buttonBase"
                :disabled="currentPage <= 1 || loading"
                @click="go(1)"
            >
                <ChevronsLeft class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Previous page"
                :class="buttonBase"
                :disabled="currentPage <= 1 || loading"
                @click="go(currentPage - 1)"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>

            <template v-for="(p, idx) in pages" :key="`${p}-${idx}`">
                <span
                    v-if="p === '…'"
                    class="px-1 text-sm text-muted-foreground select-none"
                    >…</span
                >
                <button
                    v-else
                    type="button"
                    :class="[
                        buttonBase,
                        p === currentPage
                            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary'
                            : '',
                    ]"
                    :disabled="loading"
                    :aria-current="p === currentPage ? 'page' : undefined"
                    @click="go(p)"
                >
                    {{ p }}
                </button>
            </template>

            <button
                type="button"
                title="Next page"
                :class="buttonBase"
                :disabled="currentPage >= lastPage || loading"
                @click="go(currentPage + 1)"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
            <button
                type="button"
                title="Last page"
                :class="buttonBase"
                :disabled="currentPage >= lastPage || loading"
                @click="go(lastPage)"
            >
                <ChevronsRight class="h-4 w-4" />
            </button>

            <form
                class="ml-1 flex items-center gap-1.5"
                @submit.prevent="submitJump"
            >
                <label class="sr-only" for="pagination-jump">Go to page</label>
                <input
                    id="pagination-jump"
                    v-model.number="jumpInput"
                    type="number"
                    min="1"
                    :max="lastPage"
                    placeholder="Go to"
                    class="h-8 w-20 rounded-lg border border-border/50 bg-background px-2 text-sm focus:ring-2 focus:ring-primary/50 focus:outline-none"
                />
                <button
                    type="submit"
                    :class="buttonBase"
                    :disabled="jumpInput === null || loading"
                >
                    Go
                </button>
            </form>
        </div>
    </div>
</template>
