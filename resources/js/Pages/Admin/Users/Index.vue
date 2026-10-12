<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { usePermission } from '@/Composables/usePermission';
import {
    Users,
    UserPlus,
    Search,
    Filter,
    X,
    Edit2,
    Power,
    Store,
    Shield,
    ShieldCheck,
    AlertCircle,
    CheckCircle2,
    Lock
} from 'lucide-vue-next';

interface BranchOption {
    id: number;
    name: string;
    code: string;
}

interface RoleOption {
    id: number;
    name: string;
    slug: string;
    is_system: boolean;
}

interface UserAssignment {
    id: number;
    branch_id: number | null;
    branch_name: string;
    branch_code: string;
    role_id: number;
    role_name: string;
    role_slug: string;
}

interface UserListItem {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_super_admin: boolean;
    has_2fa: boolean;
    theme_preference: string | null;
    created_at: string | null;
    is_self: boolean;
    assignments: UserAssignment[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: UserListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    users: PaginatedUsers;
    filters: {
        search: string;
        status: string;
        branch_id: string | number;
        role_id: string | number;
    };
    branches: BranchOption[];
    roles: RoleOption[];
    canCreate: boolean;
    canEdit: boolean;
    canManage: boolean;
}

const props = defineProps<Props>();
const page = usePage();
const { can } = usePermission();

const searchTerm = ref(props.filters.search);
const selectedStatus = ref(props.filters.status || 'all');
const selectedBranch = ref(props.filters.branch_id ? String(props.filters.branch_id) : '');
const selectedRole = ref(props.filters.role_id ? String(props.filters.role_id) : '');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || (selectedStatus.value && selectedStatus.value !== 'all') || selectedBranch.value || selectedRole.value);
});

const applyFilters = () => {
    router.get('/admin/users', {
        search: searchTerm.value || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        branch_id: selectedBranch.value || undefined,
        role_id: selectedRole.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchTerm.value = '';
    selectedStatus.value = 'all';
    selectedBranch.value = '';
    selectedRole.value = '';

    router.get('/admin/users', {}, {
        preserveState: true,
        replace: true,
    });
};

// Toggle status modal
const toggleModalOpen = ref(false);
const userToToggle = ref<UserListItem | null>(null);
const isToggling = ref(false);

const openToggleModal = (user: UserListItem) => {
    userToToggle.value = user;
    toggleModalOpen.value = true;
};

const confirmToggleStatus = () => {
    if (!userToToggle.value) return;

    isToggling.value = true;
    router.post(`/admin/users/${userToToggle.value.id}/toggle-active`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toggleModalOpen.value = false;
            userToToggle.value = null;
        },
        onFinish: () => {
            isToggling.value = false;
        }
    });
};

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
};
</script>

