<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PosLayout from '@/Layouts/PosLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import BaseTable from '@/Components/UI/BaseTable.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseSelect from '@/Components/Forms/BaseSelect.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import { useToast } from '@/Composables/useToast';
import { 
    Layers, 
    Search, 
    Plus, 
    Trash2, 
    ExternalLink, 
    Sparkles, 
    ShoppingBag, 
    CreditCard, 
    SlidersHorizontal,
    Monitor,
    Maximize2
} from 'lucide-vue-next';

interface Props {
    appName: string;
    phpVersion: string;
    laravelVersion: string;
    dbDriver: string;
    dbConnected: boolean;
    appEnv: string;
}

defineProps<Props>();

const toast = useToast();

// Interactive Test State
const previewLayout = ref<'admin' | 'pos'>('admin');
const isButtonLoading = ref(false);
const isModalOpen = ref(false);
const isTableLoading = ref(false);
const isTableEmpty = ref(false);
const alertDismissed = ref(false);

// Form Test State
const sampleInput = ref('Sample item description');
const sampleInputError = ref('This field is required in production validation.');
const sampleSelect = ref('USD');
const sampleCheckbox = ref(true);

const selectOptions = [
    { label: 'US Dollar ($ - USD)', value: 'USD' },
    { label: 'Euro (€ - EUR)', value: 'EUR' },
    { label: 'British Pound (£ - GBP)', value: 'GBP' },
    { label: 'Canadian Dollar ($ - CAD)', value: 'CAD' },
];

// Sample table records for design verification
const tableRecords = [
    { id: 'SKU-001', name: 'Standard Thermal Paper (80mm)', category: 'Supplies', price: '$24.50', status: 'In Stock', variant: 'success' as const },
    { id: 'SKU-002', name: 'Wireless Barcode Scanner Handheld', category: 'Hardware', price: '$89.00', status: 'Low Stock', variant: 'warning' as const },
    { id: 'SKU-003', name: 'Electronic Cash Drawer (RJ11)', category: 'Hardware', price: '$115.00', status: 'In Stock', variant: 'success' as const },
    { id: 'SKU-004', name: 'Receipt Printer Stand Mount', category: 'Accessories', price: '$35.00', status: 'Out of Stock', variant: 'danger' as const },
];

const toggleButtonLoading = () => {
    isButtonLoading.value = true;
    setTimeout(() => {
        isButtonLoading.value = false;
        toast.info('Button loading state completed.');
    }, 1200);
};
</script>

