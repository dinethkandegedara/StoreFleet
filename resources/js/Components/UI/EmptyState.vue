<script setup lang="ts">
import { FolderOpen } from 'lucide-vue-next';

interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: var(--space-10) var(--space-4);">
        <div style="width: 3.5rem; height: 3.5rem; border-radius: 50%; background-color: var(--bg-subtle); display: flex; align-items: center; justify-content: center; color: var(--text-muted); margin-bottom: var(--space-4);">
            <slot name="icon">
                <FolderOpen :size="28" />
            </slot>
        </div>

        <h4 style="font-size: 1.1rem; font-weight: 600; margin-bottom: var(--space-1);">{{ title }}</h4>
        <p v-if="description" style="max-width: 24rem; font-size: 0.875rem; margin-bottom: var(--space-4);">
            {{ description }}
        </p>

        <div v-if="$slots.action" style="margin-top: var(--space-2);">
            <slot name="action"></slot>
        </div>
    </div>
</template>
