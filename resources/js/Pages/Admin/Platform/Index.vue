<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import {
    Server,
    Shield,
    ShieldCheck,
    Database,
    HardDrive,
    Cpu,
    Lock,
    Terminal,
    RefreshCw,
    CheckCircle2,
    XCircle,
    Activity,
    Layers,
    Users,
    Store,
    Package,
    ReceiptText,
    FileText
} from 'lucide-vue-next';

interface SuperAdminItem {
    id: number;
    name: string;
    email: string;
    has_2fa: boolean;
    created_at: string | null;
}

interface Props {
    system: {
        php_version: string;
        laravel_version: string;
        server_software: string;
        memory_limit: string;
        max_execution_time: string;
        timezone: string;
        app_env: string;
        debug_mode: boolean;
        session_driver: string;
        cache_driver: string;
        queue_driver: string;
        db_driver: string;
        db_connected: boolean;
        db_version: string;
        webp_supported: boolean;
    };
    storage_health: Record<string, boolean>;
    extensions: Record<string, boolean>;
    super_admins: SuperAdminItem[];
    stats: {
        total_users: number;
        total_branches: number;
        total_products: number;
        total_sales: number;
        total_audit_logs: number;
    };
}

const props = defineProps<Props>();

const isClearingCache = ref(false);

