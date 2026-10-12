<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/vite-env';
import PosLayout from '@/Layouts/PosLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import { useToast } from '@/Composables/useToast';
import { 
    Search, 
    Barcode, 
    ShoppingCart, 
    Trash2, 
    Plus, 
    Minus, 
    DollarSign, 
    CreditCard, 
    Building2, 
    CheckCircle2, 
    AlertCircle, 
    Printer, 
    Layers, 
    Tag, 
    User, 
    X,
    Sparkles,
    ArrowRight,
    RefreshCw
} from 'lucide-vue-next';

interface Variant {
    id: number;
    name: string;
    sku: string;
    barcode?: string | null;
    selling_price: number;
}

interface Product {
    id: number;
    uuid: string;
    name: string;
    sku: string;
    barcode?: string | null;
    brand?: string | null;
    unit: string;
    selling_price: number;
    is_taxable: boolean;
    tax_class: string;
    tax_rate: number;
    image_url?: string | null;
    category_id?: number | null;
    has_variants: boolean;
    variants: Variant[];
}

interface Category {
    id: number;
    name: string;
    full_path: string;
}

interface CartItem {
    id: string; // unique cart line ID (product_id + variant_id)
    product_id: number;
    product_variant_id?: number | null;
    name: string;
    sku: string;
    barcode?: string | null;
    unit: string;
    unit_price: number;
    quantity: number;
    discount_amount: number;
    is_taxable: boolean;
    tax_rate: number;
}

interface PaymentEntry {
    payment_method: 'cash' | 'card' | 'bank_transfer' | 'other';
    amount: number;
    tendered_amount?: number;
    reference_note?: string;
}

interface CompletedSale {
    id: number;
    sale_number: string;
    total_amount: number;
    subtotal: number;
    tax_total: number;
    discount_total: number;
    completed_at: string;
    payments: Array<{
        payment_method: string;
        amount: number;
        tendered_amount?: number;
        change_amount: number;
    }>;
}

interface Props {
    activeBranch: {
        id: number;
        name: string;
        code: string;
        receipt_header?: string;
        receipt_footer?: string;
    };
    authorizedBranches: Array<{ id: number; name: string; code: string }>;
    products: Product[];
    categories: Category[];
    settings: {
        business_name: string;
        currency_code: string;
        currency_symbol: string;
        currency_precision: number;
        currency_symbol_position: string;
        tax_enabled: boolean;
        default_tax_rate: number;
        prices_include_tax: boolean;
    };
}

const props = defineProps<Props>();
const page = usePage<SharedPageProps>();
const toast = useToast();

// State
const searchQuery = ref('');
const selectedCategoryId = ref<number | null>(null);
const cart = ref<CartItem[]>([]);
const orderDiscount = ref<number>(0);
const customerName = ref('');
const customerPhone = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);

// Variant selection modal
const variantModalOpen = ref(false);
const activeProductForVariants = ref<Product | null>(null);

// Payment modal state
const paymentModalOpen = ref(false);
const selectedPaymentMethod = ref<'cash' | 'card' | 'bank_transfer' | 'other'>('cash');
const tenderedCash = ref<number | string>('');
const cardReference = ref('');
const isProcessingCheckout = ref(false);

// Completed sale modal state
const receiptModalOpen = ref(false);
const completedSale = ref<CompletedSale | null>(null);

// Storage key for cart persistence
const storageKey = computed(() => `storefleet_pos_cart_${props.activeBranch.id}`);

// Filtered products list
const filteredProducts = computed(() => {
    let list = props.products;

    if (selectedCategoryId.value) {
        list = list.filter(p => p.category_id === selectedCategoryId.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter(p => 
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            (p.barcode && p.barcode.toLowerCase().includes(q)) ||
            (p.brand && p.brand.toLowerCase().includes(q))
        );
    }

    return list;
});

// Format monetary amounts
const formatMoney = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: props.settings.currency_precision || 2,
        maximumFractionDigits: props.settings.currency_precision || 2,
    }).format(amount);
};

// Cart Calculations
const cartSubtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        const lineNet = Math.max(0, (item.unit_price * item.quantity) - item.discount_amount);
        return sum + lineNet;
    }, 0);
});

