<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
    align?: 'left' | 'right';
    width?: string;
}

const props = withDefaults(defineProps<Props>(), {
    align: 'right',
    width: '200px',
});

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const close = () => {
    isOpen.value = false;
};

const handleClickOutside = (e: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        close();
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isOpen.value) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div class="dropdown-container" ref="dropdownRef">
        <div @click="toggle" role="button" tabindex="0" @keydown.enter="toggle" @keydown.space.prevent="toggle">
            <slot name="trigger" :isOpen="isOpen"></slot>
        </div>

        <div 
            v-if="isOpen" 
            :class="['dropdown-menu', `dropdown-align-${align}`]"
            :style="{ width }"
            role="menu"
        >
            <slot :close="close"></slot>
        </div>
    </div>
</template>
