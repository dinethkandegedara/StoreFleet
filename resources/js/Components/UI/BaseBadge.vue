<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    variant?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'neutral';
    size?: 'sm' | 'md';
    dot?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'neutral',
    size: 'sm',
    dot: false,
});

const badgeClasses = computed(() => {
    return [
        'badge',
        `badge-${props.variant}`,
        props.size === 'md' ? 'badge-md' : '',
    ].filter(Boolean).join(' ');
});
</script>

<template>
    <span :class="badgeClasses">
        <span 
            v-if="dot" 
            style="width: 6px; height: 6px; border-radius: 50%; background-color: currentColor; display: inline-block;"
            aria-hidden="true"
        ></span>
        <slot></slot>
    </span>
</template>