const cartTaxTotal = computed(() => {
    if (!props.settings.tax_enabled) return 0;

    return cart.value.reduce((sum, item) => {
        if (!item.is_taxable) return sum;
        const rate = item.tax_rate > 0 ? item.tax_rate : props.settings.default_tax_rate;
        const lineNet = Math.max(0, (item.unit_price * item.quantity) - item.discount_amount);

        if (props.settings.prices_include_tax) {
            const tax = lineNet - (lineNet / (1 + (rate / 100)));
            return sum + tax;
        } else {
            return sum + (lineNet * (rate / 100));
        }
    }, 0);
});

const cartGrandTotal = computed(() => {
    let total = 0;
    if (props.settings.prices_include_tax) {
        total = cartSubtotal.value - orderDiscount.value;
    } else {
        total = cartSubtotal.value + cartTaxTotal.value - orderDiscount.value;
    }
    return Math.max(0, Math.round(total * 100) / 100);
});

// Change calculation
const cashChange = computed(() => {
    const tendered = Number(tenderedCash.value) || 0;
    const diff = tendered - cartGrandTotal.value;
    return diff > 0 ? Math.round(diff * 100) / 100 : 0;
});

// Cart item management
const addToCart = (product: Product, variant?: Variant) => {
    if (product.has_variants && !variant) {
        activeProductForVariants.value = product;
        variantModalOpen.value = true;
        return;
    }

    const cartLineId = variant ? `${product.id}_v${variant.id}` : `${product.id}`;
    const existingIndex = cart.value.findIndex(item => item.id === cartLineId);

    const price = variant ? variant.selling_price : product.selling_price;
    const name = variant ? `${product.name} (${variant.name})` : product.name;
    const sku = variant ? variant.sku : product.sku;
    const barcode = variant ? (variant.barcode || product.barcode) : product.barcode;

    if (existingIndex > -1) {
        cart.value[existingIndex].quantity += 1;
    } else {
        cart.value.push({
            id: cartLineId,
            product_id: product.id,
            product_variant_id: variant?.id || null,
            name,
            sku,
            barcode,
            unit: product.unit,
            unit_price: price,
            quantity: 1,
            discount_amount: 0,
            is_taxable: product.is_taxable,
            tax_rate: product.tax_rate,
        });
    }

    if (variantModalOpen.value) {
        variantModalOpen.value = false;
        activeProductForVariants.value = null;
    }

    toast.success(`Added ${name} to cart`);
};

const updateQuantity = (index: number, delta: number) => {
    const item = cart.value[index];
    if (!item) return;

    item.quantity = Math.max(1, item.quantity + delta);
};

const removeFromCart = (index: number) => {
    cart.value.splice(index, 1);
};

const clearCart = () => {
    if (cart.value.length === 0) return;
    if (confirm('Are you sure you want to clear all items in the current cart?')) {
        cart.value = [];
        orderDiscount.value = 0;
        customerName.value = '';
        customerPhone.value = '';
        localStorage.removeItem(storageKey.value);
        toast.info('Cart cleared');
    }
};

// Barcode & Keyboard listener
const handleBarcodeInput = () => {
    const term = searchQuery.value.trim();
    if (!term) return;

    // 1. Exact match by Barcode
    const barcodeMatch = props.products.find(p => p.barcode === term);
    if (barcodeMatch) {
        addToCart(barcodeMatch);
        searchQuery.value = '';
        return;
    }

    // 2. Exact match by Variant Barcode
    for (const p of props.products) {
        const vMatch = p.variants?.find(v => v.barcode === term);
        if (vMatch) {
            addToCart(p, vMatch);
            searchQuery.value = '';
            return;
        }
    }

    // 3. Exact match by SKU
    const skuMatch = props.products.find(p => p.sku.toLowerCase() === term.toLowerCase());
    if (skuMatch) {
        addToCart(skuMatch);
        searchQuery.value = '';
        return;
    }

    // If only one product in filtered list, add it
    if (filteredProducts.value.length === 1) {
        addToCart(filteredProducts.value[0]);
        searchQuery.value = '';
    }
};

