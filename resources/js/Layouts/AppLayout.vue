<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import ToastContainer from '@/Components/Feedback/ToastContainer.vue';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import { 
    LayoutDashboard, 
    ShoppingCart, 
    Package, 
    ReceiptText, 
    Store, 
    Settings, 
    Sun, 
    Moon, 
    Palette, 
    Menu, 
    X, 
    User as UserIcon,
    Layers,
    ChevronDown
} from 'lucide-vue-next';

interface Props {
    title?: string;
}

defineProps<Props>();

const page = usePage();
const { theme, primaryColor, toggleTheme, setPrimaryColor, initTheme } = useTheme();

const mobileMenuOpen = ref(false);

onMounted(() => {
    initTheme();
});

const presetColors = [
    { name: 'Classic Blue', hex: '#2563eb' },
    { name: 'Emerald POS', hex: '#059669' },
    { name: 'Indigo Modern', hex: '#4f46e5' },
    { name: 'Purple Luxe', hex: '#7c3aed' },
    { name: 'Amber Glow', hex: '#d97706' },
    { name: 'Rose Accent', hex: '#e11d48' },
];

const navigation = [
    { name: 'Dashboard', href: '/', icon: LayoutDashboard, active: true },
    { name: 'POS Terminal', href: '#pos-preview', icon: ShoppingCart, active: false },
    { name: 'Products & Catalog', href: '#products', icon: Package, active: false },
    { name: 'Sales & Receipts', href: '#sales', icon: ReceiptText, active: false },
    { name: 'Branches', href: '#branches', icon: Store, active: false },
    { name: 'System Settings', href: '#settings', icon: Settings, active: false },
];
</script>

<template>
    <div class="app-shell">
        <!-- Toast Notification Host -->
        <ToastContainer />

        <!-- Sidebar Navigation -->
        <aside :class="['sidebar', mobileMenuOpen ? 'mobile-open' : '']">
            <div class="sidebar-header justify-between">
                <div class="flex items-center gap-3">
                    <div style="width: 2.25rem; height: 2.25rem; border-radius: var(--radius-md); background: linear-gradient(135deg, var(--primary), var(--primary-hover)); display: flex; align-items: center; justify-content: center; color: white;">
                        <Layers :size="20" />
                    </div>
                    <div>
                        <span style="font-weight: 700; font-size: 1.05rem; display: block; line-height: 1.2;">
                            {{ page.props.appName || 'StoreFleet' }}
                        </span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Admin Portal</span>
                    </div>
                </div>

                <button 
                    @click="mobileMenuOpen = false" 
                    class="btn btn-ghost btn-sm" 
                    style="display: none;" 
                    aria-label="Close sidebar"
                >
                    <X :size="18" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="sidebar-nav" aria-label="Main Navigation">
                <a
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="['sidebar-item', item.active ? 'active' : '']"
                >
                    <component :is="item.icon" :size="18" aria-hidden="true" />
                    <span>{{ item.name }}</span>
                </a>
            </nav>

            <!-- Sidebar Footer -->
            <div style="padding: var(--space-4); border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: var(--space-3);">
                <div style="width: 2rem; height: 2rem; border-radius: 50%; background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center;">
                    <UserIcon :size="16" />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.825rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        Administrator
                    </div>
                    <div style="font-size: 0.725rem; color: var(--text-muted);">
                        Super Admin
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Viewport -->
        <div class="main-viewport">
            <!-- Topbar Header -->
            <header class="topbar">
                <div class="flex items-center gap-3">
                    <button 
                        @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="btn btn-secondary btn-sm" 
                        aria-label="Toggle menu"
                    >
                        <Menu :size="18" />
                    </button>

                    <!-- Active Branch Indicator -->
                    <div class="flex items-center gap-2" style="background-color: var(--bg-subtle); padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.8rem; border: 1px solid var(--border-subtle);">
                        <Store :size="14" style="color: var(--primary);" />
                        <span style="font-weight: 500;">Active Branch:</span>
                        <strong style="color: var(--text-primary);">Main Store</strong>
                    </div>
                </div>

                <!-- Topbar Actions -->
                <div class="flex items-center gap-2">
                    <!-- Accent Color Picker Dropdown -->
                    <BaseDropdown align="right" width="220px">
                        <template #trigger="{ isOpen }">
                            <button class="btn btn-secondary btn-sm" title="Configure Accent Color">
                                <Palette :size="16" style="color: var(--primary);" />
                                <span>Theme Color</span>
                                <ChevronDown :size="14" />
                            </button>
                        </template>
                        <template #default="{ close }">
                            <div style="padding: 0.5rem 0.75rem;">
                                <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                                    Preset Accent Colors
                                </div>
                                <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                                    <button
                                        v-for="color in presetColors"
                                        :key="color.hex"
                                        @click="() => { setPrimaryColor(color.hex); close(); }"
                                        :style="{ backgroundColor: color.hex }"
                                        style="width: 2.25rem; height: 2.25rem; border-radius: var(--radius-sm); border: 2px solid white; cursor: pointer; box-shadow: var(--shadow-xs);"
                                        :title="color.name"
                                    ></button>
                                </div>
                            </div>
                        </template>
                    </BaseDropdown>

                    <!-- Light / Dark Theme Toggle -->
                    <button 
                        @click="toggleTheme" 
                        class="btn btn-secondary btn-sm" 
                        :title="theme === 'dark' ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
                        aria-label="Toggle theme"
                    >
                        <Sun v-if="theme === 'dark'" :size="16" />
                        <Moon v-else :size="16" />
                        <span>{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
                    </button>
                </div>
            </header>

            <!-- Page Body Content -->
            <main class="content-body">
                <slot></slot>
            </main>
        </div>
    </div>
</template>