const clearCache = () => {
    isClearingCache.value = true;
    router.post('/admin/platform/clear-cache', {}, {
        preserveScroll: true,
        onFinish: () => {
            isClearingCache.value = false;
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Platform &amp; System - Super Admin - StoreFleet" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            <ShieldCheck class="w-3.5 h-3.5" />
                            Platform Operator Zone
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                        <Server class="w-7 h-7 text-purple-600 dark:text-purple-400" />
                        Platform &amp; Installation Overview
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Diagnostics, runtime environment, server health, and provisioning controls for this StoreFleet installation.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        :loading="isClearingCache"
                        @click="clearCache"
                    >
                        <RefreshCw class="w-4 h-4 mr-1.5" />
                        Clear Framework Cache
                    </BaseButton>

                    <Link href="/admin/audit-logs">
                        <BaseButton variant="primary" size="sm">
                            <Activity class="w-4 h-4 mr-1.5" />
                            Audit Logs Explorer
                        </BaseButton>
                    </Link>
                </div>
            </div>

            <!-- Stats Ribbon -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider flex items-center gap-1.5">
                        <Users class="w-3.5 h-3.5 text-blue-500" />
                        Staff Users
                    </div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ stats.total_users }}
                    </div>
                </div>

                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider flex items-center gap-1.5">
                        <Store class="w-3.5 h-3.5 text-emerald-500" />
                        Branches
                    </div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ stats.total_branches }}
                    </div>
                </div>

                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider flex items-center gap-1.5">
                        <Package class="w-3.5 h-3.5 text-indigo-500" />
                        Catalog Items
                    </div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ stats.total_products }}
                    </div>
                </div>

                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider flex items-center gap-1.5">
                        <ReceiptText class="w-3.5 h-3.5 text-amber-500" />
                        Sales Orders
                    </div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ stats.total_sales }}
                    </div>
                </div>

                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 col-span-2 sm:col-span-1">
                    <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider flex items-center gap-1.5">
                        <FileText class="w-3.5 h-3.5 text-rose-500" />
                        Audit Events
                    </div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ stats.total_audit_logs }}
                    </div>
                </div>
            </div>

            <!-- Main Technical Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- 1. Runtime Environment -->
                <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Cpu class="w-4 h-4 text-purple-500" />
                        Server Runtime &amp; Framework
                    </h2>

                    <div class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-mono">
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">PHP Version</span>
                            <span class="font-bold text-slate-900 dark:text-white">{{ system.php_version }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">Laravel Framework</span>
                            <span class="font-bold text-slate-900 dark:text-white">v{{ system.laravel_version }}</span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">Environment</span>
                            <span class="font-bold uppercase" :class="system.app_env === 'production' ? 'text-emerald-600' : 'text-amber-600'">
                                {{ system.app_env }} (Debug: {{ system.debug_mode ? 'ON' : 'OFF' }})
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">Database Engine</span>
                            <span class="font-bold text-slate-900 dark:text-white">
                                {{ system.db_driver.toUpperCase() }} ({{ system.db_version }})
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">Database Connection</span>
                            <span class="font-bold" :class="system.db_connected ? 'text-emerald-600' : 'text-rose-600'">
                                {{ system.db_connected ? 'Connected' : 'Disconnected' }}
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">WebP Image Pipeline</span>
                            <span class="font-bold" :class="system.webp_supported ? 'text-emerald-600' : 'text-amber-600'">
                                {{ system.webp_supported ? 'Active (GD WebP)' : 'Unsupported' }}
                            </span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">Memory Limit / Exec Time</span>
                            <span class="text-slate-900 dark:text-white">{{ system.memory_limit }} / {{ system.max_execution_time }}s</span>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <span class="text-slate-500">Session / Cache Driver</span>
                            <span class="text-slate-900 dark:text-white">{{ system.session_driver }} / {{ system.cache_driver }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Filesystem & Extensions Health -->
                <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 space-y-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <HardDrive class="w-4 h-4 text-emerald-500" />
                        Directory Permissions &amp; Extensions
                    </h2>

                    <!-- Storage Paths -->
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Writable Directories</div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div
                                v-for="(writable, path) in storage_health"
                                :key="path"
                                class="p-2 rounded-lg border flex items-center justify-between"
                                :class="writable ? 'bg-emerald-50/50 border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-800' : 'bg-rose-50 border-rose-200'"
                            >
                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ path }}</span>
                                <CheckCircle2 v-if="writable" class="w-4 h-4 text-emerald-600 shrink-0" />
                                <XCircle v-else class="w-4 h-4 text-rose-600 shrink-0" />
                            </div>
                        </div>
                    </div>

                    <!-- PHP Extensions -->
                    <div class="pt-2">
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Essential PHP Extensions</div>
                        <div class="grid grid-cols-4 gap-2 text-xs">
                            <div
                                v-for="(loaded, ext) in extensions"
                                :key="ext"
                                class="p-2 rounded border text-center font-mono font-medium"
                                :class="loaded ? 'bg-slate-50 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700' : 'bg-rose-50 text-rose-700 border-rose-200'"
                            >
                                {{ ext }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Super Admin Provisioning & Security Posture -->
            <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-sm dark:bg-slate-900 dark:border-slate-800 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Lock class="w-4 h-4 text-purple-600" />
                            Super Admin Operator Accounts &amp; Provisioning
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Platform operators are separated from regular tenant business users. Public registration is permanently disabled.
                        </p>
                    </div>

                    <div class="text-xs text-slate-400">
                        <span class="font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">
                            php artisan storefleet:super-admin:provision
                        </span>
                    </div>
                </div>

                <div class="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 font-semibold text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="py-2.5 px-3">Operator Name</th>
                                <th class="py-2.5 px-3">Email Address</th>
                                <th class="py-2.5 px-3 text-center">Two-Factor Authentication</th>
                                <th class="py-2.5 px-3 text-right">Provisioned On</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr v-for="admin in super_admins" :key="admin.id">
                                <td class="py-2.5 px-3 font-medium text-slate-900 dark:text-white">
                                    {{ admin.name }}
                                </td>
                                <td class="py-2.5 px-3 font-mono">
                                    {{ admin.email }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span
                                        v-if="admin.has_2fa"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200"
                                    >
                                        <ShieldCheck class="w-3 h-3" />
                                        TOTP 2FA Enforced
                                    </span>
                                    <span v-else class="text-amber-600 font-semibold">
                                        Pending Setup
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right text-slate-500">
                                    {{ admin.created_at }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CLI Provisioning Instructions Box -->
                <div class="p-4 bg-slate-900 text-slate-200 rounded-lg font-mono text-xs space-y-2">
                    <div class="flex items-center gap-2 text-slate-400 border-b border-slate-800 pb-2">
                        <Terminal class="w-4 h-4 text-emerald-400" />
                        <span>Artisan CLI Provisioning Reference</span>
                    </div>
                    <div class="text-slate-300">
                        # Interactive provisioning with secure TOTP 2FA secret generation &amp; recovery codes:
                    </div>
                    <div class="text-emerald-400 font-bold">
                        php artisan storefleet:super-admin:provision
                    </div>
                    <div class="text-slate-400 text-[11px] pt-1">
                        * Re-execution is refused automatically if an operator account exists unless the <span class="text-white">--force</span> flag is provided.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
