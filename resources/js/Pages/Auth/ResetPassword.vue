<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import { Lock, Mail } from 'lucide-vue-next';

interface Props {
    email: string;
    token: string;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <Head title="Choose New Password - StoreFleet" />

    <div class="min-h-screen flex items-center justify-center" style="padding: var(--space-4); background-color: var(--bg-page);">
        <div class="card card-elevated" style="max-width: 440px; width: 100%; padding: var(--space-8);">
            <div style="text-align: center; margin-bottom: var(--space-6);">
                <div style="width: 3rem; height: 3rem; border-radius: 50%; background-color: var(--primary-light); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: var(--space-3);">
                    <Lock :size="24" />
                </div>
                <h1 style="font-size: 1.4rem; font-weight: 700; margin-bottom: var(--space-1); color: var(--text-primary);">Set New Password</h1>
                <p style="font-size: 0.875rem; color: var(--text-secondary);">Create a strong, secure password for your account.</p>
            </div>

            <form @submit.prevent="submit">
                <BaseInput
                    v-model="form.email"
                    label="Email Address"
                    type="email"
                    required
                    :error="form.errors.email"
                >
                    <template #leading><Mail :size="16" /></template>
                </BaseInput>

                <BaseInput
                    v-model="form.password"
                    label="New Password"
                    type="password"
                    placeholder="••••••••••••"
                    required
                    :error="form.errors.password"
                    hint="Must be at least 8 characters with mixed case, numbers, and symbols"
                >
                    <template #leading><Lock :size="16" /></template>
                </BaseInput>

                <BaseInput
                    v-model="form.password_confirmation"
                    label="Confirm New Password"
                    type="password"
                    placeholder="••••••••••••"
                    required
                    :error="form.errors.password_confirmation"
                >
                    <template #leading><Lock :size="16" /></template>
                </BaseInput>

                <div style="margin-top: var(--space-6);">
                    <BaseButton
                        type="submit"
                        variant="primary"
                        style="width: 100%;"
                        :loading="form.processing"
                    >
                        Reset Password &amp; Sign In
                    </BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>
