<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { usePermission } from '@/Composables/usePermission';
import { 
    Layers, 
    Plus, 
    ArrowLeft, 
    Edit2, 
    Trash2, 
    FolderTree, 
    Package, 
    CheckCircle2, 
    X,
    Save
} from 'lucide-vue-next';

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    parent_name: string | null;
    full_path: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    products_count: number;
    created_at: string;
}

interface Props {
    categories: CategoryItem[];
}

const props = defineProps<Props>();
const { can } = usePermission();

// Modal state
const modalOpen = ref(false);
const editingCategory = ref<CategoryItem | null>(null);

const form = useForm({
    name: '',
    slug: '',
    parent_id: null as number | null,
    description: '',
    sort_order: 0,
    is_active: true,
});

const openCreateModal = () => {
    editingCategory.value = null;
    form.reset();
    form.name = '';
    form.slug = '';
    form.parent_id = null;
    form.description = '';
    form.sort_order = 0;
    form.is_active = true;
    modalOpen.value = true;
};

const openEditModal = (cat: CategoryItem) => {
    editingCategory.value = cat;
    form.name = cat.name;
    form.slug = cat.slug;
    form.parent_id = cat.parent_id;
    form.description = cat.description ?? '';
    form.sort_order = cat.sort_order;
    form.is_active = cat.is_active;
    modalOpen.value = true;
};

