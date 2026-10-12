<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseSelect from '@/Components/Forms/BaseSelect.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import { 
    Package, 
    ArrowLeft, 
    Save, 
    Upload, 
    Trash2, 
    Plus, 
    Sparkles, 
    Layers, 
    Info, 
    DollarSign, 
    Percent, 
    Image as ImageIcon,
    CheckCircle2
} from 'lucide-vue-next';

interface CategoryOption {
    id: number;
    name: string;
}

interface VariantItem {
    id?: number;
    name: string;
    sku: string;
    barcode?: string | null;
    selling_price: number | string;
    cost_price?: number | string | null;
    is_active: boolean;
}

interface ProductData {
    id?: number;
    uuid?: string;
    name: string;
    sku: string;
    barcode?: string | null;
    category_id?: number | null;
    brand?: string | null;
    product_type: string;
    unit: string;
    selling_price: number | string;
    cost_price?: number | string | null;
    is_taxable: boolean;
    tax_class: string;
    tax_rate: number | string;
    image_url?: string | null;
    image_path?: string | null;
    description?: string | null;
    notes?: string | null;
    tags?: string[];
    has_variants: boolean;
    is_active: boolean;
    variants?: VariantItem[];
}

interface Props {
    mode: 'create' | 'edit';
    product: ProductData | null;
    categories: CategoryOption[];
    standardUnits: string[];
    taxClasses: { value: string; label: string }[];
}

const props = defineProps<Props>();

// Initialize form
const form = useForm({
    _method: props.mode === 'edit' ? 'PUT' : 'POST',
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    category_id: props.product?.category_id ?? null,
    brand: props.product?.brand ?? '',
    product_type: props.product?.product_type ?? 'standard',
    unit: props.product?.unit ?? 'piece',
    selling_price: props.product?.selling_price ?? '',
    cost_price: props.product?.cost_price ?? '',
    is_taxable: props.product?.is_taxable ?? true,
    tax_class: props.product?.tax_class ?? 'standard',
    tax_rate: props.product?.tax_rate ?? 0,
    description: props.product?.description ?? '',
    notes: props.product?.notes ?? '',
    tags: props.product?.tags ?? [],
    has_variants: props.product?.has_variants ?? false,
    is_active: props.product?.is_active ?? true,
    image: null as File | null,
    remove_image: false,
    variants: (props.product?.variants && props.product.variants.length > 0)
        ? props.product.variants.map(v => ({
            id: v.id,
            name: v.name,
            sku: v.sku,
            barcode: v.barcode ?? '',
            selling_price: v.selling_price,
            cost_price: v.cost_price ?? '',
            is_active: v.is_active ?? true,
        }))
        : [] as VariantItem[],
});

// Image preview state
const clientImagePreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const handleImageChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        form.image = file;
        form.remove_image = false;

        const reader = new FileReader();
        reader.onload = (event) => {
            clientImagePreview.value = event.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
};

const removeSelectedImage = () => {
    form.image = null;
    clientImagePreview.value = null;
    form.remove_image = true;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const generateSku = () => {
    const prefix = form.name ? form.name.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, 'PRD') : 'PRD';
    const random = Math.floor(100000 + Math.random() * 900000);
    form.sku = `${prefix}-${random}`;
};

const addVariant = () => {
    const count = form.variants.length + 1;
    const variantSku = form.sku ? `${form.sku}-V${count}` : `VAR-${Math.floor(10000 + Math.random() * 90000)}`;
    form.variants.push({
        name: `Variant ${count}`,
        sku: variantSku,
        barcode: '',
        selling_price: form.selling_price || 0,
        cost_price: form.cost_price || 0,
        is_active: true,
    });
};

const removeVariant = (index: number) => {
    form.variants.splice(index, 1);
};

// Tag handling
const tagInput = ref('');
const addTag = () => {
    const trimmed = tagInput.value.trim();
    if (trimmed && !form.tags.includes(trimmed)) {
        form.tags.push(trimmed);
        tagInput.value = '';
    }
};
const removeTag = (index: number) => {
    form.tags.splice(index, 1);
};

