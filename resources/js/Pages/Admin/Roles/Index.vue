<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import { usePermission } from '@/Composables/usePermission';
import { 
    Shield, 
    Plus, 
    Users, 
    Copy, 
    Edit2, 
    Trash2, 
    Power, 
    Key, 
    Lock,
    AlertCircle
} from 'lucide-vue-next';

interface RoleItem {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    is_system: boolean;
    is_active: boolean;
    permissions_count: number;
    users_count: number;
}

interface Props {
    roles: RoleItem[];
}

const props = defineProps<Props>();
const { can } = usePermission();

const deleteModalOpen = ref(false);
const roleToDelete = ref<RoleItem | null>(null);
const isDeleting = ref(false);

const openDeleteModal = (role: RoleItem) => {
    roleToDelete.value = role;
    deleteModalOpen.value = true;
};

const confirmDelete = () => {
    if (!roleToDelete.value) return;

    isDeleting.value = true;
    router.delete(`/admin/roles/${roleToDelete.value.id}`, {
        onFinish: () => {
            isDeleting.value = false;
            deleteModalOpen.value = false;
            roleToDelete.value = null;
        },
    });
};

const toggleRoleActive = (role: RoleItem) => {
    router.post(`/admin/roles/${role.id}/toggle-active`);
};

const duplicateRole = (role: RoleItem) => {
    router.post(`/admin/roles/${role.id}/duplicate`);
};
</script>

<template>
    <AppLayout title="Roles & Permissions - StoreFleet">
        <Head title="Roles & Permissions - StoreFleet" />

        <div style="max-width: 1200px; margin: 0 auto;">
            <!-- Header -->
            <div class="flex items-center justify-between" style="margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <Shield style="color: var(--primary);" :size="28" />
                        Roles &amp; Permissions
                    </h1>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        Manage business access boundaries, custom staff roles, and granular module permissions.
                    </p>
                </div>

                <div class="flex items-center" style="gap: var(--space-3);">
                    <Link v-if="can('roles.assign')" href="/admin/roles/assignments/manage">
                        <BaseButton variant="secondary" size="md">
                            <Users :size="16" />
                            <span>Assign Roles to Staff</span>
                        </BaseButton>
                    </Link>

                    <Link v-if="can('roles.create')" href="/admin/roles/create">
                        <BaseButton variant="primary" size="md">
                            <Plus :size="16" />
                            <span>Create Custom Role</span>
                        </BaseButton>
                    </Link>
                </div>
            </div>

            <!-- Role Cards Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Role Name &amp; Key</th>
                                <th>Classification</th>
                                <th>Status</th>
                                <th>Permissions</th>
                                <th>Assigned Users</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in roles" :key="role.id">
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.35rem;">
                                        <Lock v-if="role.is_system" :size="14" style="color: var(--warning);" />
                                        <span>{{ role.name }}</span>
                                    </div>
                                    <div class="mono" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                        {{ role.slug }}
                                    </div>
                                    <div v-if="role.description" style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px; max-width: 380px;">
                                        {{ role.description }}
                                    </div>
                                </td>
                                <td>
                                    <BaseBadge v-if="role.is_system" variant="warning">
                                        System Protected
                                    </BaseBadge>
                                    <BaseBadge v-else variant="info">
                                        Custom Role
                                    </BaseBadge>
                                </td>
                                <td>
                                    <BaseBadge v-if="role.is_active" variant="success">
                                        Active
                                    </BaseBadge>
                                    <BaseBadge v-else variant="danger">
                                        Inactive
                                    </BaseBadge>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; font-weight: 500;">
                                        <Key :size="15" style="color: var(--text-muted);" />
                                        <span>{{ role.permissions_count }} granted</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                                        <Users :size="15" style="color: var(--text-muted);" />
                                        <span>{{ role.users_count }} users</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end" style="gap: var(--space-2);">
                                        <!-- Edit -->
                                        <Link v-if="can('roles.edit')" :href="`/admin/roles/${role.id}/edit`">
                                            <BaseButton variant="ghost" size="sm" title="Edit Role">
                                                <Edit2 :size="15" />
                                                <span class="sr-only">Edit</span>
                                            </BaseButton>
                                        </Link>

                                        <!-- Duplicate -->
                                        <BaseButton 
                                            v-if="can('roles.create')" 
                                            variant="ghost" 
                                            size="sm" 
                                            title="Duplicate Template"
                                            @click="duplicateRole(role)"
                                        >
                                            <Copy :size="15" />
                                            <span class="sr-only">Duplicate</span>
                                        </BaseButton>

                                        <!-- Toggle Status (Disabled for system roles) -->
                                        <BaseButton
                                            v-if="can('roles.edit') && !role.is_system"
                                            variant="ghost"
                                            size="sm"
                                            :title="role.is_active ? 'Deactivate Role' : 'Activate Role'"
                                            @click="toggleRoleActive(role)"
                                        >
                                            <Power :size="15" :style="{ color: role.is_active ? 'var(--warning)' : 'var(--success)' }" />
                                            <span class="sr-only">Toggle Status</span>
                                        </BaseButton>

                                        <!-- Delete (Disabled for system roles or roles with users) -->
                                        <BaseButton
                                            v-if="can('roles.delete') && !role.is_system"
                                            variant="ghost"
                                            size="sm"
                                            title="Delete Role"
                                            :disabled="role.users_count > 0"
                                            @click="openDeleteModal(role)"
                                        >
                                            <Trash2 :size="15" style="color: var(--danger);" />
                                            <span class="sr-only">Delete</span>
                                        </BaseButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <BaseModal
            :show="deleteModalOpen"
            title="Delete Custom Role"
            size="sm"
            @close="deleteModalOpen = false"
        >
            <div style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5;">
                <p>Are you sure you want to permanently delete the custom role <strong>{{ roleToDelete?.name }}</strong>?</p>
                <p style="margin-top: var(--space-2); color: var(--danger); font-size: 0.825rem;">
                    This action is immediate and cannot be undone.
                </p>
            </div>

            <template #footer>
                <div class="flex items-center justify-end" style="gap: var(--space-3); width: 100%;">
                    <BaseButton variant="secondary" @click="deleteModalOpen = false">
                        Cancel
                    </BaseButton>
                    <BaseButton variant="danger" :loading="isDeleting" @click="confirmDelete">
                        Delete Role
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