// Open checkout payment modal
const openPaymentModal = () => {
    if (cart.value.length === 0) {
        toast.warning('Cart is empty. Add items first.');
        return;
    }
    selectedPaymentMethod.value = 'cash';
    tenderedCash.value = cartGrandTotal.value; // default to exact cash
    cardReference.value = '';
    paymentModalOpen.value = true;
};

// Quick cash tender buttons
const setExactCash = () => {
    tenderedCash.value = cartGrandTotal.value;
};

const setQuickCash = (amount: number) => {
    tenderedCash.value = amount;
};

// Submit checkout
const submitCheckout = () => {
    if (isProcessingCheckout.value) return;

    const tenderedNum = Number(tenderedCash.value) || 0;
    if (selectedPaymentMethod.value === 'cash' && tenderedNum < cartGrandTotal.value) {
        toast.error(`Tendered cash ($${tenderedNum}) is less than total ($${cartGrandTotal.value}).`);
        return;
    }

    isProcessingCheckout.value = true;

    // Generate unique idempotency key
    const idempotencyKey = (typeof crypto !== 'undefined' && crypto.randomUUID) 
        ? crypto.randomUUID() 
        : `sf_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;

    const payments: PaymentEntry[] = [
        {
            payment_method: selectedPaymentMethod.value,
            amount: cartGrandTotal.value,
            tendered_amount: selectedPaymentMethod.value === 'cash' ? tenderedNum : cartGrandTotal.value,
            reference_note: selectedPaymentMethod.value !== 'cash' ? cardReference.value : undefined,
        }
    ];

    const payload = {
        branch_id: props.activeBranch.id,
        idempotency_key: idempotencyKey,
        items: cart.value.map(item => ({
            product_id: item.product_id,
            product_variant_id: item.product_variant_id || null,
            quantity: item.quantity,
            discount_amount: item.discount_amount || 0,
        })),
        discount_total: orderDiscount.value || 0,
        customer_name: customerName.value || null,
        customer_phone: customerPhone.value || null,
        payments,
    };

    router.post('/pos/checkout', payload as any, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (pageObj) => {
            isProcessingCheckout.value = false;
            paymentModalOpen.value = false;

            // Extract completed sale from session flash
            const sale = (pageObj.props as any).completed_sale as CompletedSale | undefined;
            if (sale) {
                completedSale.value = sale;
                receiptModalOpen.value = true;
            } else {
                toast.success('Sale completed successfully!');
            }

            // Clear local cart
            cart.value = [];
            orderDiscount.value = 0;
            customerName.value = '';
            customerPhone.value = '';
            localStorage.removeItem(storageKey.value);
        },
        onError: (errors) => {
            isProcessingCheckout.value = false;
            const msg = Object.values(errors)[0] as string || 'Checkout failed. Please review cart details.';
            toast.error(msg);
        },
    });
};

// Print thermal receipt emulation
const printReceipt = () => {
    window.print();
};

const startNewSale = () => {
    receiptModalOpen.value = false;
    completedSale.value = null;
    nextTick(() => {
        searchInputRef.value?.focus();
    });
};

// Local storage backup & window protection
watch(cart, (newCart) => {
    if (newCart.length > 0) {
        localStorage.setItem(storageKey.value, JSON.stringify(newCart));
    } else {
        localStorage.removeItem(storageKey.value);
    }
}, { deep: true });

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (cart.value.length > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
};

onMounted(() => {
    // Restore cart from storage if available
    const saved = localStorage.getItem(storageKey.value);
    if (saved) {
        try {
            cart.value = JSON.parse(saved);
        } catch (e) {
            // ignore corrupted cache
        }
    }

    window.addEventListener('beforeunload', handleBeforeUnload);

    // Auto-focus barcode search input
    nextTick(() => {
        searchInputRef.value?.focus();
    });
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <PosLayout :branch-name="activeBranch.name" :cashier-name="page.props.auth?.user?.name || 'Cashier'">
        <Head title="POS Register - StoreFleet" />

        <div class="pos-workspace" style="display: flex; width: 100%; height: 100%; overflow: hidden;">
            <!-- Left Panel: Catalog Engine & Search (60%) -->
            <div class="pos-catalog" style="flex: 1.4; display: flex; flex-direction: column; border-right: 1px solid var(--border-subtle); background: var(--bg-surface); overflow: hidden;">
                <!-- Search & Barcode Scan Bar -->
                <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-subtle); display: flex; gap: var(--space-3); align-items: center; background: var(--bg-surface);">
                    <div style="position: relative; flex: 1;">
                        <Barcode :size="18" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);" />
                        <input
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Scan barcode or search product name / SKU (Press Enter)..."
                            style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.4rem; font-size: 0.95rem; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); background: var(--bg-page); color: var(--text-primary); outline: none;"
                            @keydown.enter.prevent="handleBarcodeInput"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;"
                            @click="searchQuery = ''; searchInputRef?.focus()"
                        >
                            <X :size="16" />
                        </button>
                    </div>

                    <!-- Category Clear Pill if active -->
                    <button 
                        v-if="selectedCategoryId" 
                        class="btn btn-secondary btn-sm"
                        style="font-size: 0.8rem;"
                        @click="selectedCategoryId = null"
                    >
                        Clear Filter
                    </button>
                </div>

                <!-- Category Navigation Pills -->
                <div style="padding: 0.5rem var(--space-4); border-bottom: 1px solid var(--border-subtle); display: flex; gap: 0.5rem; overflow-x: auto; white-space: nowrap; background: var(--bg-page); flex-shrink: 0;">
                    <button
                        type="button"
                        :style="{
                            padding: '0.35rem 0.85rem',
                            fontSize: '0.8rem',
                            fontWeight: '600',
                            borderRadius: 'var(--radius-full)',
                            border: '1px solid var(--border-subtle)',
                            cursor: 'pointer',
                            background: selectedCategoryId === null ? 'var(--primary)' : 'var(--bg-surface)',
                            color: selectedCategoryId === null ? '#ffffff' : 'var(--text-secondary)'
                        }"
                        @click="selectedCategoryId = null"
                    >
                        All Items ({{ products.length }})
                    </button>

                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        :style="{
                            padding: '0.35rem 0.85rem',
                            fontSize: '0.8rem',
                            fontWeight: '500',
                            borderRadius: 'var(--radius-full)',
                            border: '1px solid var(--border-subtle)',
                            cursor: 'pointer',
                            background: selectedCategoryId === cat.id ? 'var(--primary)' : 'var(--bg-surface)',
                            color: selectedCategoryId === cat.id ? '#ffffff' : 'var(--text-secondary)'
                        }"
                        @click="selectedCategoryId = cat.id"
                    >
                        {{ cat.name }}
                    </button>
                </div>

                <!-- Product Grid (Scrollable) -->
                <div style="flex: 1; overflow-y: auto; padding: var(--space-4);">
                    <div 
                        v-if="filteredProducts.length > 0"
                        style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: var(--space-3);"
                    >
                        <div
                            v-for="product in filteredProducts"
                            :key="product.id"
                            class="product-card"
                            style="background: var(--bg-page); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.75rem; display: flex; flex-direction: column; cursor: pointer; transition: all 0.15s ease; position: relative; user-select: none;"
                            @click="addToCart(product)"
                        >
                            <!-- Square Thumbnail (48px or 120px) -->
                            <div style="width: 100%; aspect-ratio: 1/1; border-radius: var(--radius-sm); overflow: hidden; background: var(--bg-surface); display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; border: 1px solid var(--border-subtle);">
                                <img
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    :alt="product.name"
                                    loading="lazy"
                                    style="width: 100%; height: 100%; object-fit: cover;"
                                />
                                <Layers v-else :size="28" style="color: var(--text-muted); opacity: 0.5;" />
                            </div>

                            <!-- Product Info -->
                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary); line-height: 1.25; margin-bottom: 0.25rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ product.name }}
                            </div>

                            <div style="font-size: 0.7rem; color: var(--text-muted); font-family: monospace; margin-bottom: auto;">
                                {{ product.sku }}
                            </div>

                            <!-- Price & Variant Badge -->
                            <div class="flex items-center justify-between" style="margin-top: 0.5rem; padding-top: 0.4rem; border-top: 1px dashed var(--border-subtle);">
                                <span style="font-weight: 700; font-size: 0.95rem; color: var(--primary);">
                                    {{ settings.currency_symbol }}{{ formatMoney(product.selling_price) }}
                                </span>

                                <span v-if="product.has_variants" style="font-size: 0.65rem; background: rgba(99, 102, 241, 0.12); color: #6366f1; padding: 0.1rem 0.35rem; border-radius: var(--radius-sm); font-weight: 600;">
                                    Variants
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty Results -->
                    <div v-else style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); text-align: center; padding: var(--space-8);">
                        <Search :size="36" style="margin-bottom: 0.75rem; opacity: 0.4;" />
                        <div style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">No products found</div>
                        <p style="font-size: 0.85rem; margin-top: 0.25rem;">Try adjusting your search query or clear the active category filter.</p>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Transaction Cart & Checkout (40%) -->
            <div class="pos-cart-panel" style="flex: 1; min-width: 380px; max-width: 520px; display: flex; flex-direction: column; background: var(--bg-page); overflow: hidden;">
                <!-- Cart Header -->
                <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; background: var(--bg-surface);">
                    <div class="flex items-center gap-2">
                        <ShoppingCart :size="18" style="color: var(--primary);" />
                        <span style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">Current Cart</span>
                        <span style="font-size: 0.75rem; background: var(--bg-subtle); padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-weight: 600; color: var(--text-secondary);">
                            {{ cart.length }} items
                        </span>
                    </div>

                    <button 
                        v-if="cart.length > 0"
                        type="button" 
                        style="background: none; border: none; font-size: 0.75rem; color: #dc2626; cursor: pointer; display: flex; align-items: center; gap: 0.2rem;"
                        @click="clearCart"
                    >
                        <Trash2 :size="14" />
                        <span>Clear</span>
                    </button>
                </div>

                <!-- Cart Items List (Scrollable) -->
                <div style="flex: 1; overflow-y: auto; padding: var(--space-3);">
                    <div v-if="cart.length > 0" style="display: flex; flex-direction: column; gap: var(--space-2);">
                        <div
                            v-for="(item, idx) in cart"
                            :key="item.id"
                            style="padding: 0.6rem 0.75rem; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); display: flex; flex-direction: column; gap: 0.4rem;"
                        >
                            <!-- Top row: Name, price, delete -->
                            <div class="flex items-center justify-between">
                                <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary); line-height: 1.2;">
                                    {{ item.name }}
                                </div>
                                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-primary);">
                                    {{ settings.currency_symbol }}{{ formatMoney((item.unit_price * item.quantity) - item.discount_amount) }}
                                </div>
                            </div>

                            <!-- Bottom row: Quantity stepper & remove -->
                            <div class="flex items-center justify-between" style="font-size: 0.75rem; color: var(--text-muted);">
                                <div>
                                    {{ settings.currency_symbol }}{{ formatMoney(item.unit_price) }} &times; {{ item.quantity }}
                                </div>

                                <div class="flex items-center gap-2">
                                    <!-- Stepper -->
                                    <div class="flex items-center" style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden; background: var(--bg-page);">
                                        <button
                                            type="button"
                                            style="padding: 0.2rem 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-secondary);"
                                            @click="updateQuantity(idx, -1)"
                                        >
                                            <Minus :size="12" />
                                        </button>
                                        <span style="padding: 0.2rem 0.4rem; font-weight: 600; font-size: 0.8rem; color: var(--text-primary);">
                                            {{ item.quantity }}
                                        </span>
                                        <button
                                            type="button"
                                            style="padding: 0.2rem 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-secondary);"
                                            @click="updateQuantity(idx, 1)"
                                        >
                                            <Plus :size="12" />
                                        </button>
                                    </div>

                                    <!-- Remove item -->
                                    <button
                                        type="button"
                                        style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.2rem;"
                                        title="Remove item"
                                        @click="removeFromCart(idx)"
                                    >
                                        <X :size="14" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Empty State -->
                    <div v-else style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); text-align: center; padding: 2rem;">
                        <ShoppingCart :size="40" style="margin-bottom: 0.75rem; opacity: 0.3;" />
                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-secondary);">Cart is empty</div>
                        <p style="font-size: 0.8rem; margin-top: 0.25rem;">Scan an item barcode or click a product from the catalog on the left to begin.</p>
                    </div>
                </div>

                <!-- Financial Calculation & Checkout Footer -->
                <div style="border-top: 1px solid var(--border-subtle); background: var(--bg-surface); padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3); flex-shrink: 0;">
                    <!-- Line Summary -->
                    <div style="display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.85rem;">
                        <div class="flex items-center justify-between" style="color: var(--text-secondary);">
                            <span>Subtotal</span>
                            <span>{{ settings.currency_symbol }}{{ formatMoney(cartSubtotal) }}</span>
                        </div>

                        <div v-if="settings.tax_enabled" class="flex items-center justify-between" style="color: var(--text-secondary);">
                            <span>Estimated Tax {{ settings.prices_include_tax ? '(Included)' : '' }}</span>
                            <span>{{ settings.currency_symbol }}{{ formatMoney(cartTaxTotal) }}</span>
                        </div>

                        <div v-if="orderDiscount > 0" class="flex items-center justify-between" style="color: #059669; font-weight: 500;">
                            <span>Discount</span>
                            <span>-{{ settings.currency_symbol }}{{ formatMoney(orderDiscount) }}</span>
                        </div>

                        <div class="flex items-center justify-between" style="border-top: 1px solid var(--border-subtle); padding-top: 0.5rem; margin-top: 0.2rem;">
                            <span style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">Total</span>
                            <span style="font-size: 1.3rem; font-weight: 800; color: var(--primary);">
                                {{ settings.currency_symbol }}{{ formatMoney(cartGrandTotal) }}
                            </span>
                        </div>
                    </div>

                    <!-- Charge Button -->
                    <button
                        type="button"
                        :disabled="cart.length === 0"
                        class="btn btn-primary"
                        style="width: 100%; padding: 0.85rem; font-size: 1.05rem; font-weight: 700; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
                        @click="openPaymentModal"
                    >
                        <span>Charge {{ settings.currency_symbol }}{{ formatMoney(cartGrandTotal) }}</span>
                        <ArrowRight :size="18" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Variant Selection Modal -->
        <BaseModal
            :show="variantModalOpen"
            :title="`Select Option: ${activeProductForVariants?.name}`"
            @close="variantModalOpen = false"
        >
            <div style="padding: var(--space-4);">
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: var(--space-4);">
                    Choose variant option to add to cart:
                </p>

                <div style="display: flex; flex-direction: column; gap: var(--space-2); margin-bottom: var(--space-4);">
                    <button
                        v-for="variant in activeProductForVariants?.variants"
                        :key="variant.id"
                        type="button"
                        style="padding: 0.75rem; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); background: var(--bg-surface); text-align: left; display: flex; justify-content: space-between; align-items: center; cursor: pointer;"
                        @click="addToCart(activeProductForVariants!, variant)"
                    >
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary);">{{ variant.name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">{{ variant.sku }}</div>
                        </div>
                        <span style="font-weight: 700; color: var(--primary);">
                            {{ settings.currency_symbol }}{{ formatMoney(variant.selling_price) }}
                        </span>
                    </button>
                </div>

                <div class="flex justify-end">
                    <BaseButton variant="secondary" size="md" @click="variantModalOpen = false">Cancel</BaseButton>
                </div>
            </div>
        </BaseModal>

        <!-- Payment & Tender Modal -->
        <BaseModal
            :show="paymentModalOpen"
            title="Complete Payment &amp; Checkout"
            @close="paymentModalOpen = false"
        >
            <div style="padding: var(--space-4);">
                <!-- Prominent Amount Due Banner -->
                <div style="background: var(--bg-subtle); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: var(--space-4); text-align: center; margin-bottom: var(--space-4);">
                    <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                        Total Amount Due
                    </div>
                    <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.2rem;">
                        {{ settings.currency_symbol }}{{ formatMoney(cartGrandTotal) }}
                    </div>
                </div>

                <!-- Payment Method Selector Tabs -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-2); margin-bottom: var(--space-4);">
                    <button
                        type="button"
                        :style="{
                            padding: '0.65rem 0.5rem',
                            border: '1px solid var(--border-subtle)',
                            borderRadius: 'var(--radius-md)',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: '0.3rem',
                            cursor: 'pointer',
                            background: selectedPaymentMethod === 'cash' ? 'var(--primary)' : 'var(--bg-surface)',
                            color: selectedPaymentMethod === 'cash' ? '#ffffff' : 'var(--text-primary)',
                            fontWeight: '600',
                            fontSize: '0.85rem'
                        }"
                        @click="selectedPaymentMethod = 'cash'"
                    >
                        <DollarSign :size="18" />
                        <span>Cash</span>
                    </button>

                    <button
                        type="button"
                        :style="{
                            padding: '0.65rem 0.5rem',
                            border: '1px solid var(--border-subtle)',
                            borderRadius: 'var(--radius-md)',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: '0.3rem',
                            cursor: 'pointer',
                            background: selectedPaymentMethod === 'card' ? 'var(--primary)' : 'var(--bg-surface)',
                            color: selectedPaymentMethod === 'card' ? '#ffffff' : 'var(--text-primary)',
                            fontWeight: '600',
                            fontSize: '0.85rem'
                        }"
                        @click="selectedPaymentMethod = 'card'"
                    >
                        <CreditCard :size="18" />
                        <span>Credit / Debit</span>
                    </button>

                    <button
                        type="button"
                        :style="{
                            padding: '0.65rem 0.5rem',
                            border: '1px solid var(--border-subtle)',
                            borderRadius: 'var(--radius-md)',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: '0.3rem',
                            cursor: 'pointer',
                            background: selectedPaymentMethod === 'bank_transfer' ? 'var(--primary)' : 'var(--bg-surface)',
                            color: selectedPaymentMethod === 'bank_transfer' ? '#ffffff' : 'var(--text-primary)',
                            fontWeight: '600',
                            fontSize: '0.85rem'
                        }"
                        @click="selectedPaymentMethod = 'bank_transfer'"
                    >
                        <Building2 :size="18" />
                        <span>Transfer / Other</span>
                    </button>
                </div>

                <!-- Cash Tender Flow -->
                <div v-if="selectedPaymentMethod === 'cash'" style="display: flex; flex-direction: column; gap: var(--space-3); margin-bottom: var(--space-4);">
                    <!-- Tendered Input -->
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                            Cash Received ($)
                        </label>
                        <input
                            v-model="tenderedCash"
                            type="number"
                            step="0.01"
                            style="width: 100%; padding: 0.65rem 0.75rem; font-size: 1.1rem; font-weight: 700; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); background: var(--bg-page); color: var(--text-primary); outline: none;"
                        />
                    </div>

                    <!-- Quick Cash Tender Shortcuts -->
                    <div class="flex items-center gap-2" style="flex-wrap: wrap;">
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            @click="setExactCash"
                        >
                            Exact (${{ formatMoney(cartGrandTotal) }})
                        </button>
                        <button
                            v-for="amt in [10, 20, 50, 100, 200].filter(a => a >= cartGrandTotal)"
                            :key="amt"
                            type="button"
                            class="btn btn-secondary btn-sm"
                            @click="setQuickCash(amt)"
                        >
                            ${{ amt }}
                        </button>
                    </div>

                    <!-- Change Due Display -->
                    <div 
                        style="padding: 0.75rem; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between;"
                        :style="{
                            background: Number(tenderedCash) >= cartGrandTotal ? 'rgba(5, 150, 105, 0.12)' : 'rgba(220, 38, 38, 0.1)',
                            border: Number(tenderedCash) >= cartGrandTotal ? '1px solid rgba(5, 150, 105, 0.3)' : '1px solid rgba(220, 38, 38, 0.3)',
                        }"
                    >
                        <span style="font-weight: 600; font-size: 0.9rem;" :style="{ color: Number(tenderedCash) >= cartGrandTotal ? '#059669' : '#dc2626' }">
                            {{ Number(tenderedCash) >= cartGrandTotal ? 'Change Returned:' : 'Insufficient Cash Received:' }}
                        </span>
                        <span style="font-weight: 800; font-size: 1.15rem;" :style="{ color: Number(tenderedCash) >= cartGrandTotal ? '#059669' : '#dc2626' }">
                            {{ settings.currency_symbol }}{{ formatMoney(cashChange) }}
                        </span>
                    </div>
                </div>

                <!-- Card / External Flow -->
                <div v-else style="display: flex; flex-direction: column; gap: var(--space-3); margin-bottom: var(--space-4);">
                    <div style="padding: 0.75rem; background: var(--bg-subtle); border-radius: var(--radius-md); font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4;">
                        Record external card/terminal payment. Please ensure customer completes transaction on physical hardware before finalizing.
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem;">
                            Transaction Reference / Auth Code (Optional)
                        </label>
                        <input
                            v-model="cardReference"
                            type="text"
                            placeholder="e.g. Auth #882941 or Card Last 4"
                            style="width: 100%; padding: 0.6rem 0.75rem; font-size: 0.85rem; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); background: var(--bg-page); color: var(--text-primary); outline: none;"
                        />
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3" style="border-top: 1px solid var(--border-subtle); padding-top: var(--space-4);">
                    <BaseButton variant="secondary" size="md" @click="paymentModalOpen = false">
                        Cancel
                    </BaseButton>
                    <BaseButton
                        variant="primary"
                        size="md"
                        :loading="isProcessingCheckout"
                        @click="submitCheckout"
                    >
                        <span>Confirm &amp; Finalize Sale</span>
                    </BaseButton>
                </div>
            </div>
        </BaseModal>

        <!-- Transaction Completed Receipt Modal -->
        <BaseModal
            :show="receiptModalOpen"
            title="Receipt &amp; Transaction Confirmation"
            @close="startNewSale"
        >
            <div style="padding: var(--space-4); text-align: center;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(5, 150, 105, 0.15); color: #059669; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                    <CheckCircle2 :size="28" />
                </div>

                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-primary);">
                    Sale Completed!
                </h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
                    Sale Reference: <strong style="color: var(--text-primary); font-family: monospace;">{{ completedSale?.sale_number }}</strong>
                </p>

                <!-- Receipt Summary Box -->
                <div style="background: var(--bg-subtle); border: 1px dashed var(--border-subtle); border-radius: var(--radius-md); padding: 1rem; margin: 1rem 0; text-align: left; font-size: 0.85rem; line-height: 1.5;">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Subtotal:</span>
                        <strong style="color: var(--text-primary);">${{ formatMoney(completedSale?.subtotal || 0) }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Tax Total:</span>
                        <strong style="color: var(--text-primary);">${{ formatMoney(completedSale?.tax_total || 0) }}</strong>
                    </div>
                    <div v-if="(completedSale?.discount_total || 0) > 0" class="flex justify-between" style="color: #059669;">
                        <span>Discount:</span>
                        <strong>-${{ formatMoney(completedSale?.discount_total || 0) }}</strong>
                    </div>
                    <div class="flex justify-between" style="border-top: 1px solid var(--border-subtle); margin-top: 0.5rem; padding-top: 0.5rem; font-size: 1rem;">
                        <strong>Grand Total:</strong>
                        <strong style="color: var(--primary);">${{ formatMoney(completedSale?.total_amount || 0) }}</strong>
                    </div>

                    <div v-if="completedSale?.payments && completedSale.payments.length > 0" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border-subtle);">
                        <div v-for="(p, i) in completedSale.payments" :key="i" class="flex justify-between" style="font-size: 0.8rem; color: var(--text-muted);">
                            <span>Paid via {{ p.payment_method.toUpperCase() }}:</span>
                            <span>${{ formatMoney(p.amount) }}</span>
                        </div>
                        <div v-if="completedSale.payments[0].change_amount > 0" class="flex justify-between" style="font-weight: 600; color: #059669; font-size: 0.85rem; margin-top: 0.2rem;">
                            <span>Change Returned:</span>
                            <span>${{ formatMoney(completedSale.payments[0].change_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3">
                    <BaseButton variant="secondary" size="md" @click="printReceipt">
                        <Printer :size="16" style="margin-right: 0.3rem;" />
                        <span>Print Receipt</span>
                    </BaseButton>

                    <BaseButton variant="primary" size="md" @click="startNewSale">
                        <RefreshCw :size="16" style="margin-right: 0.3rem;" />
                        <span>New Sale</span>
                    </BaseButton>
                </div>
            </div>
        </BaseModal>
    </PosLayout>
</template>

<style scoped>
.product-card:hover {
    border-color: var(--primary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
</style>