const submit = () => {
    if (props.mode === 'create') {
        form.post('/admin/products', {
            forceFormData: true,
        });
    } else {
        form.post(`/admin/products/${props.product?.id}`, {
            forceFormData: true,
        });
    }
};
</script>

<template>
    <AppLayout :title="mode === 'create' ? 'Create Product - StoreFleet' : 'Edit Product - StoreFleet'">
        <Head :title="mode === 'create' ? 'Create Product - StoreFleet' : `Edit ${product?.name} - StoreFleet`" />

        <div style="max-width: 1200px; margin: 0 auto; padding-bottom: var(--space-8);">
            <!-- Breadcrumbs / Back button -->
            <div class="flex items-center justify-between" style="margin-bottom: var(--space-5);">
                <Link href="/admin/products" class="flex items-center gap-2" style="color: var(--text-secondary); text-decoration: none; font-size: 0.875rem;">
                    <ArrowLeft :size="16" />
                    <span>Back to Catalog</span>
                </Link>

                <div v-if="product?.uuid" style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">
                    UUID: {{ product.uuid }}
                </div>
            </div>

            <!-- Page Title Header -->
            <div class="flex items-center justify-between" style="margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <Package style="color: var(--primary);" :size="28" />
                        <span>{{ mode === 'create' ? 'Add New Product' : `Edit "${product?.name}"` }}</span>
                    </h1>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        {{ mode === 'create' ? 'Configure product pricing, SKU, taxonomy, and optimized square thumbnail.' : 'Update product details, pricing, variants, or replace product imagery safely.' }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link href="/admin/products">
                        <BaseButton variant="secondary" size="md">Cancel</BaseButton>
                    </Link>
                    <BaseButton variant="primary" size="md" :loading="form.processing" @click="submit">
                        <Save :size="16" style="margin-right: 0.4rem;" />
                        <span>{{ mode === 'create' ? 'Save Product' : 'Update Product' }}</span>
                    </BaseButton>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-6); align-items: start;">
                    <!-- Left Column: Core Fields -->
                    <div style="display: flex; flex-direction: column; gap: var(--space-6);">
                        <!-- Basic Information Card -->
                        <div class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: var(--space-6);">
                            <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: 0.5rem;">
                                <Package :size="20" style="color: var(--primary);" />
                                <span>General Information</span>
                            </h2>

                            <div style="display: grid; gap: var(--space-4);">
                                <!-- Name -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Product Name <span style="color: #dc2626;">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        required
                                        placeholder="e.g. Premium Wireless Headphones"
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                    />
                                    <span v-if="form.errors.name" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.name }}</span>
                                </div>

                                <!-- SKU & Barcode Row -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                                    <div>
                                        <div class="flex items-center justify-between" style="margin-bottom: 0.25rem;">
                                            <label class="form-label" style="font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">
                                                SKU (Internal Ref) <span style="color: #dc2626;">*</span>
                                            </label>
                                            <button
                                                type="button"
                                                style="background: none; border: none; font-size: 0.75rem; color: var(--primary); cursor: pointer; display: flex; align-items: center; gap: 0.2rem;"
                                                @click="generateSku"
                                            >
                                                <Sparkles :size="12" />
                                                <span>Auto-generate</span>
                                            </button>
                                        </div>
                                        <input
                                            v-model="form.sku"
                                            type="text"
                                            required
                                            placeholder="e.g. HEAD-00123"
                                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none; font-family: monospace;"
                                        />
                                        <span v-if="form.errors.sku" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.sku }}</span>
                                    </div>

                                    <div>
                                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                            Barcode (UPC / EAN / ISBN)
                                        </label>
                                        <input
                                            v-model="form.barcode"
                                            type="text"
                                            placeholder="e.g. 7350012345678"
                                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                        />
                                        <span v-if="form.errors.barcode" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.barcode }}</span>
                                    </div>
                                </div>

                                <!-- Category & Brand Row -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                                    <div>
                                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                            Taxonomy Category
                                        </label>
                                        <select
                                            v-model="form.category_id"
                                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                        >
                                            <option :value="null">-- No Category Selected --</option>
                                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                                {{ cat.name }}
                                            </option>
                                        </select>
                                        <span v-if="form.errors.category_id" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.category_id }}</span>
                                    </div>

                                    <div>
                                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                            Brand / Manufacturer
                                        </label>
                                        <input
                                            v-model="form.brand"
                                            type="text"
                                            placeholder="e.g. Sony, Nike, Generic"
                                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                        />
                                        <span v-if="form.errors.brand" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.brand }}</span>
                                    </div>
                                </div>

                                <!-- Product Type & Unit of Measure -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                                    <div>
                                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                            Product Type <span style="color: #dc2626;">*</span>
                                        </label>
                                        <select
                                            v-model="form.product_type"
                                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                        >
                                            <option value="standard">Standard Physical Product</option>
                                            <option value="composite">Composite / Bundle</option>
                                            <option value="service">Service / Non-inventory</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                            Unit of Measure <span style="color: #dc2626;">*</span>
                                        </label>
                                        <select
                                            v-model="form.unit"
                                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                        >
                                            <option v-for="unit in standardUnits" :key="unit" :value="unit">
                                                {{ unit }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Description &amp; Specifications
                                    </label>
                                    <textarea
                                        v-model="form.description"
                                        rows="3"
                                        placeholder="Detailed customer-facing product description..."
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none; resize: vertical;"
                                    />
                                </div>

                                <!-- Internal Notes -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Internal Notes (Private)
                                    </label>
                                    <textarea
                                        v-model="form.notes"
                                        rows="2"
                                        placeholder="Handling notes, reorder reminders, warranty terms..."
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none; resize: vertical;"
                                    />
                                </div>

                                <!-- Tags -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Tags
                                    </label>
                                    <div class="flex items-center gap-2" style="margin-bottom: 0.5rem;">
                                        <input
                                            v-model="tagInput"
                                            type="text"
                                            placeholder="Type tag and press enter (e.g. Summer, Featured)"
                                            style="flex: 1; padding: 0.5rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                            @keydown.enter.prevent="addTag"
                                        />
                                        <BaseButton type="button" variant="secondary" size="sm" @click="addTag">Add</BaseButton>
                                    </div>
                                    <div v-if="form.tags.length > 0" class="flex items-center gap-1" style="flex-wrap: wrap;">
                                        <span 
                                            v-for="(tag, idx) in form.tags" 
                                            :key="idx" 
                                            style="display: inline-flex; align-items: center; gap: 0.3rem; background: var(--surface-hover); border: 1px solid var(--surface-border); padding: 0.2rem 0.6rem; border-radius: var(--radius-full); font-size: 0.75rem; color: var(--text-secondary);"
                                        >
                                            {{ tag }}
                                            <button type="button" style="border: none; background: none; cursor: pointer; color: var(--text-muted); padding: 0;" @click="removeTag(idx)">&times;</button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Tax Card -->
                        <div class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: var(--space-6);">
                            <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: 0.5rem;">
                                <DollarSign :size="20" style="color: var(--primary);" />
                                <span>Pricing &amp; Taxation</span>
                            </h2>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                                <!-- Selling Price -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Selling Price ($) <span style="color: #dc2626;">*</span>
                                    </label>
                                    <input
                                        v-model="form.selling_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        placeholder="0.00"
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                    />
                                    <span v-if="form.errors.selling_price" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.selling_price }}</span>
                                </div>

                                <!-- Cost Price -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Cost Price ($) (Optional)
                                    </label>
                                    <input
                                        v-model="form.cost_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                    />
                                    <span v-if="form.errors.cost_price" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.cost_price }}</span>
                                </div>

                                <!-- Tax Class -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Tax Class
                                    </label>
                                    <select
                                        v-model="form.tax_class"
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                    >
                                        <option v-for="tc in taxClasses" :key="tc.value" :value="tc.value">
                                            {{ tc.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Tax Rate % -->
                                <div>
                                    <label class="form-label" style="display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                                        Tax Rate (%)
                                    </label>
                                    <input
                                        v-model="form.tax_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        placeholder="0.00"
                                        style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); color: var(--text-primary); outline: none;"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Product Variants Card -->
                        <div class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: var(--space-6);">
                            <div class="flex items-center justify-between" style="margin-bottom: var(--space-4);">
                                <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                                    <Layers :size="20" style="color: var(--primary);" />
                                    <span>Product Variants</span>
                                </h2>

                                <label class="flex items-center gap-2" style="cursor: pointer; font-size: 0.875rem; font-weight: 500; color: var(--text-primary);">
                                    <input v-model="form.has_variants" type="checkbox" style="width: 16px; height: 16px; accent-color: var(--primary);" />
                                    <span>Enable Variants</span>
                                </label>
                            </div>

                            <div v-if="form.has_variants">
                                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: var(--space-4);">
                                    Define distinct options (e.g. Size, Color) with specific SKU identifiers and individual pricing.
                                </p>

                                <div v-if="form.variants.length > 0" style="display: flex; flex-direction: column; gap: var(--space-3); margin-bottom: var(--space-4);">
                                    <div 
                                        v-for="(variant, idx) in form.variants" 
                                        :key="idx"
                                        style="padding: var(--space-3); border: 1px solid var(--surface-border); border-radius: var(--radius-md); background: var(--background); display: grid; grid-template-columns: 2fr 2fr 1.5fr 1.5fr auto; gap: var(--space-2); align-items: center;"
                                    >
                                        <!-- Variant Name -->
                                        <div>
                                            <input 
                                                v-model="variant.name" 
                                                type="text" 
                                                placeholder="Variant Name (e.g. Blue / L)"
                                                style="width: 100%; padding: 0.45rem 0.6rem; font-size: 0.8rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-primary);"
                                            />
                                        </div>

                                        <!-- Variant SKU -->
                                        <div>
                                            <input 
                                                v-model="variant.sku" 
                                                type="text" 
                                                placeholder="Variant SKU"
                                                style="width: 100%; padding: 0.45rem 0.6rem; font-size: 0.8rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-primary); font-family: monospace;"
                                            />
                                        </div>

                                        <!-- Selling Price -->
                                        <div>
                                            <input 
                                                v-model="variant.selling_price" 
                                                type="number" 
                                                step="0.01"
                                                placeholder="Price ($)"
                                                style="width: 100%; padding: 0.45rem 0.6rem; font-size: 0.8rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-primary);"
                                            />
                                        </div>

                                        <!-- Barcode -->
                                        <div>
                                            <input 
                                                v-model="variant.barcode" 
                                                type="text" 
                                                placeholder="Barcode"
                                                style="width: 100%; padding: 0.45rem 0.6rem; font-size: 0.8rem; border: 1px solid var(--surface-border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-primary);"
                                            />
                                        </div>

                                        <!-- Remove button -->
                                        <div>
                                            <button 
                                                type="button" 
                                                style="background: none; border: none; color: #dc2626; cursor: pointer; padding: 0.4rem;"
                                                @click="removeVariant(idx)"
                                            >
                                                <Trash2 :size="16" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <BaseButton type="button" variant="secondary" size="sm" @click="addVariant">
                                    <Plus :size="14" style="margin-right: 0.3rem;" />
                                    <span>Add Another Variant</span>
                                </BaseButton>
                            </div>
                            <div v-else style="font-size: 0.85rem; color: var(--text-muted);">
                                Standard product mode without variants. Check "Enable Variants" above to manage variant options.
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Image & Publishing -->
                    <div style="display: flex; flex-direction: column; gap: var(--space-6);">
                        <!-- Optimized Image Pipeline Card -->
                        <div class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: var(--space-6);">
                            <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-3); display: flex; align-items: center; gap: 0.5rem;">
                                <ImageIcon :size="20" style="color: var(--primary);" />
                                <span>Product Thumbnail</span>
                            </h2>

                            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: var(--space-4); line-height: 1.4;">
                                Uploaded files are validated, auto-oriented, center-cropped into square thumbnails, resized to 256×256 pixels, and compressed to WebP.
                            </p>

                            <!-- Image Preview Display Area -->
                            <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: var(--space-4);">
                                <div 
                                    style="width: 200px; height: 200px; border-radius: var(--radius-lg); border: 2px dashed var(--surface-border); display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--background); position: relative; box-shadow: var(--shadow-sm);"
                                >
                                    <!-- Client Selected Preview -->
                                    <img 
                                        v-if="clientImagePreview" 
                                        :src="clientImagePreview" 
                                        alt="Selected image preview"
                                        style="width: 100%; height: 100%; object-fit: cover;"
                                    />
                                    <!-- Existing Server Image -->
                                    <img 
                                        v-else-if="product?.image_url && !form.remove_image" 
                                        :src="product.image_url" 
                                        :alt="product.name"
                                        style="width: 100%; height: 100%; object-fit: cover;"
                                    />
                                    <!-- No Image Placeholder -->
                                    <div v-else style="display: flex; flex-direction: column; align-items: center; gap: 0.5rem; color: var(--text-muted); text-align: center; padding: 1rem;">
                                        <Package :size="40" style="opacity: 0.5;" />
                                        <span style="font-size: 0.75rem;">No thumbnail selected</span>
                                    </div>
                                </div>

                                <div v-if="clientImagePreview || (product?.image_url && !form.remove_image)" style="margin-top: 0.5rem;">
                                    <button 
                                        type="button" 
                                        style="background: none; border: none; font-size: 0.75rem; color: #dc2626; cursor: pointer; display: flex; align-items: center; gap: 0.2rem;"
                                        @click="removeSelectedImage"
                                    >
                                        <Trash2 :size="12" />
                                        <span>Remove Image</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Upload Input Button -->
                            <div>
                                <input 
                                    ref="fileInput"
                                    type="file" 
                                    accept="image/jpeg,image/png,image/webp" 
                                    style="display: none;" 
                                    @change="handleImageChange"
                                />
                                <BaseButton 
                                    type="button" 
                                    variant="secondary" 
                                    size="sm" 
                                    style="width: 100%;" 
                                    @click="fileInput?.click()"
                                >
                                    <Upload :size="14" style="margin-right: 0.4rem;" />
                                    <span>Choose New Image</span>
                                </BaseButton>
                                <span v-if="form.errors.image" style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem; display: block;">{{ form.errors.image }}</span>
                            </div>

                            <div style="margin-top: var(--space-3); padding: 0.5rem; background: var(--surface-hover); border-radius: var(--radius-sm); font-size: 0.7rem; color: var(--text-muted);">
                                Supported: JPEG, PNG, WebP (Max 5MB). Strips EXIF metadata automatically.
                            </div>
                        </div>

                        <!-- Publishing Lifecycle Card -->
                        <div class="card" style="background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius-lg); padding: var(--space-6);">
                            <h2 style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-3);">
                                Status &amp; Lifecycle
                            </h2>

                            <label class="flex items-center gap-3" style="cursor: pointer;">
                                <input 
                                    v-model="form.is_active" 
                                    type="checkbox" 
                                    style="width: 18px; height: 18px; accent-color: var(--primary);" 
                                />
                                <div>
                                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--text-primary);">
                                        {{ form.is_active ? 'Active Product' : 'Inactive / Draft' }}
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        {{ form.is_active ? 'Available for sale in POS terminals and active catalog search.' : 'Hidden from POS checkout and sales search.' }}
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
