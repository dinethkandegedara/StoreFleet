<script setup lang="ts">
import LoadingSkeleton from './LoadingSkeleton.vue';
import EmptyState from './EmptyState.vue';

interface Props {
    loading?: boolean;
    empty?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}

withDefaults(defineProps<Props>(), {
    loading: false,
    empty: false,
    emptyTitle: 'No records found',
    emptyDescription: 'There are no items to display at this time.',
});
</script>

<template>
    <div class="table-container">
        <table class="table">
            <thead>
                <slot name="header"></slot>
            </thead>
            <tbody>
                <tr v-if="loading">
                    <td colspan="100" style="padding: var(--space-8);">
                        <LoadingSkeleton :count="4" height="1.5rem" />
                    </td>
                </tr>
                <tr v-else-if="empty">
                    <td colspan="100" style="padding: 0;">
                        <EmptyState :title="emptyTitle" :description="emptyDescription">
                            <template #action>
                                <slot name="empty-action"></slot>
                            </template>
                        </EmptyState>
                    </td>
                </tr>
                <slot v-else></slot>
            </tbody>
        </table>
    </div>
</template>
