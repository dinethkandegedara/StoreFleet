<script setup lang="ts">
interface Props {
    width?: string;
    height?: string;
    radius?: string;
    count?: number;
}

withDefaults(defineProps<Props>(), {
    width: '100%',
    height: '1rem',
    radius: 'var(--radius-sm)',
    count: 1,
});
</script>

<template>
    <div style="display: flex; flex-direction: column; gap: var(--space-2); width: 100%;">
        <div 
            v-for="i in count" 
            :key="i"
            class="skeleton"
            :style="{ width, height, borderRadius: radius }"
            aria-hidden="true"
        ></div>
    </div>
</template>
