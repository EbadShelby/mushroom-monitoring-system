<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Oyster Mushroom icon -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs" fill="currentColor">
        <path
            d="M12 2C7.5 2 4 5.5 4 9c0 1.8.7 3.4 1.8 4.6L5 20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1l-.8-6.4A6.96 6.96 0 0 0 20 9c0-3.5-3.5-7-8-7Zm0 2c3.3 0 6 2.7 6 5 0 1.4-.6 2.7-1.5 3.6a1 1 0 0 0-.3.8l.7 5.6H7.1l.7-5.6a1 1 0 0 0-.3-.8A4.96 4.96 0 0 1 6 9c0-2.3 2.7-5 6-5Zm-2 3a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm4 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm-2 2a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"
        />
    </svg>
</template>
