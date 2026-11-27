<script setup lang="ts">
import { Loader2 } from '@lucide/vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'danger' | 'primary';
        loading?: boolean;
    }>(),
    {
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        variant: 'danger',
        loading: false,
    },
);

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            @click.self="!loading && emit('cancel')"
        >
            <div
                class="w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900"
            >
                <h3 class="text-base font-bold text-foreground dark:text-white">
                    {{ title }}
                </h3>
                <p
                    class="mt-2 text-sm text-muted-foreground dark:text-slate-400"
                >
                    {{ description }}
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        :disabled="loading"
                        class="rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground disabled:opacity-50 dark:border-slate-700 dark:text-slate-300"
                        @click="emit('cancel')"
                    >
                        {{ cancelLabel }}
                    </button>
                    <button
                        type="button"
                        :disabled="loading"
                        class="flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-60"
                        :class="
                            variant === 'danger'
                                ? 'bg-red-600 hover:bg-red-500'
                                : 'bg-emerald-600 hover:bg-emerald-500'
                        "
                        @click="emit('confirm')"
                    >
                        <Loader2
                            v-if="loading"
                            class="h-4 w-4 animate-spin"
                        />
                        {{ confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
