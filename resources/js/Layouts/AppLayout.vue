<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
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
    ChevronDown,
    Shield,
    ShieldCheck,
    LogOut,
    LogIn
} from 'lucide-vue-next';

import type { SharedPageProps } from '@/vite-env';
import { usePermission } from '@/Composables/usePermission';

interface Props {
    title?: string;
}

defineProps<Props>();

const page = usePage<SharedPageProps>();
const { theme, primaryColor, toggleTheme, applyTheme, setPrimaryColor, initTheme } = useTheme();
const { can } = usePermission();

const mobileMenuOpen = ref(false);

const switchBranch = (branchId: number, closeDropdown: () => void) => {
    closeDropdown();
    router.post(`/switch-branch/${branchId}`);
};

const handleToggleTheme = () => {
    toggleTheme();
    if (page.props.auth?.user) {
        router.post('/user/preference/theme', {
            theme_preference: theme.value,
        }, { preserveScroll: true, preserveState: true });
    }
};

onMounted(() => {
    initTheme();

    if (page.props.auth?.user?.theme_preference) {
        applyTheme(page.props.auth.user.theme_preference as 'light' | 'dark');
    }

    if (page.props.business?.primary_color) {
        setPrimaryColor(page.props.business.primary_color);
    }
});

const presetColors = [
    { name: 'Classic Blue', hex: '#2563eb' },
    { name: 'Emerald POS', hex: '#059669' },
    { name: 'Indigo Modern', hex: '#4f46e5' },
    { name: 'Purple Luxe', hex: '#7c3aed' },
    { name: 'Amber Glow', hex: '#d97706' },
    { name: 'Rose Accent', hex: '#e11d48' },
];

const navigation = computed(() => {
    const items = [
        { name: 'Dashboard', href: '/', icon: LayoutDashboard, active: page.url === '/' },
    ];

    if (can('pos.terminal.access')) {
        items.push({
            name: 'POS Terminal',
            href: '/pos',
            icon: ShoppingCart,
            active: page.url.startsWith('/pos'),
        });
    }

    if (can('catalog.products.view')) {
        items.push({
            name: 'Products & Catalog',
            href: '/admin/products',
            icon: Package,
            active: page.url.startsWith('/admin/products'),
        });
    }

    if (can('catalog.categories.manage')) {
        items.push({
            name: 'Categories',
            href: '/admin/categories',
            icon: Layers,
            active: page.url.startsWith('/admin/categories'),
        });
    }

    items.push({ name: 'Sales & Receipts', href: '#sales', icon: ReceiptText, active: false });

    if (can('branches.view')) {
        items.push({
            name: 'Branches',
            href: '/admin/branches',
            icon: Store,
            active: false,
        });
    }

    if (can('roles.view')) {
        items.push({
            name: 'Roles & RBAC',
            href: '/admin/roles',
            icon: Shield,
            active: false,
        });
    }

    if (can('settings.view')) {
        items.push({
            name: 'System Settings',
            href: '/admin/settings',
            icon: Settings,
            active: false,
        });
    }

    if (page.props.auth?.user?.is_super_admin) {
        items.push({
            name: 'Audit Logs',
            href: '/admin/audit-logs',
            icon: ShieldCheck,
            active: false,
        });
    }

    return items;
});

const logout = () => {
    router.post('/logout');
};
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
                            {{ page.props.business?.name || page.props.appName || 'StoreFleet' }}
                        </span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">{{ page.props.appName || 'StoreFleet' }} POS</span>
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
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="['sidebar-item', item.active ? 'active' : '']"
                >
                    <component :is="item.icon" :size="18" aria-hidden="true" />
                    <span>{{ item.name }}</span>
                </Link>
            </nav>

            <!-- Sidebar User / Auth Footer -->
            <div style="padding: var(--space-4); border-top: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: var(--space-2);">
                <div v-if="page.props.auth?.user" class="flex items-center gap-2" style="flex: 1; min-width: 0;">
                    <div style="width: 2rem; height: 2rem; border-radius: 50%; background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <UserIcon :size="16" />
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.825rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ page.props.auth.user.name }}
                        </div>
                        <div style="font-size: 0.725rem; color: var(--text-muted); text-transform: capitalize;">
                            {{ page.props.auth.user.is_super_admin ? 'Super Admin' : page.props.auth.user.role }}
                        </div>
                    </div>
                    <button 
                        @click="logout" 
                        class="btn btn-ghost btn-sm" 
                        style="padding: 4px; color: var(--text-muted);" 
                        title="Sign Out"
                        aria-label="Sign out"
                    >
                        <LogOut :size="16" />
                    </button>
                </div>
                <div v-else class="flex items-center justify-between w-full">
                    <span style="font-size: 0.825rem; color: var(--text-muted);">Not signed in</span>
                    <Link href="/login" class="btn btn-primary btn-sm">
                        <LogIn :size="14" />
                        <span>Sign In</span>
                    </Link>
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

                    <!-- Active Branch Dropdown Selector -->
                    <BaseDropdown v-if="page.props.auth?.authorized_branches && page.props.auth.authorized_branches.length > 0" align="left" width="260px">
                        <template #trigger="{ isOpen }">
                            <button class="flex items-center gap-2" style="background-color: var(--bg-subtle); padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.8rem; border: 1px solid var(--border-subtle); cursor: pointer;">
                                <Store :size="14" style="color: var(--primary);" />
                                <span style="font-weight: 500;">Active Branch:</span>
                                <strong style="color: var(--text-primary);">
                                    {{ page.props.auth.active_branch?.name || 'All Branches' }}
                                </strong>
                                <ChevronDown :size="12" />
                            </button>
                        </template>
                        <template #default="{ close }">
                            <div style="padding: 0.5rem 0.25rem;">
                                <div style="font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; padding: 0 0.5rem 0.35rem;">
                                    Switch Operating Branch
                                </div>
                                <button
                                    v-for="branch in page.props.auth.authorized_branches"
                                    :key="branch.id"
                                    type="button"
                                    class="dropdown-item flex items-center justify-between"
                                    :style="{ fontWeight: branch.id === page.props.auth.active_branch?.id ? '700' : '500' }"
                                    @click="switchBranch(branch.id, close)"
                                >
                                    <span>{{ branch.name }}</span>
                                    <span class="mono" style="font-size: 0.75rem; color: var(--text-muted);">{{ branch.code }}</span>
                                </button>
                            </div>
                        </template>
                    </BaseDropdown>
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

                    <!-- Light / Dark Theme Toggle (Persisted to user preference) -->
                    <button 
                        @click="handleToggleTheme" 
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