const submitForm = () => {
    if (editingCategory.value) {
        form.put(`/admin/categories/${editingCategory.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    } else {
        form.post('/admin/categories', {
            onSuccess: () => {
                modalOpen.value = false;
            },
        });
    }
};

const deleteCategory = (cat: CategoryItem) => {
    if (confirm(`Are you sure you want to delete category "${cat.name}"?`)) {
        router.delete(`/admin/categories/${cat.id}`);
    }
};
</script>

<template>
    <AppLayout title="Categories & Taxonomy - StoreFleet">
        <Head title="Categories & Taxonomy - StoreFleet" />

        <div style="max-width: 1200px; margin: 0 auto;">
            <!-- Header -->
            <div class="flex items-center justify-between" style="margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <div style="margin-bottom: var(--space-2);">
                        <Link href="/admin/products" class="flex items-center gap-2" style="color: var(--text-secondary); text-decoration: none; font-size: 0.875rem;">
                            <ArrowLeft :size="16" />
                            <span>Back to Products</span>
                        </Link>
                    </div>

                    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <Layers style="color: var(--primary);" :size="28" />
                        <span>Categories &amp; Taxonomy</span>
                        <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted); background: var(--surface-hover); padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                            {{ categories.length }} categories
                        </span>
                    </h1>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        Hierarchical product classification tree to power POS navigation, department filtering, and reporting.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <BaseButton v-if="can('catalog.categories.manage')" variant="primary" size="md" @click="openCreateModal">
                        <Plus :size="18" style="margin-right: 0.4rem;" />
                        <span>Add Category</span>
                    </BaseButton>
                </div>
            </div>

            <!-- Categories Table or Empty State -->
            <div v-if="categories.length > 0" class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--surface-border); background: var(--surface-hover); color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem;">Category Name &amp; Hierarchy</th>
                                <th style="padding: 0.75rem 1rem;">Slug</th>
                                <th style="padding: 0.75rem 1rem;">Products</th>
                                <th style="padding: 0.75rem 1rem;">Sort Order</th>
                                <th style="padding: 0.75rem 1rem; text-align: center;">Status</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="cat in categories" 
                                :key="cat.id"
                                style="border-bottom: 1px solid var(--surface-border); transition: background-color 0.15s ease;"
                            >
                                <td style="padding: 0.75rem 1rem;">
                                    <div class="flex items-center gap-2">
                                        <FolderTree :size="16" style="color: var(--primary);" />
                                        <span style="font-weight: 600; color: var(--text-primary);">{{ cat.name }}</span>
                                    </div>
                                    <div v-if="cat.parent_name" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; margin-left: 1.5rem;">
                                        Subcategory of: <strong>{{ cat.parent_name }}</strong>
                                    </div>
                                </td>

                                <td style="padding: 0.75rem 1rem; font-family: monospace; font-size: 0.8rem; color: var(--text-secondary);">
                                    {{ cat.slug }}
                                </td>

                                <td style="padding: 0.75rem 1rem;">
                                    <span style="font-size: 0.8rem; background: var(--surface-hover); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); border: 1px solid var(--surface-border); font-weight: 600; color: var(--text-primary);">
                                        {{ cat.products_count }} products
                                    </span>
                                </td>

                                <td style="padding: 0.75rem 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    {{ cat.sort_order }}
                                </td>

                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <BaseBadge :variant="cat.is_active ? 'success' : 'neutral'">
                                        {{ cat.is_active ? 'Active' : 'Inactive' }}
                                    </BaseBadge>
                                </td>

                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                    <div class="flex items-center gap-2" style="justify-content: flex-end;">
                                        <button
                                            v-if="can('catalog.categories.manage')"
                                            type="button"
                                            title="Edit Category"
                                            style="padding: 0.35rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-secondary); cursor: pointer;"
                                            @click="openEditModal(cat)"
                                        >
                                            <Edit2 :size="15" style="color: var(--primary);" />
                                        </button>

                                        <button
                                            v-if="can('catalog.categories.manage')"
                                            type="button"
                                            title="Delete Category"
                                            style="padding: 0.35rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-secondary); cursor: pointer;"
                                            @click="deleteCategory(cat)"
                                        >
                                            <Trash2 :size="15" style="color: #dc2626;" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <EmptyState
                v-else
                title="No categories defined"
                description="Create hierarchical categories to organize products and optimize POS navigation."
            >
                <template #action>
                    <BaseButton v-if="can('catalog.categories.manage')" variant="primary" size="sm" @click="openCreateModal">
                        <Plus :size="16" style="margin-right: 0.3rem;" />
                        Add First Category
                    </BaseButton>
                </template>
            </EmptyState>
        </div>

        <!-- Create / Edit Category Modal -->
        <BaseModal
            :show="modalOpen"
            :title="editingCategory ? 'Edit Category' : 'Create Category'"
            @close="modalOpen = false"
        >
            <form style="padding: var(--space-4);" @submit.prevent="submitForm">
                <div style="display: grid; gap: var(--space-4);">
                    <!-- Name -->
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                            Category Name <span style="color: #dc2626;">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Beverages, Electronics, Clothing"
                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                        />
                        <span v-if="form.errors.name" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.name }}</span>
                    </div>

                    <!-- Slug (Optional) -->
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                            Slug (Optional URL key)
                        </label>
                        <input
                            v-model="form.slug"
                            type="text"
                            placeholder="auto-generated from name if left empty"
                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none; font-family: monospace;"
                        />
                        <span v-if="form.errors.slug" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.slug }}</span>
                    </div>

                    <!-- Parent Category -->
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                            Parent Category
                        </label>
                        <select
                            v-model="form.parent_id"
                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                        >
                            <option :value="null">-- None (Top Level Category) --</option>
                            <option 
                                v-for="cat in categories" 
                                :key="cat.id" 
                                :value="cat.id"
                                :disabled="editingCategory?.id === cat.id"
                            >
                                {{ cat.full_path }}
                            </option>
                        </select>
                        <span v-if="form.errors.parent_id" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.parent_id }}</span>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                            Description
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Short category description..."
                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none; resize: vertical;"
                        />
                    </div>

                    <!-- Sort Order & Active -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); align-items: center;">
                        <div>
                            <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                Sort Order
                            </label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                            />
                        </div>

                        <div style="padding-top: 1.25rem;">
                            <label class="flex items-center gap-2" style="cursor: pointer; font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">
                                <input v-model="form.is_active" type="checkbox" style="width: 16px; height: 16px; accent-color: var(--primary);" />
                                <span>Active Category</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3" style="margin-top: var(--space-6);">
                    <BaseButton type="button" variant="secondary" size="md" @click="modalOpen = false">
                        Cancel
                    </BaseButton>
                    <BaseButton type="submit" variant="primary" size="md" :loading="form.processing">
                        <Save :size="16" style="margin-right: 0.3rem;" />
                        <span>{{ editingCategory ? 'Update Category' : 'Create Category' }}</span>
                    </BaseButton>
                </div>
            </form>
        </BaseModal>
    </AppLayout>
</template>
