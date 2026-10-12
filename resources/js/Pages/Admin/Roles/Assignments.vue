<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import BaseSelect from '@/Components/Forms/BaseSelect.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import { usePermission } from '@/Composables/usePermission';
import { 
    Users, 
    ArrowLeft, 
    UserPlus, 
    Trash2, 
    Shield, 
    Store, 
    Globe,
    CheckCircle2
} from 'lucide-vue-next';

interface AssignmentItem {
    id: number;
    role_id: number;
    role_name: string;
    role_slug: string;
    role_active: boolean;
    branch_id?: number | null;
    branch_name: string;
    branch_code: string;
}

interface UserItem {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    assignments: AssignmentItem[];
}

interface RoleOption {
    id: number;
    name: string;
    slug: string;
    is_system: boolean;
}

interface BranchOption {
    id: number;
    name: string;
    code: string;
}

interface Props {
    users: {
        data: UserItem[];
        links: any[];
    };
    roles: RoleOption[];
    branches: BranchOption[];
}

const props = defineProps<Props>();
const { can } = usePermission();

const assignModalOpen = ref(false);

const assignForm = useForm({
    user_id: '',
    role_id: '',
    branch_id: '',
});

const userSelectOptions = computed(() => {
    return props.users.data.map(u => ({
        value: String(u.id),
        label: `${u.name} (${u.email})`,
    }));
});

const roleSelectOptions = computed(() => {
    return props.roles.map(r => ({
        value: String(r.id),
        label: r.name,
    }));
});

const branchSelectOptions = computed(() => {
    return [
        { value: '', label: '🌐 All Branches (Global)' },
        ...props.branches.map(b => ({
            value: String(b.id),
            label: `${b.name} (${b.code})`,
        })),
    ];
});

const openAssignModal = (preselectedUserId?: number) => {
    assignForm.reset();
    assignForm.clearErrors();
    if (preselectedUserId) {
        assignForm.user_id = String(preselectedUserId);
    }
    assignModalOpen.value = true;
};

const submitAssignment = () => {
    assignForm.post('/admin/roles/assignments', {
        onSuccess: () => {
            assignModalOpen.value = false;
            assignForm.reset();
        },
    });
};

const revokeAssignment = (assignment: AssignmentItem) => {
    if (confirm(`Revoke role '${assignment.role_name}' from this user?`)) {
        router.delete(`/admin/roles/assignments/${assignment.id}`);
    }
};
</script>

<script lang="ts">
import { computed } from 'vue';
</script>

<template>
    <AppLayout title="Staff Role Assignments - StoreFleet">
        <Head title="Staff Role Assignments - StoreFleet" />

        <div style="max-width: 1200px; margin: 0 auto;">
            <!-- Header & Navigation -->
            <div style="margin-bottom: var(--space-6);">
                <Link href="/admin/roles" class="btn btn-ghost btn-sm" style="padding-left: 0; margin-bottom: var(--space-3);">
                    <ArrowLeft :size="16" />
                    <span>Back to Roles</span>
                </Link>

                <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: var(--space-4);">
                    <div>
                        <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <Users style="color: var(--primary);" :size="28" />
                            <span>Staff Role &amp; Branch Assignments</span>
                        </h1>
                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                            Assign operational roles to staff members globally or scoped to specific branches.
                        </p>
                    </div>

                    <BaseButton variant="primary" size="md" @click="openAssignModal()">
                        <UserPlus :size="16" />
                        <span>Assign New Role</span>
                    </BaseButton>
                </div>
            </div>

            <!-- Users & Assignments Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Status</th>
                                <th>Assigned Roles &amp; Branch Scope</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id">
                                <td style="width: 280px;">
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        {{ user.name }}
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        {{ user.email }}
                                    </div>
                                </td>
                                <td style="width: 110px;">
                                    <BaseBadge v-if="user.is_active" variant="success" size="sm">
                                        Active
                                    </BaseBadge>
                                    <BaseBadge v-else variant="danger" size="sm">
                                        Suspended
                                    </BaseBadge>
                                </td>
                                <td>
                                    <div v-if="user.assignments.length === 0" style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">
                                        No roles currently assigned
                                    </div>

                                    <div v-else style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                                        <div 
                                            v-for="assignment in user.assignments" 
                                            :key="assignment.id"
                                            class="card"
                                            style="padding: 0.35rem 0.65rem; border-radius: var(--radius-md); display: inline-flex; align-items: center; gap: 0.5rem; background-color: var(--bg-subtle);"
                                        >
                                            <Shield :size="14" style="color: var(--primary);" />
                                            <span style="font-size: 0.825rem; font-weight: 600; color: var(--text-primary);">
                                                {{ assignment.role_name }}
                                            </span>

                                            <BaseBadge :variant="assignment.branch_id ? 'info' : 'warning'" size="sm">
                                                <Store v-if="assignment.branch_id" :size="11" />
                                                <Globe v-else :size="11" />
                                                <span>{{ assignment.branch_name }}</span>
                                            </BaseBadge>

                                            <!-- Revoke button -->
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-sm"
                                                style="padding: 2px 4px; margin-left: 2px;"
                                                title="Revoke Role Assignment"
                                                @click="revokeAssignment(assignment)"
                                            >
                                                <Trash2 :size="13" style="color: var(--danger);" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: right; width: 130px;">
                                    <BaseButton variant="ghost" size="sm" @click="openAssignModal(user.id)">
                                        <UserPlus :size="14" />
                                        <span>Add Role</span>
                                    </BaseButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assign Role Modal -->
        <BaseModal
            :show="assignModalOpen"
            title="Assign Role to Staff Member"
            size="md"
            @close="assignModalOpen = false"
        >
            <BaseAlert v-if="assignForm.errors.role_id" variant="danger" style="margin-bottom: var(--space-4);">
                {{ assignForm.errors.role_id }}
            </BaseAlert>

            <form @submit.prevent="submitAssignment">
                <BaseSelect
                    v-model="assignForm.user_id"
                    label="Select Staff Member"
                    :options="userSelectOptions"
                    placeholder="Choose an active staff member..."
                    required
                    :error="assignForm.errors.user_id"
                />

                <BaseSelect
                    v-model="assignForm.role_id"
                    label="Role to Assign"
                    :options="roleSelectOptions"
                    placeholder="Choose a role..."
                    required
                    :error="assignForm.errors.role_id"
                />

                <BaseSelect
                    v-model="assignForm.branch_id"
                    label="Branch Scope"
                    :options="branchSelectOptions"
                    placeholder="Select branch scope..."
                    hint="Choose a specific branch location, or select All Branches for business-wide access."
                    :error="assignForm.errors.branch_id"
                />
            </form>

            <template #footer>
                <div class="flex items-center justify-end" style="gap: var(--space-3); width: 100%;">
                    <BaseButton variant="secondary" @click="assignModalOpen = false">
                        Cancel
                    </BaseButton>
                    <BaseButton
                        variant="primary"
                        :loading="assignForm.processing"
                        @click="submitAssignment"
                    >
                        <CheckCircle2 :size="16" />
                        <span>Confirm Assignment</span>
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
