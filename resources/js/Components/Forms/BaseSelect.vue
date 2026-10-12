<script setup lang="ts">
import { computed } from 'vue';

export interface SelectOption {
    label: string;
    value: string | number;
}

interface Props {
    modelValue?: string | number | null;
    id?: string;
    label?: string;
    options?: SelectOption[];
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    error?: string;
    hint?: string;
}

const props = withDefaults(defineProps<Props>(), {
    options: () => [],
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const selectId = computed(() => {
    return props.id || `select-${Math.random().toString(36).substring(2, 9)}`;
});

const onChange = (e: Event) => {
    emit('update:modelValue', (e.target as HTMLSelectElement).value);
};
</script>

<template>
    <div class="form-group">
        <label 
            v-if="label" 
            :for="selectId" 
            :class="['form-label', required ? 'form-label-required' : '']"
        >
            {{ label }}
        </label>

        <select
            :id="selectId"
            :value="modelValue ?? ''"
            :disabled="disabled"
            :required="required"
            @change="onChange"
            :class="['form-select', error ? 'form-input-error' : '']"
            :aria-invalid="error ? 'true' : 'false'"
            :aria-describedby="error ? `${selectId}-error` : (hint ? `${selectId}-hint` : undefined)"
        >
            <option v-if="placeholder" value="" disabled selected>
                {{ placeholder }}
            </option>
            <option 
                v-for="opt in options" 
                :key="opt.value" 
                :value="opt.value"
            >
                {{ opt.label }}
            </option>
            <slot></slot>
        </select>

        <span v-if="error" :id="`${selectId}-error`" class="form-error" role="alert">
            {{ error }}
        </span>
        <span v-else-if="hint" :id="`${selectId}-hint`" class="form-hint">
            {{ hint }}
        </span>
    </div>
</template>
