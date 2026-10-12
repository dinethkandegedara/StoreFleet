<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { 
    CheckCircle2, 
    AlertTriangle, 
    AlertCircle, 
    Info, 
    X 
} from 'lucide-vue-next';

const { toasts, removeToast, success, error, warning, info } = useToast();
const page = usePage();

// Automatically watch for Inertia session flash messages
watch(
    () => page.props.flash as Record<string, string | null> | undefined,
    (flash) => {
        if (!flash) return;
        if (flash.success) success(flash.success);
        if (flash.error) error(flash.error);
        if (flash.warning) warning(flash.warning);
        if (flash.info) info(flash.info);
    },
    { deep: true, immediate: true }
);

const getIcon = (type: string) => {
    switch (type) {
        case 'success': return CheckCircle2;
        case 'warning': return AlertTriangle;
        case 'error': return AlertCircle;
        default: return Info;
    }
};

const getBorderColor = (type: string) => {
    switch (type) {
        case 'success': return 'var(--success)';
        case 'warning': return 'var(--warning)';
        case 'error': return 'var(--danger)';
        default: return 'var(--info)';
    }
};
</script>

<template>
    <div class="toast-container" aria-live="polite" aria-atomic="true">
        <div 
            v-for="item in toasts" 
            :key="item.id" 
            class="toast-item"
            :style="{ borderLeft: `4px solid ${getBorderColor(item.type)}` }"
            role="status"
        >
            <component 
                :is="getIcon(item.type)" 
                :size="20" 
                :style="{ color: getBorderColor(item.type), flexShrink: 0, marginTop: '2px' }" 
                aria-hidden="true" 
            />

            <div style="flex: 1; min-width: 0;">
                <div v-if="item.title" style="font-weight: 600; font-size: 0.875rem; margin-bottom: 2px;">
                    {{ item.title }}
                </div>
                <div style="font-size: 0.825rem; color: var(--text-secondary); word-break: break-word;">
                    {{ item.message }}
                </div>
            </div>

            <button 
                type="button" 
                @click="removeToast(item.id)" 
                class="btn btn-ghost btn-sm" 
                style="padding: 2px; color: var(--text-muted);"
                aria-label="Close notification"
            >
                <X :size="16" />
            </button>
        </div>
    </div>
</template>
