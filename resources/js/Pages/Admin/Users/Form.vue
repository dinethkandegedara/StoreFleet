<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseSelect from '@/Components/Forms/BaseSelect.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import {
    Users,
    ArrowLeft,
    CheckCircle2,
    Shield,
    Store,
    Plus,
    Trash2,
    Lock,
    AlertCircle
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

interface UserAssignmentForm {
    branch_id: number | null;
    role_id: number;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        is_super_admin: boolean;
        assignments: Array<{
            id: number;
            branch_id: number | null;
            role_id: number;
        }>;
    } | null;
    branches: BranchOption[];
    roles: RoleOption[];
}

const props = defineProps<Props>();
const isEditing = !!props.user;

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    is_active: props.user?.is_active ?? true,
    assignments: (props.user?.assignments || []).map(a => ({
        branch_id: a.branch_id,
        role_id: a.role_id,
    })) as UserAssignmentForm[],
});

// Add an empty assignment row
const addAssignment = () => {
    const defaultRoleId = props.roles.length > 0 ? props.roles[0].id : 0;
    form.assignments.push({
        branch_id: props.branches.length > 0 ? props.branches[0].id : null,
        role_id: defaultRoleId,
    });
};

const removeAssignment = (index: number) => {
    form.assignments.splice(index, 1);
};

const submit = () => {
    if (isEditing) {
        form.put(`/admin/users/${props.user!.id}`);
    } else {
        form.post('/admin/users');
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="isEditing ? `Edit Staff Account: ${user?.name}` : 'New Staff Account - StoreFleet'" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Link
                        href="/admin/users"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors mb-2"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        Back to Staff List
                    </Link>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                        <Users class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                        {{ isEditing ? `Edit Staff Account: ${user?.name}` : 'Create New Staff Account' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ isEditing ? 'Update profile information, password credentials, and branch-role authorizations.' : 'Provision a new operational staff member with branch register permissions.' }}
                    </p>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Credentials Card -->
                <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Lock class="w-4 h-4 text-primary-500" />
                        Account Credentials &amp; Profile
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <BaseInput
                            v-model="form.name"
                            label="Full Name"
                            placeholder="e.g. Jane Doe"
                            required
                            :error="form.errors.name"
                        />

                        <BaseInput
                            v-model="form.email"
                            label="Email Address (Login Username)"
                            type="email"
                            placeholder="jane.doe@storefleet.test"
                            required
                            :error="form.errors.email"
                        />

                        <BaseInput
                            v-model="form.password"
                            label="Password"
                            type="password"
                            :placeholder="isEditing ? 'Leave blank to preserve existing password' : 'At least 8 characters'"
                            :required="!isEditing"
                            :error="form.errors.password"
                        />

                        <BaseInput
                            v-model="form.password_confirmation"
                            label="Confirm Password"
                            type="password"
                            :placeholder="isEditing ? 'Leave blank to preserve existing' : 'Re-type password'"
                            :required="!isEditing"
                            :error="form.errors.password_confirmation"
                        />
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <BaseCheckbox
                            v-model="form.is_active"
                            label="Account is Active (Allow login and POS terminal operations)"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 pl-6">
                            Deactivated accounts cannot authenticate or access any branch functions.
                        </p>
                    </div>
                </div>

                <!-- Branch & Role Authorizations Card -->
                <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <Shield class="w-4 h-4 text-primary-500" />
                                Branch &amp; Role Authorizations
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Assign which role this user holds in each authorized operating branch.
                            </p>
                        </div>

                        <BaseButton
                            type="button"
                            variant="secondary"
                            size="sm"
                            @click="addAssignment"
                        >
                            <Plus class="w-4 h-4 mr-1.5" />
                            Add Branch Assignment
                        </BaseButton>
                    </div>

                    <div v-if="form.assignments.length === 0" class="p-4 bg-slate-50 dark:bg-slate-800/40 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg text-center text-xs text-slate-500">
                        No branch roles assigned yet. Click "Add Branch Assignment" to assign register or manager privileges.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(assignment, idx) in form.assignments"
                            :key="idx"
                            class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg flex flex-col sm:flex-row items-center gap-3"
                        >
                            <!-- Branch Select -->
                            <div class="w-full sm:flex-1">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Branch Scope
                                </label>
                                <select
                                    v-model="assignment.branch_id"
                                    class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                                >
                                    <option :value="null">All Branches (Global Business Scope)</option>
                                    <option v-for="b in branches" :key="b.id" :value="b.id">
                                        {{ b.name }} ({{ b.code }})
                                    </option>
                                </select>
                            </div>

                            <!-- Role Select -->
                            <div class="w-full sm:flex-1">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Assigned Role
                                </label>
                                <select
                                    v-model="assignment.role_id"
                                    class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                                    required
                                >
                                    <option v-for="r in roles" :key="r.id" :value="r.id">
                                        {{ r.name }} {{ r.is_system ? '(System)' : '' }}
                                    </option>
                                </select>
                            </div>

                            <!-- Remove Button -->
                            <div class="sm:pt-5">
                                <button
                                    type="button"
                                    class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition-colors"
                                    title="Remove Assignment"
                                    @click="removeAssignment(idx)"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <Link href="/admin/users">
                        <BaseButton variant="secondary" type="button">
                            Cancel
                        </BaseButton>
                    </Link>

                    <BaseButton
                        variant="primary"
                        type="submit"
                        :loading="form.processing"
                    >
                        <CheckCircle2 class="w-4 h-4 mr-1.5" />
                        {{ isEditing ? 'Save Staff Changes' : 'Create Staff Member' }}
                    </BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
