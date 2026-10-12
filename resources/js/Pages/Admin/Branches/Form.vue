<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import { 
    Store, 
    ArrowLeft, 
    CheckCircle2, 
    MapPin, 
    Receipt, 
    Phone 
} from 'lucide-vue-next';

interface BranchData {
    id: number;
    name: string;
    code: string;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    phone?: string | null;
    email?: string | null;
    manager_name?: string | null;
    receipt_header?: string | null;
    receipt_footer?: string | null;
    is_active: boolean;
    is_default: boolean;
}

interface Props {
    branch?: BranchData | null;
}

const props = defineProps<Props>();

const isEditing = computed(() => Boolean(props.branch?.id));

const form = useForm({
    name: props.branch?.name || '',
    code: props.branch?.code || '',
    address: props.branch?.address || '',
    city: props.branch?.city || '',
    state: props.branch?.state || '',
    postal_code: props.branch?.postal_code || '',
    country: props.branch?.country || 'US',
    phone: props.branch?.phone || '',
    email: props.branch?.email || '',
    manager_name: props.branch?.manager_name || '',
    receipt_header: props.branch?.receipt_header || '',
    receipt_footer: props.branch?.receipt_footer || '',
    is_default: props.branch?.is_default ?? false,
});

const submit = () => {
    if (isEditing.value && props.branch?.id) {
        form.put(`/admin/branches/${props.branch.id}`);
    } else {
        form.post('/admin/branches');
    }
};
</script>

<template>
    <AppLayout :title="(isEditing ? 'Edit Branch' : 'Create Branch') + ' - StoreFleet'">
        <Head :title="(isEditing ? 'Edit Branch' : 'Create Branch') + ' - StoreFleet'" />

        <div style="max-width: 900px; margin: 0 auto;">
            <!-- Navigation & Header -->
            <div style="margin-bottom: var(--space-6);">
                <Link href="/admin/branches" class="btn btn-ghost btn-sm" style="padding-left: 0; margin-bottom: var(--space-3);">
                    <ArrowLeft :size="16" />
                    <span>Back to Branches</span>
                </Link>

                <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <Store style="color: var(--primary);" :size="28" />
                    <span>{{ isEditing ? `Edit Branch: ${branch?.name}` : 'Create New Operating Branch' }}</span>
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                    Configure operating parameters, store location, manager details, and thermal receipt notes.
                </p>
            </div>

            <form @submit.prevent="submit">
                <!-- Branch Information Card -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Location Information &amp; Code
                    </h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                        <BaseInput
                            v-model="form.name"
                            label="Branch Name"
                            placeholder="e.g. Downtown Flagship"
                            required
                            :error="form.errors.name"
                        />

                        <BaseInput
                            v-model="form.code"
                            label="Branch Code / Identifier"
                            placeholder="e.g. DT01"
                            required
                            maxlength="20"
                            class="mono"
                            :error="form.errors.code"
                            hint="Unique alphanumeric code used in transaction records and barcode prefixes."
                        />

                        <BaseInput
                            v-model="form.manager_name"
                            label="Branch Manager"
                            placeholder="e.g. Jane Doe"
                            :error="form.errors.manager_name"
                        />

                        <BaseInput
                            v-model="form.phone"
                            label="Branch Phone"
                            placeholder="+1 (555) 234-5678"
                            :error="form.errors.phone"
                        />

                        <BaseInput
                            v-model="form.email"
                            label="Branch Email"
                            type="email"
                            placeholder="downtown@business.com"
                            :error="form.errors.email"
                        />
                    </div>

                    <div style="margin-top: var(--space-4);">
                        <BaseCheckbox
                            v-model="form.is_default"
                            label="Set as default business location for new registers"
                        />
                    </div>
                </div>

                <!-- Physical Address Card -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Physical Store Address
                    </h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                        <BaseInput
                            v-model="form.address"
                            label="Street Address"
                            placeholder="450 Market Street"
                            :error="form.errors.address"
                        />

                        <BaseInput
                            v-model="form.city"
                            label="City"
                            placeholder="San Francisco"
                            :error="form.errors.city"
                        />

                        <BaseInput
                            v-model="form.state"
                            label="State / Province"
                            placeholder="CA"
                            :error="form.errors.state"
                        />

                        <BaseInput
                            v-model="form.postal_code"
                            label="Postal Code"
                            placeholder="94105"
                            :error="form.errors.postal_code"
                        />

                        <BaseInput
                            v-model="form.country"
                            label="Country"
                            placeholder="US"
                            :error="form.errors.country"
                        />
                    </div>
                </div>

                <!-- Branch Receipt Customization -->
                <div class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.15rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-2);">
                        Branch-Specific Receipt Overrides
                    </h2>
                    <p style="font-size: 0.825rem; color: var(--text-secondary); margin-bottom: var(--space-4);">
                        Leave blank to automatically use the global business receipt header and footer defaults.
                    </p>

                    <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label">Custom Branch Header</label>
                            <textarea
                                v-model="form.receipt_header"
                                class="form-textarea"
                                rows="2"
                                placeholder="Downtown Store - Specializing in Artisan Goods"
                            ></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Custom Branch Footer Note</label>
                            <textarea
                                v-model="form.receipt_footer"
                                class="form-textarea"
                                rows="2"
                                placeholder="Returns accepted at our Downtown location Monday through Saturday."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="card" style="position: sticky; bottom: 1rem; z-index: 10; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-lg);">
                    <Link href="/admin/branches">
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
                        <span>{{ isEditing ? 'Save Branch Changes' : 'Create Branch' }}</span>
                    </BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
