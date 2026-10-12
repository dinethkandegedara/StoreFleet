<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    modelValue: boolean;
    id?: string;
    label?: string;
    disabled?: boolean;
    error?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const checkboxId = computed(() => {
    return props.id || `check-${Math.random().toString(36).substring(2, 9)}`;
});

const onChange = (e: Event) => {
    emit('update:modelValue', (e.target as HTMLInputElement).checked);
};
</script>

<template>
    <div style="margin-bottom: var(--space-3);">
        <label :for="checkboxId" class="form-checkbox-wrapper">
            <input
                :id="checkboxId"
                type="checkbox"
                :checked="modelValue"
                :disabled="disabled"
                @change="onChange"
                class="form-checkbox"
                :aria-invalid="error ? 'true' : 'false'"
            />
            <span v-if="label" style="font-size: 0.875rem; color: var(--text-primary); font-weight: 500;">
                {{ label }}
            </span>
            <slot></slot>
        </label>
        <div v-if="error" class="form-error">
            {{ error }}
        </div>
    </div>
</template>
