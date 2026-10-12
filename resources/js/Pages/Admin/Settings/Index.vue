<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseSelect from '@/Components/Forms/BaseSelect.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import { 
    Settings, 
    Building2, 
    Coins, 
    Clock, 
    Receipt, 
    Percent, 
    Palette, 
    CheckCircle2,
    Upload
} from 'lucide-vue-next';

interface Props {
    settings: {
        business_name: string;
        business_logo_path?: string | null;
        business_email?: string | null;
        business_phone?: string | null;
        address_line1?: string | null;
        address_line2?: string | null;
        city?: string | null;
        state?: string | null;
        postal_code?: string | null;
        country?: string | null;
        primary_color: string;
        currency_code: string;
        currency_symbol: string;
        currency_precision: number;
        currency_symbol_position: 'before' | 'after';
        decimal_separator: string;
        thousand_separator?: string | null;
        timezone: string;
        date_format: string;
        time_format: string;
        tax_enabled: boolean;
        default_tax_rate: number;
        tax_id_number?: string | null;
        prices_include_tax: boolean;
        receipt_header?: string | null;
        receipt_footer?: string | null;
        receipt_show_logo: boolean;
    };
    timezones: string[];
}

const props = defineProps<Props>();

const activeTab = ref<'branding' | 'address' | 'currency' | 'tax' | 'receipt'>('branding');

const form = useForm({
    business_name: props.settings.business_name || '',
    business_email: props.settings.business_email || '',
    business_phone: props.settings.business_phone || '',
    address_line1: props.settings.address_line1 || '',
    address_line2: props.settings.address_line2 || '',
    city: props.settings.city || '',
    state: props.settings.state || '',
    postal_code: props.settings.postal_code || '',
    country: props.settings.country || 'US',
    primary_color: props.settings.primary_color || '#2563eb',
    currency_code: props.settings.currency_code || 'USD',
    currency_symbol: props.settings.currency_symbol || '$',
    currency_precision: props.settings.currency_precision ?? 2,
    currency_symbol_position: props.settings.currency_symbol_position || 'before',
    decimal_separator: props.settings.decimal_separator || '.',
    thousand_separator: props.settings.thousand_separator || ',',
    timezone: props.settings.timezone || 'America/New_York',
    date_format: props.settings.date_format || 'Y-m-d',
    time_format: props.settings.time_format || 'H:i',
    tax_enabled: props.settings.tax_enabled ?? true,
    default_tax_rate: props.settings.default_tax_rate ?? 10.0,
    tax_id_number: props.settings.tax_id_number || '',
    prices_include_tax: props.settings.prices_include_tax ?? false,
    receipt_header: props.settings.receipt_header || '',
    receipt_footer: props.settings.receipt_footer || '',
    receipt_show_logo: props.settings.receipt_show_logo ?? true,
    logo: null as File | null,
});

const logoPreview = ref<string | null>(props.settings.business_logo_path || null);

const handleLogoUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.logo = target.files[0];
        logoPreview.value = URL.createObjectURL(target.files[0]);
    }
};

const currencyPositionOptions = [
    { value: 'before', label: 'Before amount (e.g. $100.00)' },
    { value: 'after', label: 'After amount (e.g. 100.00$)' },
];

const decimalOptions = [
    { value: '.', label: 'Period (. )' },
    { value: ',', label: 'Comma (, )' },
];

const thousandOptions = [
    { value: ',', label: 'Comma (, )' },
    { value: '.', label: 'Period (. )' },
    { value: ' ', label: 'Space ( )' },
    { value: '', label: 'None' },
];

const dateFormatOptions = [
    { value: 'Y-m-d', label: 'YYYY-MM-DD (2026-10-12)' },
    { value: 'd/m/Y', label: 'DD/MM/YYYY (12/10/2026)' },
    { value: 'm/d/Y', label: 'MM/DD/YYYY (10/12/2026)' },
    { value: 'd M Y', label: 'DD Mon YYYY (12 Oct 2026)' },
];

const timeFormatOptions = [
    { value: 'H:i', label: '24-hour (14:30)' },
    { value: 'h:i A', label: '12-hour AM/PM (02:30 PM)' },
];

const timezoneOptions = props.timezones.map(tz => ({
    value: tz,
    label: tz,
}));

const submit = () => {
    form.post('/admin/settings', {
        // Method spoofing to PUT with file upload support
        headers: {
            'X-HTTP-Method-Override': 'PUT',
        },
    });
};
</script>