<template>
    <Head title="Design System & Shell - StoreFleet" />

    <!-- POS Shell Preview Mode -->
    <PosLayout v-if="previewLayout === 'pos'" branch-name="Downtown Flagship" cashier-name="Sarah Jenkins">
        <div style="flex: 1; padding: var(--space-4); display: flex; gap: var(--space-4); height: 100%; overflow: hidden;">
            <!-- Left: Catalog Area Placeholder -->
            <div class="card" style="flex: 3; display: flex; flex-direction: column; height: 100%;">
                <div class="flex items-center justify-between" style="margin-bottom: var(--space-4);">
                    <div class="flex items-center gap-2">
                        <ShoppingBag :size="20" style="color: var(--primary);" />
                        <h3 style="font-size: 1.15rem;">POS Catalog Viewport</h3>
                    </div>
                    <BaseButton variant="secondary" size="sm" @click="previewLayout = 'admin'">
                        <Monitor :size="16" />
                        <span>Return to Admin Portal</span>
                    </BaseButton>
                </div>

                <div style="flex: 1; border: 2px dashed var(--border-subtle); border-radius: var(--radius-md); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--space-6); text-align: center;">
                    <Sparkles :size="32" style="color: var(--primary); margin-bottom: var(--space-2);" />
                    <h4>POS Layout Ready for Phase 4</h4>
                    <p style="max-width: 28rem; font-size: 0.875rem;">
                        This viewport verifies full-screen, distraction-free dimensions, high-contrast borders, and accessibility tokens across light and dark themes.
                    </p>
                </div>
            </div>

            <!-- Right: Cart & Checkout Ledger Area Placeholder -->
            <div class="card" style="flex: 2; display: flex; flex-direction: column; height: 100%;">
                <div class="flex items-center gap-2" style="margin-bottom: var(--space-4);">
                    <CreditCard :size="20" style="color: var(--primary);" />
                    <h3 style="font-size: 1.15rem;">Checkout Ledger</h3>
                </div>

                <div style="flex: 1; border: 2px dashed var(--border-subtle); border-radius: var(--radius-md); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: var(--space-4);">
                    <p style="font-size: 0.85rem;">Cart calculations and payment ledger mount here in Phase 4.</p>
                </div>

                <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-subtle);">
                    <BaseButton variant="primary" style="width: 100%;" size="lg" @click="toast.success('POS Layout test verified.')">
                        Complete Checkout
                    </BaseButton>
                </div>
            </div>
        </div>
    </PosLayout>

    <!-- Admin Shell Mode (Default) -->
    <AppLayout v-else>
        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-6);">
            <!-- Phase 2 Header & Layout Switcher -->
            <div class="card card-elevated" style="border-left: 4px solid var(--primary);">
                <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: var(--space-4);">
                    <div>
                        <div class="flex items-center gap-2" style="margin-bottom: var(--space-2);">
                            <BaseBadge variant="primary">Phase 2: Complete</BaseBadge>
                            <BaseBadge variant="success">WCAG AA Focus &amp; Contrast</BaseBadge>
                        </div>
                        <h2>Global Design System &amp; Application Shell</h2>
                        <p style="font-size: 0.95rem; margin-top: var(--space-1);">
                            Reusable, theme-agnostic component library powered by CSS custom properties. Verify components and test theme switching below.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <BaseButton variant="outline" size="sm" @click="previewLayout = 'pos'">
                            <Maximize2 :size="16" />
                            <span>Preview POS Terminal Shell</span>
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- Section 1: Buttons, States & Dialogs -->
            <div class="card">
                <h3 style="margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                    <SlidersHorizontal :size="18" style="color: var(--primary);" />
                    <span>Buttons, Sizes &amp; Interactive States</span>
                </h3>

                <div class="flex items-center gap-3" style="flex-wrap: wrap; margin-bottom: var(--space-4);">
                    <BaseButton variant="primary">Primary</BaseButton>
                    <BaseButton variant="secondary">Secondary</BaseButton>
                    <BaseButton variant="outline">Outline</BaseButton>
                    <BaseButton variant="ghost">Ghost</BaseButton>
                    <BaseButton variant="danger">Danger</BaseButton>
                    <BaseButton variant="primary" :loading="isButtonLoading" @click="toggleButtonLoading">
                        <span>Click for Spinner</span>
                    </BaseButton>
                    <BaseButton variant="secondary" disabled>Disabled</BaseButton>
                    <BaseButton variant="primary" size="sm">Small</BaseButton>
                    <BaseButton variant="primary" size="lg">Large</BaseButton>
                </div>

                <div class="flex items-center gap-3" style="padding-top: var(--space-4); border-top: 1px solid var(--border-subtle);">
                    <BaseButton variant="secondary" size="sm" @click="isModalOpen = true">
                        <span>Open Modal Dialog</span>
                    </BaseButton>

                    <BaseDropdown align="left" width="200px">
                        <template #trigger>
                            <BaseButton variant="secondary" size="sm">
                                <span>Dropdown Menu</span>
                            </BaseButton>
                        </template>
                        <template #default="{ close }">
                            <button class="dropdown-item" @click="close">Edit Settings</button>
                            <button class="dropdown-item" @click="close">Print Duplicate</button>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item" style="color: var(--danger);" @click="close">Delete Item</button>
                        </template>
                    </BaseDropdown>

                    <BaseButton variant="ghost" size="sm" @click="toast.success('Operation completed successfully!')">
                        Trigger Success Toast
                    </BaseButton>

                    <BaseButton variant="ghost" size="sm" @click="toast.error('Transaction validation failed.')">
                        Trigger Error Toast
                    </BaseButton>
                </div>
            </div>

            <!-- Section 2: Form Controls & Validation -->
            <div class="card">
                <h3 style="margin-bottom: var(--space-4);">Form Controls, Inputs &amp; Validation States</h3>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                    <BaseInput 
                        v-model="sampleInput" 
                        label="Standard Text Input" 
                        placeholder="Enter item name..." 
                        hint="Supports helper text below input"
                    >
                        <template #leading><Search :size="16" /></template>
                    </BaseInput>

                    <BaseInput 
                        model-value="Invalid data input" 
                        label="Validated Input (Error State)" 
                        :error="sampleInputError" 
                        required
                    />

                    <BaseSelect 
                        v-model="sampleSelect" 
                        label="Currency Selector" 
                        :options="selectOptions" 
                        hint="Dropdown select with accessible option keys"
                    />

                    <div class="form-group" style="justify-content: center;">
                        <span class="form-label">Checkboxes &amp; Toggles</span>
                        <BaseCheckbox v-model="sampleCheckbox" label="Enable automatic receipt printing on tender" />
                        <BaseCheckbox :model-value="false" label="Disabled Option" disabled />
                    </div>
                </div>
            </div>

            <!-- Section 3: Semantic Alerts & Badges -->
            <div class="card">
                <h3 style="margin-bottom: var(--space-4);">Feedback, Alerts &amp; Status Badges</h3>

                <div class="flex items-center gap-3" style="flex-wrap: wrap; margin-bottom: var(--space-4);">
                    <BaseBadge variant="primary" dot>Primary</BaseBadge>
                    <BaseBadge variant="success" dot>Active</BaseBadge>
                    <BaseBadge variant="warning" dot>Pending</BaseBadge>
                    <BaseBadge variant="danger" dot>Suspended</BaseBadge>
                    <BaseBadge variant="info">Info Tag</BaseBadge>
                    <BaseBadge variant="neutral">Draft</BaseBadge>
                </div>

                <div class="flex flex-col gap-2">
                    <BaseAlert v-if="!alertDismissed" variant="info" title="Application Notice" dismissible @dismiss="alertDismissed = true">
                        The design system adheres to shared hosting constraints: zero Redis dependencies, bundled CSS variables, and native session state.
                    </BaseAlert>
                    <BaseAlert variant="success" title="Theme Compatibility Verified">
                        Every component dynamically reflects the primary accent color token and responds to light/dark mode toggles.
                    </BaseAlert>
                </div>
            </div>

            <!-- Section 4: Data Table with Loading & Empty States -->
            <div class="card">
                <div class="flex items-center justify-between" style="margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
                    <div>
                        <h3>Data Tables, Loading Skeletons &amp; Empty States</h3>
                        <p style="font-size: 0.85rem;">Test how the table renders data rows, skeleton pulse animations, or empty states.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <BaseButton 
                            variant="secondary" 
                            size="sm" 
                            @click="isTableLoading = !isTableLoading"
                        >
                            {{ isTableLoading ? 'Show Table Data' : 'Test Loading State' }}
                        </BaseButton>

                        <BaseButton 
                            variant="secondary" 
                            size="sm" 
                            @click="isTableEmpty = !isTableEmpty"
                        >
                            {{ isTableEmpty ? 'Show Data Rows' : 'Test Empty State' }}
                        </BaseButton>
                    </div>
                </div>

                <BaseTable 
                    :loading="isTableLoading" 
                    :empty="isTableEmpty" 
                    empty-title="No Products in Catalog"
                    empty-description="Get started by creating your first product or importing catalog data."
                >
                    <template #header>
                        <tr>
                            <th>SKU</th>
                            <th>Product Description</th>
                            <th>Category</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </template>

                    <template #default>
                        <tr v-for="item in tableRecords" :key="item.id">
                            <td class="mono" style="font-weight: 500;">{{ item.id }}</td>
                            <td style="font-weight: 500;">{{ item.name }}</td>
                            <td>{{ item.category }}</td>
                            <td class="mono">{{ item.price }}</td>
                            <td>
                                <BaseBadge :variant="item.variant" dot>{{ item.status }}</BaseBadge>
                            </td>
                            <td style="text-align: right;">
                                <BaseButton variant="ghost" size="sm" @click="toast.info(`Inspecting ${item.id}`)">
                                    View
                                </BaseButton>
                            </td>
                        </tr>
                    </template>

                    <template #empty-action>
                        <BaseButton variant="primary" size="sm" @click="isTableEmpty = false">
                            <Plus :size="16" />
                            <span>Add First Product</span>
                        </BaseButton>
                    </template>
                </BaseTable>
            </div>
        </div>

        <!-- Sample Modal Dialog -->
        <BaseModal 
            :show="isModalOpen" 
            title="Sample Modal Dialog" 
            size="md"
            @close="isModalOpen = false"
        >
            <p style="margin-bottom: var(--space-4);">
                This dialog demonstrates modal elevation, subtle background backdrop blur, responsive sizing, and keyboard <kbd style="background: var(--bg-subtle); padding: 2px 6px; border-radius: 4px; font-size: 0.8em;">Esc</kbd> key dismissal.
            </p>

            <BaseInput label="Dialog Input Field" placeholder="Type something to test focus trap..." />

            <template #footer>
                <BaseButton variant="secondary" size="sm" @click="isModalOpen = false">
                    Cancel
                </BaseButton>
                <BaseButton variant="primary" size="sm" @click="() => { isModalOpen = false; toast.success('Dialog action confirmed.'); }">
                    Confirm Action
                </BaseButton>
            </template>
        </BaseModal>
    </AppLayout>
</template>