<template>
    <AppLayout>
        <Head title="Staff Accounts - StoreFleet" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                        <Users class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                        Staff Accounts &amp; Users
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage operational staff, cashiers, managers, and their branch &amp; role authorizations.
                    </p>
                </div>

                <div v-if="canCreate">
                    <Link
                        href="/admin/users/create"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 active:bg-primary-800 rounded-lg shadow-sm transition-colors"
                    >
                        <UserPlus class="w-4 h-4" />
                        Add Staff Account
                    </Link>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <!-- Search -->
                    <div class="lg:col-span-2 relative">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Search Staff</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                            <input
                                v-model="searchTerm"
                                type="text"
                                placeholder="Search by name or email address..."
                                class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white placeholder-slate-400"
                                @keydown.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                        <select
                            v-model="selectedStatus"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="all">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                    </div>

                    <!-- Branch Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch Scope</label>
                        <select
                            v-model="selectedBranch"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Branches</option>
                            <option v-for="b in branches" :key="b.id" :value="String(b.id)">
                                {{ b.name }} ({{ b.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Assigned Role</label>
                        <select
                            v-model="selectedRole"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Roles</option>
                            <option v-for="r in roles" :key="r.id" :value="String(r.id)">
                                {{ r.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <BaseButton
                        v-if="hasActiveFilters"
                        variant="secondary"
                        size="sm"
                        @click="resetFilters"
                    >
                        <X class="w-3.5 h-3.5 mr-1" />
                        Clear Filters
                    </BaseButton>
                    <BaseButton
                        variant="primary"
                        size="sm"
                        @click="applyFilters"
                    >
                        <Filter class="w-3.5 h-3.5 mr-1" />
                        Apply Filters
                    </BaseButton>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 overflow-hidden">
                <div v-if="users.data.length === 0" class="p-8">
                    <EmptyState
                        title="No staff accounts found"
                        :description="hasActiveFilters ? 'No accounts match the selected filters. Try clearing search filters.' : 'Add your first staff member to assign branch register and management roles.'"
                    >
                        <template #icon>
                            <Users class="w-12 h-12 text-slate-400" />
                        </template>
                        <template #actions>
                            <BaseButton v-if="hasActiveFilters" variant="secondary" @click="resetFilters">
                                Clear Filters
                            </BaseButton>
                            <Link v-else-if="canCreate" href="/admin/users/create">
                                <BaseButton variant="primary">
                                    <UserPlus class="w-4 h-4 mr-1.5" />
                                    Add Staff Account
                                </BaseButton>
                            </Link>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-3 px-4">Staff Member</th>
                                <th class="py-3 px-4">Trust Boundary</th>
                                <th class="py-3 px-4">Branch &amp; Role Assignments</th>
                                <th class="py-3 px-4 text-center">Security</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
                                :class="{ 'opacity-60 bg-slate-50/30 dark:bg-slate-900/30': !user.is_active }"
                            >
                                <!-- Name & Email -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300 font-bold text-xs flex items-center justify-center shrink-0 border border-primary-200 dark:border-primary-800">
                                            {{ getInitials(user.name) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-slate-900 dark:text-white flex items-center gap-1.5">
                                                {{ user.name }}
                                                <span v-if="user.is_self" class="text-[10px] px-1.5 py-0.2 rounded font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 border border-primary-200">
                                                    You
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Trust Boundary -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        v-if="user.is_super_admin"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800"
                                    >
                                        <ShieldCheck class="w-3.5 h-3.5" />
                                        Super Admin
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        Operational Staff
                                    </span>
                                </td>

                                <!-- Role & Branch Assignments -->
                                <td class="py-3.5 px-4">
                                    <div v-if="user.assignments.length === 0" class="text-xs text-slate-400 italic">
                                        No branch roles assigned
                                    </div>
                                    <div v-else class="flex flex-wrap gap-1.5 max-w-md">
                                        <span
                                            v-for="a in user.assignments"
                                            :key="a.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700"
                                        >
                                            <Shield class="w-3 h-3 text-primary-500" />
                                            <span class="font-medium">{{ a.role_name }}</span>
                                            <span class="text-slate-400">@</span>
                                            <span class="font-semibold text-slate-600 dark:text-slate-400">{{ a.branch_code }}</span>
                                        </span>
                                    </div>
                                </td>

                                <!-- Security (2FA) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span
                                        v-if="user.has_2fa"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                                    >
                                        <Lock class="w-3 h-3" />
                                        2FA Enabled
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-slate-400"
                                    >
                                        Password only
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <BaseBadge :variant="user.is_active ? 'success' : 'danger'" size="sm">
                                        {{ user.is_active ? 'Active' : 'Deactivated' }}
                                    </BaseBadge>
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Edit -->
                                        <Link
                                            v-if="canEdit && (!user.is_super_admin || (page.props as any).auth?.user?.is_super_admin)"
                                            :href="`/admin/users/${user.id}/edit`"
                                            class="p-1.5 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                            title="Edit Staff Account"
                                        >
                                            <Edit2 class="w-4 h-4" />
                                        </Link>

                                        <!-- Toggle Status (Cannot toggle self) -->
                                        <button
                                            v-if="canManage && !user.is_self && (!user.is_super_admin || (page.props as any).auth?.user?.is_super_admin)"
                                            type="button"
                                            class="p-1.5 rounded-lg transition-colors"
                                            :class="user.is_active ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/50' : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50'"
                                            :title="user.is_active ? 'Deactivate Account' : 'Activate Account'"
                                            @click="openToggleModal(user)"
                                        >
                                            <Power class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="users.total > 0"
                    class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/25"
                >
                    <div>
                        Showing <span class="font-semibold text-slate-700 dark:text-slate-300">{{ users.from || 0 }}</span>
                        to <span class="font-semibold text-slate-700 dark:text-slate-300">{{ users.to || 0 }}</span>
                        of <span class="font-semibold text-slate-700 dark:text-slate-300">{{ users.total }}</span> users
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in users.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-2.5 py-1.5 rounded border text-xs font-medium transition-colors"
                                :class="[
                                    link.active
                                        ? 'bg-primary-600 text-white border-primary-600'
                                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1.5 text-xs text-slate-400 border border-transparent"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toggle Status Confirmation Modal -->
        <BaseModal
            :show="toggleModalOpen"
            :title="userToToggle?.is_active ? 'Deactivate Staff Account' : 'Activate Staff Account'"
            size="md"
            @close="toggleModalOpen = false"
        >
            <div v-if="userToToggle" class="space-y-4">
                <div class="p-3 rounded-lg text-xs" :class="userToToggle.is_active ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200'">
                    <div class="font-bold flex items-center gap-1.5 mb-1">
                        <AlertCircle class="w-4 h-4" />
                        {{ userToToggle.is_active ? 'Access Revocation Notice' : 'Account Re-activation' }}
                    </div>
                    {{ userToToggle.is_active
                        ? `Deactivating \"${userToToggle.name}\" will immediately invalidate active login sessions and block access to register terminals and the admin portal.`
                        : `Activating \"${userToToggle.name}\" will restore login credentials and branch permissions immediately.`
                    }}
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        :disabled="isToggling"
                        @click="toggleModalOpen = false"
                    >
                        Cancel
                    </BaseButton>

                    <BaseButton
                        :variant="userToToggle.is_active ? 'danger' : 'primary'"
                        size="sm"
                        :loading="isToggling"
                        @click="confirmToggleStatus"
                    >
                        <Power class="w-3.5 h-3.5 mr-1.5" />
                        {{ userToToggle.is_active ? 'Confirm Deactivation' : 'Confirm Activation' }}
                    </BaseButton>
                </div>
            </div>
        </BaseModal>
    </AppLayout>
</template>