<template>
    <AppLayout title="Business Settings - StoreFleet">
        <Head title="Business Settings - StoreFleet" />

        <div style="max-width: 1100px; margin: 0 auto;">
            <!-- Header -->
            <div style="margin-bottom: var(--space-6);">
                <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <Settings style="color: var(--primary);" :size="28" />
                    <span>Business Configuration &amp; Settings</span>
                </h1>
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                    Configure store branding, currency precision, tax rates, and thermal receipt information.
                </p>
            </div>

            <!-- Tab Navigation Bar -->
            <div class="flex items-center" style="gap: var(--space-2); margin-bottom: var(--space-6); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-2); overflow-x: auto;">
                <button
                    type="button"
                    :class="['btn btn-sm', activeTab === 'branding' ? 'btn-primary' : 'btn-ghost']"
                    @click="activeTab = 'branding'"
                >
                    <Palette :size="15" />
                    <span>Branding &amp; Contact</span>
                </button>
                <button
                    type="button"
                    :class="['btn btn-sm', activeTab === 'address' ? 'btn-primary' : 'btn-ghost']"
                    @click="activeTab = 'address'"
                >
                    <Building2 :size="15" />
                    <span>Store Address</span>
                </button>
                <button
                    type="button"
                    :class="['btn btn-sm', activeTab === 'currency' ? 'btn-primary' : 'btn-ghost']"
                    @click="activeTab = 'currency'"
                >
                    <Coins :size="15" />
                    <span>Currency &amp; Timezone</span>
                </button>
                <button
                    type="button"
                    :class="['btn btn-sm', activeTab === 'tax' ? 'btn-primary' : 'btn-ghost']"
                    @click="activeTab = 'tax'"
                >
                    <Percent :size="15" />
                    <span>Tax Configuration</span>
                </button>
                <button
                    type="button"
                    :class="['btn btn-sm', activeTab === 'receipt' ? 'btn-primary' : 'btn-ghost']"
                    @click="activeTab = 'receipt'"
                >
                    <Receipt :size="15" />
                    <span>Thermal Receipts</span>
                </button>
            </div>

            <form @submit.prevent="submit">
                <!-- 1. Branding & Identity Tab -->
                <div v-show="activeTab === 'branding'" class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Business Identity &amp; Branding
                    </h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                        <BaseInput
                            v-model="form.business_name"
                            label="Business / Merchant Name"
                            placeholder="e.g. Acme Fine Retail"
                            required
                            :error="form.errors.business_name"
                            hint="This name appears on the POS register and printed receipts."
                        />

                        <BaseInput
                            v-model="form.business_email"
                            label="Contact Email"
                            type="email"
                            placeholder="info@business.com"
                            :error="form.errors.business_email"
                        />

                        <BaseInput
                            v-model="form.business_phone"
                            label="Contact Telephone"
                            placeholder="+1 (555) 000-0000"
                            :error="form.errors.business_phone"
                        />

                        <!-- Primary Accent Color Picker -->
                        <div class="form-group">
                            <label class="form-label">Primary Brand Accent Color</label>
                            <div class="flex items-center" style="gap: var(--space-3);">
                                <input
                                    v-model="form.primary_color"
                                    type="color"
                                    style="width: 48px; height: 38px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); cursor: pointer; padding: 2px;"
                                />
                                <input
                                    v-model="form.primary_color"
                                    type="text"
                                    class="form-input mono"
                                    placeholder="#2563eb"
                                    style="max-width: 130px;"
                                />
                                <div style="width: 24px; height: 24px; border-radius: 50%;" :style="{ backgroundColor: form.primary_color }"></div>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">
                                Configures the global accent theme applied across all POS registers.
                            </span>
                        </div>
                    </div>

                    <!-- Logo Upload -->
                    <div style="margin-top: var(--space-6); border-top: 1px solid var(--border-subtle); padding-top: var(--space-5);">
                        <label class="form-label">Business Logo</label>
                        <div class="flex items-center" style="gap: var(--space-5); margin-top: var(--space-2);">
                            <div 
                                style="width: 80px; height: 80px; border-radius: var(--radius-md); border: 1px dashed var(--border-strong); display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: var(--bg-subtle);"
                            >
                                <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                                <Building2 v-else :size="32" style="color: var(--text-muted);" />
                            </div>

                            <div>
                                <label class="btn btn-secondary btn-sm" style="cursor: pointer; display: inline-flex;">
                                    <Upload :size="14" />
                                    <span>Upload New Logo</span>
                                    <input type="file" accept="image/*" class="sr-only" @change="handleLogoUpload" />
                                </label>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 4px;">
                                    PNG, JPG, SVG, or WebP. Max 2MB.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Address Tab -->
                <div v-show="activeTab === 'address'" class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Headquarters &amp; Business Address
                    </h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-4);">
                        <BaseInput
                            v-model="form.address_line1"
                            label="Address Line 1"
                            placeholder="100 Commercial Way"
                            :error="form.errors.address_line1"
                        />

                        <BaseInput
                            v-model="form.address_line2"
                            label="Address Line 2"
                            placeholder="Suite 200"
                            :error="form.errors.address_line2"
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
                            placeholder="California"
                            :error="form.errors.state"
                        />

                        <BaseInput
                            v-model="form.postal_code"
                            label="Postal / ZIP Code"
                            placeholder="94105"
                            :error="form.errors.postal_code"
                        />

                        <BaseInput
                            v-model="form.country"
                            label="Country"
                            placeholder="United States"
                            :error="form.errors.country"
                        />
                    </div>
                </div>

                <!-- 3. Currency & Timezone Tab -->
                <div v-show="activeTab === 'currency'" class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Currency, Formats &amp; Timezone
                    </h2>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-4);">
                        <BaseInput
                            v-model="form.currency_code"
                            label="Currency Code"
                            placeholder="USD"
                            required
                            maxlength="3"
                            class="mono"
                            :error="form.errors.currency_code"
                            hint="Standard 3-letter ISO code (e.g. USD, EUR, GBP)"
                        />

                        <BaseInput
                            v-model="form.currency_symbol"
                            label="Currency Symbol"
                            placeholder="$"
                            required
                            :error="form.errors.currency_symbol"
                        />

                        <BaseInput
                            v-model.number="form.currency_precision"
                            label="Decimal Precision"
                            type="number"
                            min="0"
                            max="4"
                            required
                            :error="form.errors.currency_precision"
                            hint="Number of decimal places (typically 2 for cents)"
                        />

                        <BaseSelect
                            v-model="form.currency_symbol_position"
                            label="Symbol Position"
                            :options="currencyPositionOptions"
                            required
                            :error="form.errors.currency_symbol_position"
                        />

                        <BaseSelect
                            v-model="form.decimal_separator"
                            label="Decimal Separator"
                            :options="decimalOptions"
                            required
                            :error="form.errors.decimal_separator"
                        />

                        <BaseSelect
                            v-model="form.thousand_separator"
                            label="Thousands Separator"
                            :options="thousandOptions"
                            :error="form.errors.thousand_separator"
                        />

                        <BaseSelect
                            v-model="form.timezone"
                            label="Business Timezone"
                            :options="timezoneOptions"
                            required
                            :error="form.errors.timezone"
                        />

                        <BaseSelect
                            v-model="form.date_format"
                            label="Date Format"
                            :options="dateFormatOptions"
                            required
                            :error="form.errors.date_format"
                        />

                        <BaseSelect
                            v-model="form.time_format"
                            label="Time Format"
                            :options="timeFormatOptions"
                            required
                            :error="form.errors.time_format"
                        />
                    </div>
                </div>

                <!-- 4. Tax Configuration Tab -->
                <div v-show="activeTab === 'tax'" class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        POS Tax &amp; Surcharge Configuration
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: var(--space-4); max-width: 600px;">
                        <BaseCheckbox
                            v-model="form.tax_enabled"
                            label="Enable tax calculations during checkout"
                        />

                        <div v-if="form.tax_enabled" style="display: flex; flex-direction: column; gap: var(--space-4); margin-top: var(--space-2);">
                            <BaseInput
                                v-model.number="form.default_tax_rate"
                                label="Default Tax Rate (%)"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                required
                                :error="form.errors.default_tax_rate"
                                hint="Percentage tax applied to taxable items (e.g. 10.0 for 10%)"
                            />

                            <BaseInput
                                v-model="form.tax_id_number"
                                label="Tax Registration / VAT ID"
                                placeholder="VAT-12345678"
                                :error="form.errors.tax_id_number"
                                hint="Printed on customer thermal receipts for regulatory compliance."
                            />

                            <BaseCheckbox
                                v-model="form.prices_include_tax"
                                label="Product prices already include tax (Gross Pricing)"
                            />
                        </div>
                    </div>
                </div>

                <!-- 5. Thermal Receipts Tab -->
                <div v-show="activeTab === 'receipt'" class="card" style="margin-bottom: var(--space-6);">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin-bottom: var(--space-4);">
                        Thermal Receipt Defaults
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                        <BaseCheckbox
                            v-model="form.receipt_show_logo"
                            label="Print business logo on receipt headers"
                        />

                        <div class="form-group">
                            <label class="form-label">Receipt Header Message</label>
                            <textarea
                                v-model="form.receipt_header"
                                class="form-textarea"
                                rows="3"
                                placeholder="Thank you for shopping at Acme Stores! Visit us online at acme.test"
                            ></textarea>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">
                                Displayed immediately below store name and address on printed receipts.
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Receipt Footer Message / Return Policy</label>
                            <textarea
                                v-model="form.receipt_footer"
                                class="form-textarea"
                                rows="3"
                                placeholder="Items may be returned within 30 days with receipt. No refunds on clearance items."
                            ></textarea>
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">
                                Displayed at the bottom of customer receipts.
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer -->
                <div class="card" style="position: sticky; bottom: 1rem; z-index: 10; display: flex; align-items: center; justify-content: flex-end; box-shadow: var(--shadow-lg);">
                    <BaseButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                    >
                        <CheckCircle2 :size="16" />
                        <span>Save Business Settings</span>
                    </BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
