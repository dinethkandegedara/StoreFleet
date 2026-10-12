<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { usePermission } from '@/Composables/usePermission';
import { 
    Package, 
    Plus, 
    Search, 
    Edit2, 
    Trash2, 
    Power, 
    Layers, 
    Barcode, 
    Filter, 
    X,
    CheckCircle2,
    Boxes,
    ChevronLeft,
    ChevronRight,
    Tag
} from 'lucide-vue-next';

interface CategoryOption {
    id: number;
    name: string;
}

interface ProductItem {
    id: number;
    uuid: string;
    name: string;
    sku: string;
    barcode: string | null;
    brand: string | null;
    product_type: string;
    unit: string;
    selling_price: number;
    cost_price: number | null;
    is_taxable: boolean;
    tax_class: string;
    tax_rate: number;
    image_url: string | null;
    is_active: boolean;
    has_variants: boolean;
    variants_count: number;
    category: { id: number; name: string } | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProducts {
    data: ProductItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    products: PaginatedProducts;
    filters: {
        search: string;
        category_id: number | null;
        status: string;
    };
    categories: CategoryOption[];
}

const props = defineProps<Props>();
const { can } = usePermission();

const searchTerm = ref(props.filters.search);
const selectedCategory = ref<number | null>(props.filters.category_id);
const selectedStatus = ref(props.filters.status);

// Archive modal state
const archiveModalOpen = ref(false);
const productToArchive = ref<ProductItem | null>(null);

const applyFilters = () => {
    router.get('/admin/products', {
        search: searchTerm.value || undefined,
        category_id: selectedCategory.value || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchTerm.value = '';
    selectedCategory.value = null;
    selectedStatus.value = 'all';
    router.get('/admin/products');
};

const toggleActive = (product: ProductItem) => {
    router.post(`/admin/products/${product.id}/toggle-active`, {}, {
        preserveScroll: true,
    });
};

const openArchiveModal = (product: ProductItem) => {
    productToArchive.value = product;
    archiveModalOpen.value = true;
};

const confirmArchive = () => {
    if (!productToArchive.value) return;
    router.delete(`/admin/products/${productToArchive.value.id}`, {
        onSuccess: () => {
            archiveModalOpen.value = false;
            productToArchive.value = null;
        },
    });
};

const formatPrice = (val: number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val);
};
</script>

<template>
    <AppLayout title="Products & Catalog - StoreFleet">
        <Head title="Products & Catalog - StoreFleet" />

        <div style="max-width: 1400px; margin: 0 auto;">
            <!-- Header Section -->
            <div class="flex items-center justify-between" style="margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <Package style="color: var(--primary);" :size="28" />
                        <span>Product Catalog</span>
                        <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted); background: var(--surface-hover); padding: 0.2rem 0.6rem; border-radius: var(--radius-full);">
                            {{ products.total }} items
                        </span>
                    </h1>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        Manage central products, SKU identifiers, barcode pricing, taxonomy categories, and square WebP imagery.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link v-if="can('catalog.categories.manage')" href="/admin/categories">
                        <BaseButton variant="secondary" size="md">
                            <Layers :size="16" style="margin-right: 0.4rem;" />
                            <span>Categories</span>
                        </BaseButton>
                    </Link>

