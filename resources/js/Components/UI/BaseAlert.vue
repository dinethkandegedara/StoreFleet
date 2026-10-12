<script setup lang="ts">
import { computed } from 'vue';
import { 
    CheckCircle2, 
    AlertTriangle, 
    AlertCircle, 
    Info, 
    X 
} from 'lucide-vue-next';

interface Props {
    variant?: 'success' | 'warning' | 'danger' | 'info';
    title?: string;
    dismissible?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'info',
    dismissible: false,
});

const emit = defineEmits<{
    (e: 'dismiss'): void;
}>();

const iconComponent = computed(() => {
    switch (props.variant) {
        case 'success': return CheckCircle2;
        case 'warning': return AlertTriangle;
        case 'danger': return AlertCircle;
        default: return Info;
    }
});
</script>

<template>
    <div :class="['alert', `alert-${variant}`]" role="alert">
        <component :is="iconComponent" :size="20" style="flex-shrink: 0; margin-top: 1px;" aria-hidden="true" />
        
        <div style="flex: 1; min-width: 0;">
            <div v-if="title" style="font-weight: 600; margin-bottom: 2px;">{{ title }}</div>
            <div><slot></slot></div>
        </div>

        <button 
            v-if="dismissible" 
            type="button" 
            @click="emit('dismiss')" 
            class="btn btn-ghost btn-sm" 
            style="padding: 2px; margin-left: var(--space-2); color: inherit;"
            aria-label="Dismiss alert"
        >
            <X :size="16" />
        </button>
    </div>
</template>
