<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import { usePermission } from '@/Composables/usePermission';
import { 
    Store, 
    Plus, 
    Edit2, 
    Power, 
    Users, 
    MapPin, 
    Phone, 
    Mail, 
    CheckCircle,
    Star
} from 'lucide-vue-next';

interface BranchItem {
    id: number;
    name: string;
    code: string;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    phone?: string | null;
    email?: string | null;
    manager_name?: string | null;
    is_active: boolean;
    is_default: boolean;
    users_count: number;
}

interface Props {
    branches: BranchItem[];
}

const props = defineProps<Props>();
const { can } = usePermission();

const toggleActive = (branch: BranchItem) => {
    router.post(`/admin/branches/${branch.id}/toggle-active`);
};
</script>

<template>
    <AppLayout title="Branches & Locations - StoreFleet">
        <Head title="Branches & Locations - StoreFleet" />

        <div style="max-width: 1200px; margin: 0 auto;">
            <!-- Header -->
            <div class="flex items-center justify-between" style="margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <Store style="color: var(--primary);" :size="28" />
                        <span>Branches &amp; Operating Locations</span>
                    </h1>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        Configure multi-location store branches, assign staff, and set branch-specific receipt information.
                    </p>
                </div>

                <Link v-if="can('branches.create')" href="/admin/branches/create">
                    <BaseButton variant="primary" size="md">
                        <Plus :size="16" />
                        <span>Create New Branch</span>
                    </BaseButton>
                </Link>
            </div>

            <!-- Branches Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Branch Name &amp; Code</th>
                                <th>Location &amp; Address</th>
                                <th>Contact &amp; Manager</th>
                                <th>Staff Count</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="branch in branches" :key="branch.id">
                                <td>
                                    <div style="font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.35rem;">
                                        <span>{{ branch.name }}</span>
                                        <BaseBadge v-if="branch.is_default" variant="primary" size="sm" title="Default Store Location">
                                            <Star :size="10" />
                                            <span>Default</span>
                                        </BaseBadge>
                                    </div>
                                    <div class="mono" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                        CODE: {{ branch.code }}
                                    </div>
                                </td>
                                <td>
                                    <div v-if="branch.address" style="font-size: 0.85rem; color: var(--text-primary);">
                                        {{ branch.address }}
                                    </div>
                                    <div v-if="branch.city || branch.state" style="font-size: 0.775rem; color: var(--text-secondary);">
                                        {{ [branch.city, branch.state, branch.postal_code].filter(Boolean).join(', ') }}
                                    </div>
                                    <div v-if="!branch.address && !branch.city" style="color: var(--text-muted); font-size: 0.8rem; font-style: italic;">
                                        No address specified
                                    </div>
                                </td>
                                <td>
                                    <div v-if="branch.manager_name" style="font-size: 0.85rem; font-weight: 500; color: var(--text-primary);">
                                        Mgr: {{ branch.manager_name }}
                                    </div>
                                    <div v-if="branch.phone" style="font-size: 0.775rem; color: var(--text-secondary);">
                                        {{ branch.phone }}
                                    </div>
                                    <div v-if="branch.email" style="font-size: 0.775rem; color: var(--text-secondary);">
                                        {{ branch.email }}
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                                        <Users :size="15" style="color: var(--text-muted);" />
                                        <span>{{ branch.users_count }} assigned</span>
                                    </div>
                                </td>
                                <td>
                                    <BaseBadge v-if="branch.is_active" variant="success">
                                        Active
                                    </BaseBadge>
                                    <BaseBadge v-else variant="danger">
                                        Inactive
                                    </BaseBadge>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end" style="gap: var(--space-2);">
                                        <Link v-if="can('branches.edit')" :href="`/admin/branches/${branch.id}/edit`">
                                            <BaseButton variant="ghost" size="sm" title="Edit Branch">
                                                <Edit2 :size="15" />
                                                <span class="sr-only">Edit</span>
                                            </BaseButton>
                                        </Link>

                                        <BaseButton
                                            v-if="can('branches.manage')"
                                            variant="ghost"
                                            size="sm"
                                            :title="branch.is_active ? 'Deactivate Branch' : 'Activate Branch'"
                                            @click="toggleActive(branch)"
                                        >
                                            <Power :size="15" :style="{ color: branch.is_active ? 'var(--warning)' : 'var(--success)' }" />
                                            <span class="sr-only">Toggle Status</span>
                                        </BaseButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
