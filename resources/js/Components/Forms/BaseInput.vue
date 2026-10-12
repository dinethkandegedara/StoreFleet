<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    modelValue?: string | number | null;
    id?: string;
    label?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    error?: string;
    hint?: string;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const inputId = computed(() => {
    return props.id || `input-${Math.random().toString(36).substring(2, 9)}`;
});

const onInput = (e: Event) => {
    emit('update:modelValue', (e.target as HTMLInputElement).value);
};
</script>

<template>
    <div class="form-group">
        <label 
            v-if="label" 
            :for="inputId" 
            :class="['form-label', required ? 'form-label-required' : '']"
        >
            {{ label }}
        </label>

        <div class="form-control-wrapper">
            <span v-if="$slots.leading" style="position: absolute; left: 0.75rem; color: var(--text-muted); display: flex; align-items: center; pointer-events: none;">
                <slot name="leading"></slot>
            </span>

            <input
                :id="inputId"
                :type="type"
                :value="modelValue ?? ''"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                @input="onInput"
                :class="[
                    'form-input', 
                    error ? 'form-input-error' : '',
                    $slots.leading ? 'pl-9' : '',
                    $slots.trailing ? 'pr-9' : ''
                ]"
                :style="[
                    $slots.leading ? { paddingLeft: '2.5rem' } : {},
                    $slots.trailing ? { paddingRight: '2.5rem' } : {}
                ]"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="error ? `${inputId}-error` : (hint ? `${inputId}-hint` : undefined)"
            />

            <span v-if="$slots.trailing" style="position: absolute; right: 0.75rem; color: var(--text-muted); display: flex; align-items: center;">
                <slot name="trailing"></slot>
            </span>
        </div>

        <span v-if="error" :id="`${inputId}-error`" class="form-error" role="alert">
            {{ error }}
        </span>
        <span v-else-if="hint" :id="`${inputId}-hint`" class="form-hint">
            {{ hint }}
        </span>
    </div>
</template>
