<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-lg bg-emerald-700/10 dark:bg-emerald-950/40 p-0.5 shadow-sm overflow-hidden"
    >
        <AppLogoIcon class="size-full object-contain" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-bold text-emerald-700 dark:text-emerald-400"
            >MycoSense</span
        >
        <span
            class="truncate text-[10px] tracking-wider text-muted-foreground uppercase"
            >CotSU Monitoring</span
        >
    </div>
</template>
