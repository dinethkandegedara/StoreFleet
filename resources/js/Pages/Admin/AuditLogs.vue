<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseTable from '@/Components/UI/BaseTable.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseSelect from '@/Components/Forms/BaseSelect.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import { ShieldCheck, Search, Filter, History } from 'lucide-vue-next';

interface UserItem {
    id: number;
    name: string;
    email: string;
}

interface LogItem {
    id: number;
    user_id: number | null;
    event: string;
    ip_address: string | null;
    user_agent: string | null;
    payload: Record<string, any> | null;
    created_at: string;
    user: UserItem | null;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface Props {
    logs: Pagination<LogItem>;
    filters: {
        search?: string;
        event?: string;
    };
    availableEvents: string[];
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const selectedEvent = ref(props.filters.event || '');

const applyFilters = () => {
    router.get('/admin/audit-logs', {
        search: search.value || undefined,
        event: selectedEvent.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedEvent.value = '';
    applyFilters();
};

const getEventVariant = (event: string) => {
    if (event.includes('failed') || event.includes('throttled') || event.includes('unauthorized')) return 'danger';
    if (event.includes('provisioned') || event.includes('login') || event.includes('completed')) return 'success';
    if (event.includes('challenge') || event.includes('reset')) return 'warning';
    return 'neutral';
};

const formatDate = (dateStr: string) => {
    const d = new Date(dateStr);
    return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
};
</script>

<template>
    <AppLayout>
        <Head title="Security & Audit Logs - StoreFleet" />

        <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-6);">
            <!-- Header Banner -->
            <div class="card card-elevated" style="border-left: 4px solid var(--primary);">
                <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: var(--space-4);">
                    <div>
                        <div class="flex items-center gap-2" style="margin-bottom: var(--space-2);">
                            <BaseBadge variant="danger" dot>Super Admin Only</BaseBadge>
                            <BaseBadge variant="neutral">Platform Governance</BaseBadge>
                        </div>
                        <h2>System Security &amp; Audit Logs</h2>
                        <p style="font-size: 0.95rem; margin-top: var(--space-1);">
                            Immutable chronological record of sensitive authentication, 2FA challenges, privilege escalation attempts, and administrative events.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="mono" style="font-size: 0.85rem; color: var(--text-muted);">
                            Total Events: {{ logs.total }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="card" style="padding: var(--space-4);">
                <div class="flex items-center gap-4" style="flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 240px;">
                        <BaseInput
                            v-model="search"
                            placeholder="Search by event, IP address, or user name..."
                            @keydown.enter="applyFilters"
                        >
                            <template #leading><Search :size="16" /></template>
                        </BaseInput>
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <BaseSelect
                            v-model="selectedEvent"
                            :options="[
                                { label: 'All Event Types', value: '' },
                                ...availableEvents.map(e => ({ label: e, value: e }))
                            ]"
                            @change="applyFilters"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <BaseButton variant="primary" size="sm" @click="applyFilters">
                            <Filter :size="14" />
                            <span>Filter</span>
                        </BaseButton>

                        <BaseButton variant="secondary" size="sm" @click="resetFilters">
                            Reset
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <BaseTable 
                    :empty="logs.data.length === 0"
                    empty-title="No Audit Records Found"
                    empty-description="There are no security events matching your current filter criteria."
                >
                    <template #header>
                        <tr>
                            <th>Timestamp</th>
                            <th>Security Event</th>
                            <th>Actor / Target</th>
                            <th>IP Address</th>
                            <th>Payload Metadata</th>
                        </tr>
                    </template>

                    <template #default>
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="mono" style="font-size: 0.825rem; white-space: nowrap;">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td>
                                <BaseBadge :variant="getEventVariant(log.event)" dot>
                                    {{ log.event }}
                                </BaseBadge>
                            </td>
                            <td>
                                <div v-if="log.user">
                                    <div style="font-weight: 600; font-size: 0.875rem;">{{ log.user.name }}</div>
                                    <div style="font-size: 0.775rem; color: var(--text-muted);">{{ log.user.email }}</div>
                                </div>
                                <span v-else style="color: var(--text-muted); font-size: 0.825rem;">
                                    Anonymous / System
                                </span>
                            </td>
                            <td class="mono" style="font-size: 0.825rem;">
                                {{ log.ip_address || '—' }}
                            </td>
                            <td>
                                <code v-if="log.payload" class="mono" style="font-size: 0.75rem; background-color: var(--bg-subtle); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); word-break: break-all;">
                                    {{ JSON.stringify(log.payload) }}
                                </code>
                                <span v-else style="color: var(--text-muted); font-size: 0.75rem;">—</span>
                            </td>
                        </tr>
                    </template>
                </BaseTable>

                <!-- Pagination Footer -->
                <div v-if="logs.last_page > 1" class="flex items-center justify-between" style="padding: var(--space-4) var(--space-6); border-top: 1px solid var(--border-subtle); background-color: var(--bg-subtle);">
                    <span style="font-size: 0.825rem; color: var(--text-secondary);">
                        Page {{ logs.current_page }} of {{ logs.last_page }}
                    </span>

                    <div class="flex items-center gap-2">
                        <BaseButton 
                            v-if="logs.prev_page_url" 
                            variant="secondary" 
                            size="sm"
                            @click="router.get(logs.prev_page_url!)"
                        >
                            Previous
                        </BaseButton>

                        <BaseButton 
                            v-if="logs.next_page_url" 
                            variant="secondary" 
                            size="sm"
                            @click="router.get(logs.next_page_url!)"
                        >
                            Next
                        </BaseButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
