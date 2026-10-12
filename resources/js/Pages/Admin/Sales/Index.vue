<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { usePermission } from '@/Composables/usePermission';
import {
    ReceiptText,
    Search,
    Filter,
    X,
    Eye,
    Printer,
    Ban,
    Store,
    Calendar,
    User as UserIcon,
    DollarSign,
    ChevronLeft,
    ChevronRight,
    AlertCircle,
    CheckCircle2,
    ShoppingCart,
    Clock,
    FileText,
    CreditCard
} from 'lucide-vue-next';

interface BranchOption {
    id: number;
    name: string;
    code: string;
}

interface CashierOption {
    id: number;
    name: string;
    email: string;
}

interface SaleListItem {
    id: number;
    uuid: string;
    sale_number: string;
    branch: {
        id: number;
        name: string;
        code: string;
    };
    cashier: {
        id: number;
        name: string;
    };
    customer_name: string | null;
    customer_phone: string | null;
    status: string;
    subtotal: number;
    discount_total: number;
    tax_total: number;
    total_amount: number;
    payment_status: string;
    payment_methods: string[];
    items_count: number;
    completed_at: string | null;
    completed_at_formatted: string;
    cancelled_at: string | null;
    cancelled_at_formatted: string | null;
    cancelled_by_name: string | null;
    cancellation_reason: string | null;
    can_cancel: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSales {
    data: SaleListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface SaleItemDetail {
    id: number;
    uuid: string;
    product_id: number | null;
    product_name: string;
    sku: string;
    barcode: string | null;
    unit: string;
    quantity: number;
    unit_price: number;
    discount_amount: number;
    is_taxable: boolean;
    tax_rate: number;
    tax_amount: number;
    subtotal: number;
    total: number;
    notes: string | null;
}

interface SalePaymentDetail {
    id: number;
    uuid: string;
    payment_method: string;
    amount: number;
    tendered_amount: number | null;
    change_amount: number;
    reference_note: string | null;
    status: string;
    created_at: string;
}

interface FullSaleDetail {
    id: number;
    uuid: string;
    sale_number: string;
    status: string;
    payment_status: string;
    customer_name: string | null;
    customer_phone: string | null;
    notes: string | null;
    subtotal: number;
    discount_total: number;
    tax_total: number;
    total_amount: number;
    completed_at: string | null;
    completed_at_formatted: string;
    cancelled_at: string | null;
    cancelled_at_formatted: string | null;
    cancellation_reason: string | null;
    canceller: {
        id: number;
        name: string;
        email: string;
    } | null;
    can_cancel: boolean;
    branch: {
        id: number;
        name: string;
        code: string;
        address: string | null;
        phone: string | null;
        email: string | null;
    };
    cashier: {
        id: number;
        name: string;
        email: string;
    };
    items: SaleItemDetail[];
    payments: SalePaymentDetail[];
}

interface Props {
    sales: PaginatedSales;
    filters: {
        search: string;
        branch_id: string | number;
        cashier_id: string | number;
        payment_method: string;
        status: string;
        date_from: string;
        date_to: string;
    };
    branches: BranchOption[];
    cashiers: CashierOption[];
    paymentMethods: string[];
    canCancelAny: boolean;
    business: {
        name: string;
        currency_symbol: string;
        currency_code: string;
        currency_precision: number;
    };
}

const props = defineProps<Props>();
const { can } = usePermission();

// Filter States
const searchTerm = ref(props.filters.search);
const selectedBranch = ref(props.filters.branch_id ? String(props.filters.branch_id) : '');
const selectedCashier = ref(props.filters.cashier_id ? String(props.filters.cashier_id) : '');
const selectedPaymentMethod = ref(props.filters.payment_method || '');
const selectedStatus = ref(props.filters.status || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const hasActiveFilters = computed(() => {
    return !!(searchTerm.value || selectedBranch.value || selectedCashier.value || 
              selectedPaymentMethod.value || (selectedStatus.value && selectedStatus.value !== 'all') || 
              dateFrom.value || dateTo.value);
});

const applyFilters = () => {
    router.get('/admin/sales', {
        search: searchTerm.value || undefined,
        branch_id: selectedBranch.value || undefined,
        cashier_id: selectedCashier.value || undefined,
        payment_method: selectedPaymentMethod.value || undefined,
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchTerm.value = '';
    selectedBranch.value = '';
    selectedCashier.value = '';
    selectedPaymentMethod.value = '';
    selectedStatus.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';

    router.get('/admin/sales', {}, {
        preserveState: true,
        replace: true,
    });
};

// Currency Formatter
const formatCurrency = (val: number | null | undefined) => {
    if (val === null || val === undefined) return `${props.business.currency_symbol}0.00`;
    return `${props.business.currency_symbol}${Number(val).toFixed(props.business.currency_precision)}`;
};

// Sale Detail Modal State
const detailModalOpen = ref(false);
const selectedSaleDetail = ref<FullSaleDetail | null>(null);
const loadingDetail = ref(false);

const openDetailModal = async (sale: SaleListItem) => {
    loadingDetail.value = true;
    detailModalOpen.value = true;
    try {
        const res = await fetch(`/admin/sales/${sale.id}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (res.ok) {
            selectedSaleDetail.value = await res.json();
        } else {
            selectedSaleDetail.value = null;
        }
    } catch (e) {
        selectedSaleDetail.value = null;
    } finally {
        loadingDetail.value = false;
    }
};

// Printable Thermal Receipt Modal State
const receiptModalOpen = ref(false);
const receiptData = ref<any | null>(null);
const loadingReceipt = ref(false);

const openReceiptModal = async (saleId: number) => {
    loadingReceipt.value = true;
    receiptModalOpen.value = true;
    try {
        const res = await fetch(`/admin/sales/${saleId}/receipt`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (res.ok) {
            receiptData.value = await res.json();
        } else {
            receiptData.value = null;
        }
    } catch (e) {
        receiptData.value = null;
    } finally {
        loadingReceipt.value = false;
    }
};

const printReceiptDirect = (saleId: number) => {
    const printWindow = window.open(`/admin/sales/${saleId}/receipt?autoprint=1`, '_blank', 'width=420,height=600');
    if (printWindow) {
        printWindow.focus();
    }
};

const triggerReceiptCardPrint = () => {
    window.print();
};

// Cancellation Modal State
const cancelModalOpen = ref(false);
const saleToCancel = ref<SaleListItem | FullSaleDetail | null>(null);
const cancellationReason = ref('');
const cancellationError = ref('');
const isCancelling = ref(false);

const openCancelModal = (sale: SaleListItem | FullSaleDetail) => {
    saleToCancel.value = sale;
    cancellationReason.value = '';
    cancellationError.value = '';
    cancelModalOpen.value = true;
};

const confirmCancellation = () => {
    if (!saleToCancel.value) return;

    if (!cancellationReason.value || cancellationReason.value.trim().length < 3) {
        cancellationError.value = 'Please provide a clear reason for cancellation (at least 3 characters).';
        return;
    }

    isCancelling.value = true;
    cancellationError.value = '';

    router.post(`/admin/sales/${saleToCancel.value.id}/cancel`, {
        reason: cancellationReason.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cancelModalOpen.value = false;
            // If detail modal was open for this sale, update its state or close
            if (detailModalOpen.value && selectedSaleDetail.value && selectedSaleDetail.value.id === saleToCancel.value?.id) {
                selectedSaleDetail.value.status = 'cancelled';
                selectedSaleDetail.value.cancellation_reason = cancellationReason.value.trim();
                selectedSaleDetail.value.can_cancel = false;
            }
            saleToCancel.value = null;
            cancellationReason.value = '';
        },
        onError: (errors) => {
            cancellationError.value = errors.reason || 'Failed to cancel sale order. Please try again.';
        },
        onFinish: () => {
            isCancelling.value = false;
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Sales & Receipts - StoreFleet" />

        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                        <ReceiptText class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                        Sales & Receipts
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View immutable transaction history, print thermal receipts, and audit cancellations across authorized branches.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="can('pos.terminal.access')"
                        href="/pos"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 active:bg-primary-800 rounded-lg shadow-sm transition-colors"
                    >
                        <ShoppingCart class="w-4 h-4" />
                        Launch POS Terminal
                    </Link>
                </div>
            </div>

            <!-- Search & Multi-Parameter Filter Card -->
            <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Text Search -->
                    <div class="relative">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Search Identifier</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                            <input
                                v-model="searchTerm"
                                type="text"
                                placeholder="Sale #, customer, SKU..."
                                class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-slate-900 dark:text-white placeholder-slate-400"
                                @keydown.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- Branch Selector -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Branch</label>
                        <select
                            v-model="selectedBranch"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Authorized Branches</option>
                            <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">
                                {{ branch.name }} ({{ branch.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Cashier Selector -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cashier</label>
                        <select
                            v-model="selectedCashier"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Cashiers</option>
                            <option v-for="c in cashiers" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Payment Method</label>
                        <select
                            v-model="selectedPaymentMethod"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="">All Methods</option>
                            <option v-for="pm in paymentMethods" :key="pm" :value="pm">
                                {{ pm.toUpperCase().replace('_', ' ') }}
                            </option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                        <select
                            v-model="selectedStatus"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        >
                            <option value="all">All Statuses</option>
                            <option value="completed">Completed Only</option>
                            <option value="cancelled">Cancelled Only</option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">From Date</label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">To Date</label>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-end gap-2">
                        <BaseButton
                            variant="primary"
                            class="flex-1 justify-center py-2 text-sm"
                            @click="applyFilters"
                        >
                            <Filter class="w-4 h-4 mr-1.5" />
                            Filter
                        </BaseButton>

                        <BaseButton
                            v-if="hasActiveFilters"
                            variant="secondary"
                            class="justify-center py-2 text-sm"
                            @click="resetFilters"
                        >
                            <X class="w-4 h-4" />
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- Sales Transactions Table -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 overflow-hidden">
                <div v-if="sales.data.length === 0" class="p-8">
                    <EmptyState
                        title="No sales transactions found"
                        :description="hasActiveFilters ? 'No transactions match your search and filter criteria. Try clearing filters.' : 'Completed POS orders will appear here in chronological order.'"
                    >
                        <template #icon>
                            <ReceiptText class="w-12 h-12 text-slate-400" />
                        </template>
                        <template #actions>
                            <BaseButton v-if="hasActiveFilters" variant="secondary" @click="resetFilters">
                                Clear Filters
                            </BaseButton>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
                                <th class="py-3 px-4">Sale #</th>
                                <th class="py-3 px-4">Date & Time</th>
                                <th class="py-3 px-4">Branch</th>
                                <th class="py-3 px-4">Cashier</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4 text-center">Items</th>
                                <th class="py-3 px-4">Payment</th>
                                <th class="py-3 px-4 text-right">Total</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr
                                v-for="sale in sales.data"
                                :key="sale.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
                                :class="{ 'opacity-75 bg-rose-50/20 dark:bg-rose-950/10': sale.status === 'cancelled' }"
                            >
                                <!-- Sale # -->
                                <td class="py-3.5 px-4 font-mono font-medium text-slate-900 dark:text-white">
                                    <button
                                        class="hover:text-primary-600 dark:hover:text-primary-400 underline decoration-dotted text-left"
                                        @click="openDetailModal(sale)"
                                    >
                                        {{ sale.sale_number }}
                                    </button>
                                </td>

                                <!-- Date & Time -->
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                    {{ sale.completed_at_formatted }}
                                </td>

                                <!-- Branch -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">
                                        <Store class="w-3 h-3 text-slate-400" />
                                        {{ sale.branch.name }}
                                    </span>
                                </td>

                                <!-- Cashier -->
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs">
                                    <span class="flex items-center gap-1.5">
                                        <UserIcon class="w-3 h-3 text-slate-400" />
                                        {{ sale.cashier.name }}
                                    </span>
                                </td>

                                <!-- Customer -->
                                <td class="py-3.5 px-4 text-xs">
                                    <div v-if="sale.customer_name" class="font-medium text-slate-900 dark:text-white">
                                        {{ sale.customer_name }}
                                    </div>
                                    <div v-else class="text-slate-400 italic">
                                        Walk-in Customer
                                    </div>
                                    <div v-if="sale.customer_phone" class="text-[11px] text-slate-400">
                                        {{ sale.customer_phone }}
                                    </div>
                                </td>

                                <!-- Items Count -->
                                <td class="py-3.5 px-4 text-center font-medium">
                                    {{ sale.items_count }}
                                </td>

                                <!-- Payment Method -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="pm in sale.payment_methods"
                                            :key="pm"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold uppercase bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            {{ pm.replace('_', ' ') }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Total Amount -->
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    {{ formatCurrency(sale.total_amount) }}
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <BaseBadge
                                        v-if="sale.status === 'completed'"
                                        variant="success"
                                        size="sm"
                                    >
                                        Completed
                                    </BaseBadge>
                                    <div v-else-if="sale.status === 'cancelled'" class="inline-flex flex-col items-center">
                                        <BaseBadge variant="danger" size="sm">
                                            Cancelled
                                        </BaseBadge>
                                        <span v-if="sale.cancelled_at_formatted" class="text-[10px] text-rose-500 mt-0.5 font-mono">
                                            {{ sale.cancelled_at_formatted }}
                                        </span>
                                    </div>
                                    <BaseBadge v-else variant="warning" size="sm">
                                        {{ sale.status }}
                                    </BaseBadge>
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- View Detail -->
                                        <button
                                            type="button"
                                            class="p-1.5 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                            title="View Snapshot Details"
                                            @click="openDetailModal(sale)"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>

                                        <!-- Print Thermal Receipt -->
                                        <button
                                            type="button"
                                            class="p-1.5 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                            title="Receipt Preview & Print"
                                            @click="openReceiptModal(sale.id)"
                                        >
                                            <Printer class="w-4 h-4" />
                                        </button>

                                        <!-- Authorized Cancellation -->
                                        <button
                                            v-if="sale.can_cancel"
                                            type="button"
                                            class="p-1.5 text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                            title="Cancel Sale Order"
                                            @click="openCancelModal(sale)"
                                        >
                                            <Ban class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="sales.total > 0"
                    class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/25"
                >
                    <div>
                        Showing <span class="font-semibold text-slate-700 dark:text-slate-300">{{ sales.from || 0 }}</span>
                        to <span class="font-semibold text-slate-700 dark:text-slate-300">{{ sales.to || 0 }}</span>
                        of <span class="font-semibold text-slate-700 dark:text-slate-300">{{ sales.total }}</span> sales
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in sales.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-2.5 py-1.5 rounded border text-xs font-medium transition-colors"
                                :class="[
                                    link.active
                                        ? 'bg-primary-600 text-white border-primary-600'
                                        : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1.5 text-xs text-slate-400 border border-transparent"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. FULL SALE DETAIL MODAL -->
        <BaseModal
            :show="detailModalOpen"
            title="Sale Transaction Snapshot"
            size="xl"
            @close="detailModalOpen = false"
        >
            <div v-if="loadingDetail" class="p-8 text-center text-slate-500">
                <Clock class="w-8 h-8 animate-spin mx-auto text-primary-500 mb-2" />
                Loading snapshot details...
            </div>

            <div v-else-if="selectedSaleDetail" class="space-y-6">
                <!-- Status Banner if Cancelled -->
                <div
                    v-if="selectedSaleDetail.status === 'cancelled'"
                    class="p-4 bg-rose-50 border border-rose-200 rounded-xl dark:bg-rose-950/40 dark:border-rose-900/60"
                >
                    <div class="flex items-start gap-3">
                        <AlertCircle class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                        <div class="text-sm">
                            <div class="font-bold text-rose-900 dark:text-rose-200">
                                This sale was CANCELLED on {{ selectedSaleDetail.cancelled_at_formatted }}
                            </div>
                            <div v-if="selectedSaleDetail.canceller" class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">
                                Authorizer: {{ selectedSaleDetail.canceller.name }} ({{ selectedSaleDetail.canceller.email }})
                            </div>
                            <div class="mt-2 text-xs text-rose-800 dark:text-rose-300 bg-white/60 dark:bg-rose-900/40 p-2.5 rounded-lg border border-rose-200 dark:border-rose-800">
                                <span class="font-semibold">Reason:</span> {{ selectedSaleDetail.cancellation_reason }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header Meta Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 dark:bg-slate-800/60 p-4 rounded-xl text-xs">
                    <div>
                        <div class="text-slate-400">Sale Number</div>
                        <div class="font-mono font-bold text-slate-900 dark:text-white text-sm mt-0.5">
                            {{ selectedSaleDetail.sale_number }}
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-400">Date & Time</div>
                        <div class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ selectedSaleDetail.completed_at_formatted }}
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-400">Branch</div>
                        <div class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ selectedSaleDetail.branch.name }} ({{ selectedSaleDetail.branch.code }})
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-400">Cashier</div>
                        <div class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ selectedSaleDetail.cashier.name }}
                        </div>
                    </div>
                </div>

                <!-- Customer & Notes if present -->
                <div v-if="selectedSaleDetail.customer_name || selectedSaleDetail.notes" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-slate-50 dark:bg-slate-800/40 p-3 rounded-lg">
                    <div v-if="selectedSaleDetail.customer_name">
                        <span class="text-slate-400 font-medium">Customer:</span>
                        <span class="ml-1 font-semibold text-slate-800 dark:text-slate-200">{{ selectedSaleDetail.customer_name }}</span>
                        <span v-if="selectedSaleDetail.customer_phone" class="text-slate-500 ml-1">({{ selectedSaleDetail.customer_phone }})</span>
                    </div>
                    <div v-if="selectedSaleDetail.notes">
                        <span class="text-slate-400 font-medium">Notes:</span>
                        <span class="ml-1 italic text-slate-700 dark:text-slate-300">{{ selectedSaleDetail.notes }}</span>
                    </div>
                </div>

                <!-- Line Item Snapshots -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                        Stored Line Item Snapshots
                    </h3>
                    <div class="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-100/75 dark:bg-slate-800/75 text-slate-600 dark:text-slate-300 font-semibold border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="py-2.5 px-3">Item Description</th>
                                    <th class="py-2.5 px-2 text-center">Qty</th>
                                    <th class="py-2.5 px-2 text-right">Unit Price</th>
                                    <th class="py-2.5 px-2 text-right">Discount</th>
                                    <th class="py-2.5 px-2 text-right">Tax</th>
                                    <th class="py-2.5 px-3 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <tr v-for="item in selectedSaleDetail.items" :key="item.id">
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ item.product_name }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono">SKU: {{ item.sku }}</div>
                                    </td>
                                    <td class="py-2.5 px-2 text-center font-medium">{{ item.quantity }}</td>
                                    <td class="py-2.5 px-2 text-right font-mono">{{ formatCurrency(item.unit_price) }}</td>
                                    <td class="py-2.5 px-2 text-right font-mono" :class="item.discount_amount > 0 ? 'text-rose-600' : 'text-slate-400'">
                                        {{ item.discount_amount > 0 ? `-${formatCurrency(item.discount_amount)}` : '-' }}
                                    </td>
                                    <td class="py-2.5 px-2 text-right font-mono text-[11px] text-slate-500">
                                        {{ item.tax_amount > 0 ? `${formatCurrency(item.tax_amount)} (${item.tax_rate}%)` : 'Exempt' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                        {{ formatCurrency(item.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totals Breakdown & Payment Records -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Payments -->
                    <div class="bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                        <div class="font-bold uppercase tracking-wider text-slate-500 mb-2">Payment Records</div>
                        <div class="space-y-2">
                            <div
                                v-for="pay in selectedSaleDetail.payments"
                                :key="pay.id"
                                class="flex items-center justify-between p-2 bg-white dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700"
                            >
                                <div>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 uppercase">{{ pay.payment_method.replace('_', ' ') }}</span>
                                    <div v-if="pay.reference_note" class="text-[11px] text-slate-400">Ref: {{ pay.reference_note }}</div>
                                    <div v-if="pay.change_amount > 0" class="text-[11px] text-emerald-600">
                                        Change Given: {{ formatCurrency(pay.change_amount) }}
                                    </div>
                                </div>
                                <div class="font-mono font-bold text-slate-900 dark:text-white">
                                    {{ formatCurrency(pay.amount) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs space-y-2">
                        <div class="font-bold uppercase tracking-wider text-slate-500 mb-2">Summary Totals</div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Gross Subtotal</span>
                            <span class="font-mono">{{ formatCurrency(selectedSaleDetail.subtotal) }}</span>
                        </div>
                        <div v-if="selectedSaleDetail.discount_total > 0" class="flex justify-between text-rose-600">
                            <span>Discounts Applied</span>
                            <span class="font-mono">-{{ formatCurrency(selectedSaleDetail.discount_total) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Tax Total</span>
                            <span class="font-mono">{{ formatCurrency(selectedSaleDetail.tax_total) }}</span>
                        </div>
                        <div class="border-t border-slate-200 dark:border-slate-700 pt-2 flex justify-between font-bold text-sm text-slate-900 dark:text-white">
                            <span>FINAL TOTAL</span>
                            <span class="font-mono text-base text-primary-600 dark:text-primary-400">
                                {{ formatCurrency(selectedSaleDetail.total_amount) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800">
                    <div>
                        <BaseButton
                            v-if="selectedSaleDetail.can_cancel"
                            variant="danger"
                            size="sm"
                            @click="openCancelModal(selectedSaleDetail)"
                        >
                            <Ban class="w-4 h-4 mr-1.5" />
                            Cancel Sale Order
                        </BaseButton>
                    </div>

                    <div class="flex items-center gap-2">
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            @click="openReceiptModal(selectedSaleDetail.id)"
                        >
                            <Printer class="w-4 h-4 mr-1.5" />
                            Print Thermal Receipt
                        </BaseButton>
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            @click="detailModalOpen = false"
                        >
                            Close
                        </BaseButton>
                    </div>
                </div>
            </div>
        </BaseModal>

        <!-- 2. PRINTABLE THERMAL RECEIPT MODAL -->
        <BaseModal
            :show="receiptModalOpen"
            title="Receipt Print Preview"
            size="md"
            @close="receiptModalOpen = false"
        >
            <div v-if="loadingReceipt" class="p-8 text-center text-slate-500">
                <Clock class="w-8 h-8 animate-spin mx-auto text-primary-500 mb-2" />
                Rendering receipt snapshot...
            </div>

            <div v-else-if="receiptData" class="space-y-4">
                <!-- Top Action Bar inside preview -->
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                    <span class="text-xs text-slate-500">Formatted for 80mm thermal POS rolls & browser print</span>
                    <BaseButton
                        variant="primary"
                        size="sm"
                        @click="printReceiptDirect(receiptData.sale.id)"
                    >
                        <Printer class="w-4 h-4 mr-1.5" />
                        Print Now
                    </BaseButton>
                </div>

                <!-- Printable Receipt Emulation Container -->
                <div class="bg-white text-black p-5 rounded-lg border-2 border-dashed border-slate-300 font-mono text-xs shadow-inner max-w-sm mx-auto">
                    <!-- Cancelled Stamp -->
                    <div
                        v-if="receiptData.sale.is_cancelled"
                        class="border-2 border-dashed border-red-600 bg-red-50 text-red-700 text-center p-2 mb-3 font-bold text-sm tracking-widest"
                    >
                        *** CANCELLED / VOIDED ***
                        <div class="text-[10px] font-normal mt-0.5">
                            Reason: {{ receiptData.sale.cancellation_reason }}
                        </div>
                    </div>

                    <!-- Business / Branch Header -->
                    <div class="text-center mb-3">
                        <div class="font-bold text-sm uppercase tracking-wide">{{ receiptData.business.name }}</div>
                        <div class="font-semibold">{{ receiptData.branch.name }} ({{ receiptData.branch.code }})</div>
                        <div v-if="receiptData.branch.address" class="text-[11px]">{{ receiptData.branch.address }}</div>
                        <div v-if="receiptData.branch.phone" class="text-[11px]">Tel: {{ receiptData.branch.phone }}</div>
                        <div v-if="receiptData.business.tax_id" class="text-[11px]">Tax ID: {{ receiptData.business.tax_id }}</div>
                    </div>

                    <div v-if="receiptData.header" class="text-center text-[11px] italic my-2">
                        {{ receiptData.header }}
                    </div>

                    <div class="border-t border-dashed border-black my-2"></div>

                    <!-- Meta -->
                    <div class="space-y-0.5 text-[11px]">
                        <div class="flex justify-between">
                            <span class="font-bold">Receipt #:</span>
                            <span class="font-bold">{{ receiptData.sale.sale_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Date:</span>
                            <span>{{ receiptData.sale.completed_at_formatted }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cashier:</span>
                            <span>{{ receiptData.sale.cashier_name }}</span>
                        </div>
                        <div v-if="receiptData.sale.customer_name" class="flex justify-between">
                            <span>Customer:</span>
                            <span>{{ receiptData.sale.customer_name }}</span>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-black my-2"></div>

                    <!-- Items -->
                    <div class="space-y-2">
                        <div
                            v-for="(it, idx) in receiptData.items"
                            :key="idx"
                            class="text-[11px]"
                        >
                            <div class="font-bold">{{ it.name }}</div>
                            <div class="flex justify-between text-slate-700">
                                <span>{{ it.quantity }} x {{ receiptData.currency.symbol }}{{ it.unit_price.toFixed(receiptData.currency.precision) }}</span>
                                <span class="font-bold">{{ receiptData.currency.symbol }}{{ it.total.toFixed(receiptData.currency.precision) }}</span>
                            </div>
                            <div v-if="it.discount > 0" class="text-red-600 text-[10px]">
                                Discount: -{{ receiptData.currency.symbol }}{{ it.discount.toFixed(receiptData.currency.precision) }}
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-black my-2"></div>

                    <!-- Totals -->
                    <div class="space-y-1 text-[11px]">
                        <div class="flex justify-between">
                            <span>Subtotal:</span>
                            <span>{{ receiptData.currency.symbol }}{{ receiptData.totals.subtotal.toFixed(receiptData.currency.precision) }}</span>
                        </div>
                        <div v-if="receiptData.totals.discount_total > 0" class="flex justify-between text-red-600">
                            <span>Discounts:</span>
                            <span>-{{ receiptData.currency.symbol }}{{ receiptData.totals.discount_total.toFixed(receiptData.currency.precision) }}</span>
                        </div>
                        <div v-if="receiptData.totals.tax_total > 0" class="flex justify-between">
                            <span>Tax:</span>
                            <span>{{ receiptData.currency.symbol }}{{ receiptData.totals.tax_total.toFixed(receiptData.currency.precision) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-xs pt-1 border-t border-black">
                            <span>TOTAL:</span>
                            <span>{{ receiptData.currency.symbol }}{{ receiptData.totals.total_amount.toFixed(receiptData.currency.precision) }}</span>
                        </div>
                    </div>

                    <div class="border-t-2 border-black my-2"></div>

                    <!-- Payments -->
                    <div class="space-y-1 text-[11px]">
                        <div class="font-bold uppercase text-[10px]">Payment Details:</div>
                        <div
                            v-for="(p, pidx) in receiptData.payments"
                            :key="pidx"
                            class="flex justify-between"
                        >
                            <span>{{ p.method }}</span>
                            <span>{{ receiptData.currency.symbol }}{{ p.amount.toFixed(receiptData.currency.precision) }}</span>
                        </div>
                        <div v-if="receiptData.totals.change_total > 0" class="flex justify-between font-bold">
                            <span>Change Due:</span>
                            <span>{{ receiptData.currency.symbol }}{{ receiptData.totals.change_total.toFixed(receiptData.currency.precision) }}</span>
                        </div>
                    </div>

                    <div v-if="receiptData.footer" class="border-t border-dashed border-black my-2"></div>
                    <div v-if="receiptData.footer" class="text-center text-[10px] whitespace-pre-line text-slate-700">
                        {{ receiptData.footer }}
                    </div>

                    <div class="text-center text-[9px] text-slate-500 mt-3">
                        StoreFleet POS System
                    </div>
                </div>
            </div>
        </BaseModal>

        <!-- 3. AUTHORIZED CANCELLATION MODAL -->
        <BaseModal
            :show="cancelModalOpen"
            title="Authorize Sale Cancellation"
            size="md"
            @close="cancelModalOpen = false"
        >
            <div v-if="saleToCancel" class="space-y-4">
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 dark:bg-amber-950/40 dark:border-amber-900/60 dark:text-amber-200">
                    <div class="font-bold flex items-center gap-1.5 mb-1">
                        <AlertCircle class="w-4 h-4 text-amber-600" />
                        Audit-Tracked Cancellation
                    </div>
                    Completed transactions cannot be silently deleted or rewritten. A formal cancellation status and audit trail record will be permanently attached.
                </div>

                <div class="bg-slate-50 dark:bg-slate-800/60 p-3 rounded-lg text-xs space-y-1 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Sale Number:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ saleToCancel.sale_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Total Amount:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(saleToCancel.total_amount) }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Reason for Cancellation <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        v-model="cancellationReason"
                        rows="3"
                        placeholder="State reason (e.g. customer change of mind, incorrect items rung, cashier keying error)..."
                        class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-rose-500 text-slate-900 dark:text-white placeholder-slate-400"
                    ></textarea>
                    <p v-if="cancellationError" class="text-xs text-rose-600 mt-1">
                        {{ cancellationError }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        :disabled="isCancelling"
                        @click="cancelModalOpen = false"
                    >
                        Keep Sale
                    </BaseButton>

                    <BaseButton
                        variant="danger"
                        size="sm"
                        :loading="isCancelling"
                        @click="confirmCancellation"
                    >
                        <Ban class="w-4 h-4 mr-1.5" />
                        Confirm Cancellation
                    </BaseButton>
                </div>
            </div>
        </BaseModal>
    </AppLayout>
</template>