                    <Link v-if="can('catalog.products.create')" href="/admin/products/create">
                        <BaseButton variant="primary" size="md">
                            <Plus :size="18" style="margin-right: 0.4rem;" />
                            <span>Add Product</span>
                        </BaseButton>
                    </Link>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card" style="margin-bottom: var(--space-6); padding: var(--space-4); background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-3); align-items: center;">
                    <!-- Search input -->
                    <div style="position: relative;">
                        <Search :size="16" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);" />
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search name, SKU, barcode, brand..."
                            style="width: 100%; padding: 0.55rem 0.75rem 0.55rem 2.25rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Category filter -->
                    <div>
                        <select
                            v-model="selectedCategory"
                            style="width: 100%; padding: 0.55rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                            @change="applyFilters"
                        >
                            <option :value="null">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Status filter -->
                    <div class="flex items-center gap-1" style="background: var(--background); padding: 0.25rem; border-radius: var(--radius-md); border: 1px solid var(--surface-border);">
                        <button
                            type="button"
                            :style="{
                                padding: '0.35rem 0.75rem',
                                fontSize: '0.8rem',
                                fontWeight: '500',
                                borderRadius: 'var(--radius-sm)',
                                border: 'none',
                                cursor: 'pointer',
                                background: selectedStatus === 'all' ? 'var(--primary)' : 'transparent',
                                color: selectedStatus === 'all' ? '#ffffff' : 'var(--text-secondary)'
                            }"
                            @click="selectedStatus = 'all'; applyFilters()"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            :style="{
                                padding: '0.35rem 0.75rem',
                                fontSize: '0.8rem',
                                fontWeight: '500',
                                borderRadius: 'var(--radius-sm)',
                                border: 'none',
                                cursor: 'pointer',
                                background: selectedStatus === 'active' ? 'var(--primary)' : 'transparent',
                                color: selectedStatus === 'active' ? '#ffffff' : 'var(--text-secondary)'
                            }"
                            @click="selectedStatus = 'active'; applyFilters()"
                        >
                            Active
                        </button>
                        <button
                            type="button"
                            :style="{
                                padding: '0.35rem 0.75rem',
                                fontSize: '0.8rem',
                                fontWeight: '500',
                                borderRadius: 'var(--radius-sm)',
                                border: 'none',
                                cursor: 'pointer',
                                background: selectedStatus === 'inactive' ? 'var(--primary)' : 'transparent',
                                color: selectedStatus === 'inactive' ? '#ffffff' : 'var(--text-secondary)'
                            }"
                            @click="selectedStatus = 'inactive'; applyFilters()"
                        >
                            Inactive
                        </button>
                    </div>

                    <!-- Filter action buttons -->
                    <div class="flex items-center gap-2" style="justify-content: flex-end;">
                        <BaseButton variant="secondary" size="sm" @click="applyFilters">
                            <Filter :size="14" style="margin-right: 0.3rem;" />
                            Apply
                        </BaseButton>
                        <BaseButton v-if="searchTerm || selectedCategory || selectedStatus !== 'all'" variant="ghost" size="sm" @click="resetFilters">
                            <X :size="14" style="margin-right: 0.3rem;" />
                            Clear
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- Products Table or Empty State -->
            <div v-if="products.data.length > 0" class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm);">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--surface-border); background: var(--surface-hover); color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem; width: 64px;">Image</th>
                                <th style="padding: 0.75rem 1rem;">Product Details</th>
                                <th style="padding: 0.75rem 1rem;">SKU &amp; Barcode</th>
                                <th style="padding: 0.75rem 1rem;">Category</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">Selling Price</th>
                                <th style="padding: 0.75rem 1rem; text-align: center;">Status</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="product in products.data" 
                                :key="product.id"
                                style="border-bottom: 1px solid var(--surface-border); transition: background-color 0.15s ease;"
                                class="product-row"
                            >
                                <!-- Thumbnail (Optimized 256x256 WebP rendered at 48x48 CSS) -->
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="width: 48px; height: 48px; border-radius: var(--radius-md); overflow: hidden; background: var(--surface-hover); display: flex; align-items: center; justify-content: center; border: 1px solid var(--surface-border);">
                                        <img 
                                            v-if="product.image_url" 
                                            :src="product.image_url" 
                                            :alt="product.name"
                                            loading="lazy"
                                            style="width: 100%; height: 100%; object-fit: cover;"
                                        />
                                        <Package v-else :size="22" style="color: var(--text-muted);" />
                                    </div>
                                </td>

                                <!-- Product Details -->
                                <td style="padding: 0.75rem 1rem;">
                                    <div class="flex items-center gap-2">
                                        <span style="font-weight: 600; color: var(--text-primary);">{{ product.name }}</span>
                                        <span v-if="product.has_variants" style="font-size: 0.7rem; background: rgba(99, 102, 241, 0.12); color: #6366f1; padding: 0.1rem 0.4rem; border-radius: var(--radius-sm); font-weight: 600;">
                                            {{ product.variants_count }} variants
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2" style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                        <span v-if="product.brand">{{ product.brand }} &bull;</span>
                                        <span style="text-transform: capitalize;">{{ product.product_type }}</span>
                                        <span>&bull; {{ product.unit }}</span>
                                    </div>
                                </td>

                                <!-- SKU & Barcode -->
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="font-family: monospace; font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">
                                        {{ product.sku }}
                                    </div>
                                    <div v-if="product.barcode" class="flex items-center gap-1" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        <Barcode :size="13" />
                                        <span>{{ product.barcode }}</span>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td style="padding: 0.75rem 1rem;">
                                    <BaseBadge v-if="product.category" variant="neutral">
                                        {{ product.category.name }}
                                    </BaseBadge>
                                    <span v-else style="font-size: 0.8rem; color: var(--text-muted);">&mdash;</span>
                                </td>

                                <!-- Selling Price -->
                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                    <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                                        ${{ formatPrice(product.selling_price) }}
                                    </div>
                                    <div v-if="product.cost_price" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">
                                        Cost: ${{ formatPrice(product.cost_price) }}
                                    </div>
                                </td>

                                <!-- Active Status -->
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <BaseBadge :variant="product.is_active ? 'success' : 'neutral'">
                                        {{ product.is_active ? 'Active' : 'Inactive' }}
                                    </BaseBadge>
                                </td>

                                <!-- Actions -->
                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                    <div class="flex items-center gap-2" style="justify-content: flex-end;">
                                        <!-- Toggle Active -->
                                        <button
                                            v-if="can('catalog.products.edit')"
                                            type="button"
                                            :title="product.is_active ? 'Deactivate product' : 'Activate product'"
                                            style="padding: 0.35rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-secondary); cursor: pointer; transition: all 0.15s ease;"
                                            @click="toggleActive(product)"
                                        >
                                            <Power :size="15" :style="{ color: product.is_active ? '#059669' : '#dc2626' }" />
                                        </button>

                                        <!-- Edit Product -->
                                        <Link v-if="can('catalog.products.edit')" :href="`/admin/products/${product.id}/edit`">
                                            <button
                                                type="button"
                                                title="Edit product"
                                                style="padding: 0.35rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-secondary); cursor: pointer; transition: all 0.15s ease;"
                                            >
                                                <Edit2 :size="15" style="color: var(--primary);" />
                                            </button>
                                        </Link>

                                        <!-- Archive / Soft-delete -->
                                        <button
                                            v-if="can('catalog.products.delete')"
                                            type="button"
                                            title="Archive product"
                                            style="padding: 0.35rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-secondary); cursor: pointer; transition: all 0.15s ease;"
                                            @click="openArchiveModal(product)"
                                        >
                                            <Trash2 :size="15" style="color: #dc2626;" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Server-Side Pagination Bar -->
                <div class="flex items-center justify-between" style="padding: var(--space-4); border-top: 1px solid var(--surface-border); background: var(--surface-hover); flex-wrap: wrap; gap: var(--space-3);">
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                        Showing <span style="font-weight: 600; color: var(--text-primary);">{{ products.from ?? 0 }}</span>
                        to <span style="font-weight: 600; color: var(--text-primary);">{{ products.to ?? 0 }}</span>
                        of <span style="font-weight: 600; color: var(--text-primary);">{{ products.total }}</span> products
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in products.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :style="{
                                    padding: '0.35rem 0.75rem',
                                    fontSize: '0.85rem',
                                    borderRadius: 'var(--radius-sm)',
                                    textDecoration: 'none',
                                    border: '1px solid var(--surface-border)',
                                    background: link.active ? 'var(--primary)' : 'var(--surface)',
                                    color: link.active ? '#ffffff' : 'var(--text-primary)',
                                    fontWeight: link.active ? '600' : '400',
                                    transition: 'all 0.15s ease',
                                }"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                style="padding: 0.35rem 0.75rem; font-size: 0.85rem; color: var(--text-muted); cursor: not-allowed;"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <EmptyState
                v-else
                title="No products found"
                description="No products match your current search criteria or active filters. Try clearing your filters or create a new catalog item."
            >
                <template #action>
                    <div class="flex items-center gap-3">
                        <BaseButton v-if="searchTerm || selectedCategory || selectedStatus !== 'all'" variant="secondary" size="sm" @click="resetFilters">
                            Clear Filters
                        </BaseButton>
                        <Link v-if="can('catalog.products.create')" href="/admin/products/create">
                            <BaseButton variant="primary" size="sm">
                                <Plus :size="16" style="margin-right: 0.3rem;" />
                                Add First Product
                            </BaseButton>
                        </Link>
                    </div>
                </template>
            </EmptyState>
        </div>

        <!-- Archive Confirmation Modal (Historical Preservation) -->
        <BaseModal
            :show="archiveModalOpen"
            title="Archive Product"
            @close="archiveModalOpen = false"
        >
            <div style="padding: var(--space-4);">
                <p style="color: var(--text-primary); margin-bottom: var(--space-3); font-size: 0.95rem;">
                    Are you sure you want to archive <strong>"{{ productToArchive?.name }}"</strong> (SKU: {{ productToArchive?.sku }})?
                </p>

                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-md); padding: 0.75rem; margin-bottom: var(--space-4); display: flex; gap: 0.5rem; align-items: flex-start;">
                    <CheckCircle2 :size="18" style="color: #f59e0b; flex-shrink: 0; margin-top: 0.1rem;" />
                    <span style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4;">
                        <strong>Historical Data Safety:</strong> Archiving will hide this product from active POS search and catalog creation, but will <em>never rewrite or delete</em> past receipts and sales transactions.
                    </span>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <BaseButton variant="secondary" size="md" @click="archiveModalOpen = false">
                        Cancel
                    </BaseButton>
                    <BaseButton variant="danger" size="md" @click="confirmArchive">
                        Archive Product
                    </BaseButton>
                </div>
            </div>
        </BaseModal>
    </AppLayout>
</template>

<style scoped>
.product-row:hover {
    background-color: var(--surface-hover);
}
</style>
