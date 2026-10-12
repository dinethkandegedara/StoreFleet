<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Home, RefreshCw } from 'lucide-vue-next';

interface Props {
    status: number;
}

const props = defineProps<Props>();

const title = computed(() => {
    return {
        503: '503: Service Unavailable',
        500: '500: Server Error',
        404: '404: Page Not Found',
        403: '403: Forbidden',
    }[props.status] || `${props.status}: An Error Occurred`;
});

const description = computed(() => {
    return {
        503: 'Sorry, we are doing some maintenance. Please check back soon.',
        500: 'Whoops, something went wrong on our servers. Internal errors are logged for review.',
        404: 'Sorry, the page or resource you are looking for could not be found.',
        403: 'Sorry, you are forbidden from accessing this resource.',
    }[props.status] || 'An unexpected error occurred. Please try again later.';
});

const reload = () => {
    if (typeof window !== 'undefined') {
        window.location.reload();
    }
};
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen flex items-center justify-center" style="padding: 2rem;">
        <div class="card card-elevated" style="max-width: 32rem; width: 100%; text-align: center; padding: 2.5rem;">
            <div style="width: 4rem; height: 4rem; margin: 0 auto 1.5rem auto; border-radius: 50%; background-color: var(--danger-light); display: flex; align-items: center; justify-content: center; color: var(--danger);">
                <AlertTriangle :size="32" />
            </div>

            <h1 style="font-size: 1.75rem; margin-bottom: 0.75rem;">{{ title }}</h1>
            <p style="margin-bottom: 2rem;">{{ description }}</p>

            <div class="flex items-center justify-center gap-3">
                <Link href="/" class="btn btn-primary btn-sm">
                    <Home :size="16" />
                    <span>Return to Dashboard</span>
                </Link>
                <button @click="reload" class="btn btn-secondary btn-sm">
                    <RefreshCw :size="16" />
                    <span>Try Again</span>
                </button>
            </div>
        </div>
    </div>
</template>
