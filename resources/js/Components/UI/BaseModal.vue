<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';
import { X } from 'lucide-vue-next';

interface Props {
    show: boolean;
    title?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    closeable?: boolean;
    closeOnBackdrop?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    closeable: true,
    closeOnBackdrop: true,
});

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const handleBackdropClick = (e: MouseEvent) => {
    if (e.target === e.currentTarget && props.closeable && props.closeOnBackdrop) {
        emit('close');
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show && props.closeable) {
        emit('close');
    }
};

watch(() => props.show, (isOpen) => {
    if (typeof document !== 'undefined') {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

const sizeStyles = {
    sm: 'max-width: 400px;',
    md: 'max-width: 540px;',
    lg: 'max-width: 720px;',
    xl: 'max-width: 960px;',
};
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="show" 
            class="modal-backdrop" 
            @click="handleBackdropClick"
            role="dialog"
            aria-modal="true"
            :aria-label="title || 'Modal Dialog'"
        >
            <div class="modal-container" :style="sizeStyles[size]">
                <div v-if="title || $slots.header" class="modal-header">
                    <slot name="header">
                        <h3 style="font-size: 1.15rem; font-weight: 600;">{{ title }}</h3>
                    </slot>
                    <button 
                        v-if="closeable" 
                        type="button" 
                        @click="emit('close')" 
                        class="btn btn-ghost btn-sm" 
                        style="padding: 4px; border-radius: var(--radius-sm);"
                        aria-label="Close dialog"
                    >
                        <X :size="18" />
                    </button>
                </div>

                <div class="modal-body">
                    <slot></slot>
                </div>

                <div v-if="$slots.footer" class="modal-footer">
                    <slot name="footer"></slot>
                </div>
            </div>
        </div>
    </Teleport>
</template>
