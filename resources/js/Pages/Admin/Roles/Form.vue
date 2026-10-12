<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import { 
    Shield, 
    ArrowLeft, 
    CheckSquare, 
    Square, 
    Lock, 
    AlertCircle,
    CheckCircle2
} from 'lucide-vue-next';

interface PermissionItem {
    id: number;
    name: string;
    slug: string;
    module: string;
    action: string;
    description?: string | null;
}

interface RoleData {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    is_system: boolean;
    is_active: boolean;
    permission_slugs: string[];
}

interface Props {
    role?: RoleData | null;
    groupedPermissions: Record<string, PermissionItem[]>;
    delegatableSlugs: string[];
}

const props = defineProps<Props>();

const isEditing = computed(() => Boolean(props.role?.id));

const form = useForm({
    name: props.role?.name ?? '',
    slug: props.role?.slug ?? '',
    description: props.role?.description ?? '',
    permissions: props.role?.permission_slugs ?? [] as string[],
});

const isPermissionSelected = (slug: string) => {
    return form.permissions.includes(slug);
};

const togglePermission = (slug: string) => {
    if (!props.delegatableSlugs.includes(slug)) return;

    const index = form.permissions.indexOf(slug);
    if (index > -1) {
        form.permissions.splice(index, 1);
    } else {
        form.permissions.push(slug);
    }
};

const selectAllInModule = (modulePermissions: PermissionItem[]) => {
    const delegatable = modulePermissions
        .filter(p => props.delegatableSlugs.includes(p.slug))
        .map(p => p.slug);

    delegatable.forEach(slug => {
        if (!form.permissions.includes(slug)) {
            form.permissions.push(slug);
        }
    });
};

const deselectAllInModule = (modulePermissions: PermissionItem[]) => {
    const slugs = modulePermissions.map(p => p.slug);
    form.permissions = form.permissions.filter(slug => !slugs.includes(slug));
};

const getActionVariant = (action: string) => {
    switch (action) {
        case 'view': return 'info';
        case 'create': return 'success';
        case 'update': return 'warning';
        case 'delete': return 'danger';
        case 'approve': return 'primary';
        case 'manage': return 'primary';
        default: return 'neutral';
    }
};

const submit = () => {
    if (isEditing.value && props.role?.id) {
        form.put(`/admin/roles/${props.role.id}`);
    } else {
        form.post('/admin/roles');
    }
};
</script>

