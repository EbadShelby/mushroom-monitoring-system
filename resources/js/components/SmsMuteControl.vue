<script setup lang="ts">
import { Bell, BellOff, ChevronDown } from '@lucide/vue';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { mute, unmute } from '@/routes/alerts';
import type { SmsMuteStatus } from '@/types/domain';

const props = defineProps<{
    initialStatus?: SmsMuteStatus | null;
    compact?: boolean;
}>();

const status = ref<SmsMuteStatus>(
    props.initialStatus ?? {
        muted: false,
        indefinite: false,
        muted_until: null,
    },
);

watch(
    () => props.initialStatus,
    (next) => {
        if (next) {
            status.value = next;
        }
    },
);

const menuOpen = ref(false);
const busy = ref(false);
const rootRef = ref<HTMLElement | null>(null);
const nowMs = ref(Date.now());

let ticker: ReturnType<typeof setInterval> | null = null;

function startTicker() {
    if (ticker) {
        return;
    }

    ticker = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
}

function stopTicker() {
    if (ticker) {
        clearInterval(ticker);
        ticker = null;
    }
}

const remainingSeconds = computed(() => {
    if (
        !status.value.muted ||
        status.value.indefinite ||
        !status.value.muted_until
    ) {
        return 0;
    }

    const targetMs = Date.parse(status.value.muted_until);

    if (Number.isNaN(targetMs)) {
        return 0;
    }

    return Math.max(0, Math.floor((targetMs - nowMs.value) / 1000));
});

const isMuted = computed(() => {
    if (!status.value.muted) {
        return false;
    }

    if (status.value.indefinite) {
        return true;
    }

    return remainingSeconds.value > 0;
});

watch(
    () => status.value.muted && !status.value.indefinite,
    (needsTick) => {
        if (needsTick) {
            nowMs.value = Date.now();
            startTicker();
        } else {
            stopTicker();
        }
    },
    { immediate: true },
);

const remainingLabel = computed(() => {
    if (status.value.indefinite) {
        return 'Paused';
    }

    const total = remainingSeconds.value;

    if (total <= 0) {
        return '';
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m left`;
    }

    if (minutes > 0) {
        return `${minutes}m left`;
    }

    return `${seconds}s left`;
});

const presets: { label: string; minutes: number | null }[] = [
    { label: '15 minutes', minutes: 15 },
    { label: '30 minutes', minutes: 30 },
    { label: '1 hour', minutes: 60 },
    { label: '2 hours', minutes: 120 },
    { label: 'Until I resume', minutes: null },
];

async function muteFor(minutes: number | null) {
    menuOpen.value = false;
    busy.value = true;

    try {
        const res = await axios.post<{ smsMute: SmsMuteStatus }>(mute.url(), {
            minutes,
        });
        status.value = res.data.smsMute;
        nowMs.value = Date.now();
        toast.success(
            minutes === null
                ? 'SMS alerts paused until resumed.'
                : `SMS alerts paused for ${minutes >= 60 ? `${minutes / 60} hr${minutes > 60 ? 's' : ''}` : `${minutes} min`}.`,
        );
    } catch {
        toast.error('Failed to pause SMS alerts.');
    } finally {
        busy.value = false;
    }
}

async function resumeSms() {
    busy.value = true;

    try {
        const res = await axios.post<{ smsMute: SmsMuteStatus }>(unmute.url());
        status.value = res.data.smsMute;
        toast.success('SMS alerts resumed.');
    } catch {
        toast.error('Failed to resume SMS alerts.');
    } finally {
        busy.value = false;
    }
}

function handleDocumentClick(event: MouseEvent) {
    if (
        menuOpen.value &&
        rootRef.value &&
        !rootRef.value.contains(event.target as Node)
    ) {
        menuOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    stopTicker();
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="relative inline-flex items-center gap-2">
        <!-- Muted state -->
        <template v-if="isMuted">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 font-medium text-amber-600 dark:text-amber-400"
                :class="
                    compact ? 'px-2 py-0.5 text-[11px]' : 'px-3 py-1 text-xs'
                "
            >
                <BellOff :class="compact ? 'h-3 w-3' : 'h-3.5 w-3.5'" />
                <span>
                    {{
                        status.indefinite
                            ? 'SMS Paused'
                            : `Paused · ${remainingLabel}`
                    }}
                </span>
            </span>
            <button
                type="button"
                :disabled="busy"
                class="inline-flex items-center gap-1 rounded-lg border border-emerald-500/40 bg-emerald-500/10 font-medium text-emerald-600 transition hover:bg-emerald-500/20 disabled:opacity-50 dark:text-emerald-400"
                :class="
                    compact ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs'
                "
                @click="resumeSms"
            >
                <Bell :class="compact ? 'h-3 w-3' : 'h-3.5 w-3.5'" />
                Resume
            </button>
        </template>

        <!-- Active state: Pause dropdown -->
        <template v-else>
            <button
                type="button"
                :disabled="busy"
                class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background/60 font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:opacity-50"
                :class="compact ? 'px-2.5 py-1 text-xs' : 'px-3 py-1.5 text-xs'"
                @click="menuOpen = !menuOpen"
            >
                <BellOff class="h-3.5 w-3.5" />
                <span>Pause SMS</span>
                <ChevronDown class="h-3 w-3 opacity-70" />
            </button>

            <div
                v-if="menuOpen"
                class="absolute top-full right-0 z-30 mt-1.5 w-44 overflow-hidden rounded-xl border border-border bg-popover p-1 shadow-lg"
            >
                <p
                    class="px-2.5 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Turn off SMS for
                </p>
                <button
                    v-for="preset in presets"
                    :key="preset.label"
                    type="button"
                    class="flex w-full items-center rounded-lg px-2.5 py-1.5 text-left text-xs text-foreground transition hover:bg-muted"
                    @click="muteFor(preset.minutes)"
                >
                    {{ preset.label }}
                </button>
            </div>
        </template>
    </div>
</template>
