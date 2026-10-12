<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import { Mail, ArrowLeft } from 'lucide-vue-next';

interface Props {
    status?: string | null;
}

defineProps<Props>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <Head title="Forgot Password - StoreFleet" />

    <div class="min-h-screen flex items-center justify-center" style="padding: var(--space-4); background-color: var(--bg-page);">
        <div class="card card-elevated" style="max-width: 420px; width: 100%; padding: var(--space-8);">
            <div style="margin-bottom: var(--space-6);">
                <Link href="/login" class="btn btn-ghost btn-sm" style="padding-left: 0; margin-bottom: var(--space-4);">
                    <ArrowLeft :size="16" />
                    <span>Back to sign in</span>
                </Link>

                <h1 style="font-size: 1.4rem; font-weight: 700; margin-bottom: var(--space-2); color: var(--text-primary);">Reset Password</h1>
                <p style="font-size: 0.875rem; color: var(--text-secondary);">
                    Forgot your password? Enter your email address and we will email you a password reset link.
                </p>
            </div>

            <BaseAlert v-if="status" variant="success" style="margin-bottom: var(--space-4);">
                {{ status }}
            </BaseAlert>

            <form @submit.prevent="submit">
                <BaseInput
                    v-model="form.email"
                    label="Email Address"
                    type="email"
                    placeholder="name@business.com"
                    required
                    :error="form.errors.email"
                >
                    <template #leading><Mail :size="16" /></template>
                </BaseInput>

                <div style="margin-top: var(--space-6);">
                    <BaseButton
                        type="submit"
                        variant="primary"
                        style="width: 100%;"
                        :loading="form.processing"
                    >
                        Email Password Reset Link
                    </BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>
