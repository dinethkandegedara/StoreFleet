<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
    size?: 'sm' | 'md' | 'lg';
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    type: 'button',
    loading: false,
    disabled: false,
});

const buttonClasses = computed(() => {
    return [
        'btn',
        `btn-${props.variant}`,
        props.size !== 'md' ? `btn-${props.size}` : '',
    ].filter(Boolean).join(' ');
});
</script>

<template>
    <button
        :type="type"
        :class="buttonClasses"
        :disabled="disabled || loading"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
        <slot name="icon" v-if="!loading"></slot>
        <slot></slot>
    </button>
</template>
