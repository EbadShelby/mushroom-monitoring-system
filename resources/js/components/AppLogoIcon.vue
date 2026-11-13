<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    class?: HTMLAttributes['class'];
    className?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <img
        src="/logo.png"
        alt="MycoSense Logo"
        :class="cn('rounded-full', props.class, props.className)"
        v-bind="$attrs"
    />
</template>