<template>
    <AppLayout :title="(isEditing ? 'Edit Role' : 'Create Custom Role') + ' - StoreFleet'">
        <Head :title="(isEditing ? 'Edit Role' : 'Create Custom Role') + ' - StoreFleet'" />

        <div style="max-width: 1080px; margin: 0 auto;">
            <!-- Header & Back Navigation -->
            <div style="margin-bottom: var(--space-6);">
                <Link href="/admin/roles" class="btn btn-ghost btn-sm" style="padding-left: 0; margin-bottom: var(--space-3);">
                    <ArrowLeft :size="16" />
                    <span>Back to Roles List</span>
                </Link>

                <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: var(--space-3);">
                    <div>
                        <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <Shield style="color: var(--primary);" :size="28" />
                            <span>{{ isEditing ? `Edit Role: ${role?.name}` : 'Create Custom Role' }}</span>
                        </h1>
                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                            {{ isEditing 
                                ? 'Update role metadata and fine-tune granular permission assignments.' 
                                : 'Configure a new custom staff role with modular business permissions.' 
                            }}
                        </p>
                    </div>

                    <BaseBadge v-if="role?.is_system" variant="warning">
                        <Lock :size="12" />
                        <span>System Role (Protected Slug)</span>
                    </BaseBadge>
                </div>
            </div>

            <!-- Privilege Escalation Notice -->
            <BaseAlert v-if="form.errors.permissions" variant="danger" style="margin-bottom: var(--space-6);">
                {{ form.errors.permissions }}
            </BaseAlert>

            <form @submit.prevent="submit">
                <!-- Role Details Card -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Role Information
                    </h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                        <BaseInput
                            v-model="form.name"
                            label="Role Name"
                            placeholder="e.g. Lead Floor Cashier"
                            required
                            :error="form.errors.name"
                        />

                        <BaseInput
                            v-model="form.slug"
                            label="System Slug"
                            placeholder="e.g. lead-cashier"
                            :disabled="Boolean(role?.is_system)"
                            :error="form.errors.slug"
                            hint="Unique identifier used in system code and permissions logic"
                        />
                    </div>

                    <div class="form-group" style="margin-top: var(--space-3);">
                        <label class="form-label">Role Description</label>
                        <textarea
                            v-model="form.description"
                            class="form-textarea"
                            rows="2"
                            placeholder="Briefly describe the operational responsibilities of this role..."
                        ></textarea>
                    </div>
                </div>

                <!-- Permission Matrix Header -->
                <div class="flex items-center justify-between" style="margin-bottom: var(--space-4);">
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">
                            Permission Matrix
                        </h2>
                        <p style="font-size: 0.85rem; color: var(--text-secondary);">
                            Select the granular module operations granted to users assigned this role.
                        </p>
                    </div>

                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">
                        {{ form.permissions.length }} permissions selected
                    </div>
                </div>

                <!-- Module Cards -->
                <div style="display: flex; flex-direction: column; gap: var(--space-5); margin-bottom: var(--space-8);">
                    <div 
                        v-for="(modulePermissions, moduleName) in groupedPermissions" 
                        :key="moduleName"
                        class="card"
                        style="padding: var(--space-5);"
                    >
                        <!-- Module Header -->
                        <div class="flex items-center justify-between" style="margin-bottom: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-3);">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); text-transform: capitalize;">
                                    {{ moduleName }} Module
                                </h3>
                                <BaseBadge variant="neutral" size="sm">
                                    {{ modulePermissions.length }} actions
                                </BaseBadge>
                            </div>

                            <div class="flex items-center" style="gap: var(--space-2);">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm"
                                    style="font-size: 0.775rem;"
                                    @click="selectAllInModule(modulePermissions)"
                                >
                                    <CheckSquare :size="13" />
                                    <span>Select All</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm"
                                    style="font-size: 0.775rem;"
                                    @click="deselectAllInModule(modulePermissions)"
                                >
                                    <Square :size="13" />
                                    <span>Deselect</span>
                                </button>
                            </div>
                        </div>

                        <!-- Permissions Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: var(--space-3);">
                            <div
                                v-for="permission in modulePermissions"
                                :key="permission.id"
                                :class="[
                                    'card',
                                    isPermissionSelected(permission.slug) ? 'card-selected' : '',
                                    !delegatableSlugs.includes(permission.slug) ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'
                                ]"
                                style="padding: var(--space-3); border-radius: var(--radius-md); transition: all var(--transition-fast);"
                                :style="{
                                    backgroundColor: isPermissionSelected(permission.slug) ? 'var(--primary-light)' : 'var(--bg-surface)',
                                    borderColor: isPermissionSelected(permission.slug) ? 'var(--primary)' : 'var(--border-subtle)'
                                }"
                                @click="togglePermission(permission.slug)"
                            >
                                <div class="flex items-start" style="gap: var(--space-3);">
                                    <input
                                        type="checkbox"
                                        :checked="isPermissionSelected(permission.slug)"
                                        :disabled="!delegatableSlugs.includes(permission.slug)"
                                        @click.stop="togglePermission(permission.slug)"
                                        style="margin-top: 3px;"
                                    />

                                    <div style="flex: 1;">
                                        <div class="flex items-center justify-between" style="gap: var(--space-2);">
                                            <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-primary);">
                                                {{ permission.name }}
                                            </span>
                                            <BaseBadge :variant="getActionVariant(permission.action)" size="sm">
                                                {{ permission.action }}
                                            </BaseBadge>
                                        </div>

                                        <div class="mono" style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
                                            {{ permission.slug }}
                                        </div>

                                        <p v-if="permission.description" style="font-size: 0.775rem; color: var(--text-secondary); margin-top: 4px;">
                                            {{ permission.description }}
                                        </p>

                                        <!-- Non-delegatable notice -->
                                        <div v-if="!delegatableSlugs.includes(permission.slug)" style="margin-top: 6px; font-size: 0.7rem; color: var(--warning); display: flex; align-items: center; gap: 3px;">
                                            <AlertCircle :size="12" />
                                            <span>Non-delegatable: not held by current user</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="card" style="position: sticky; bottom: 1rem; z-index: 10; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-lg);">
                    <Link href="/admin/roles">
                        <BaseButton variant="secondary" type="button">
                            Cancel
                        </BaseButton>
                    </Link>

                    <BaseButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                    >
                        <CheckCircle2 :size="16" />
                        <span>{{ isEditing ? 'Save Role Changes' : 'Create Custom Role' }}</span>
                    </BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.opacity-60 {
    opacity: 0.6;
}
.cursor-not-allowed {
    cursor: not-allowed;
}
.cursor-pointer {
    cursor: pointer;
}
</style>
