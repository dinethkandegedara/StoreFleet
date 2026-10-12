<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import BaseInput from '@/Components/Forms/BaseInput.vue';
import BaseButton from '@/Components/UI/BaseButton.vue';
import BaseCheckbox from '@/Components/Forms/BaseCheckbox.vue';
import BaseAlert from '@/Components/UI/BaseAlert.vue';
import { Layers, Lock, Mail } from 'lucide-vue-next';

interface Props {
    status?: string | null;
}

defineProps<Props>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Sign In - StoreFleet" />

    <div class="min-h-screen flex items-center justify-center" style="padding: var(--space-4); background-color: var(--bg-page);">
        <div class="card card-elevated" style="max-width: 420px; width: 100%; padding: var(--space-8);">
            <!-- Brand Header -->
            <div style="text-align: center; margin-bottom: var(--space-6);">
                <div style="width: 3rem; height: 3rem; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--primary), var(--primary-hover)); display: inline-flex; align-items: center; justify-content: center; color: white; margin-bottom: var(--space-3); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);">
                    <Layers :size="24" />
                </div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--space-1); color: var(--text-primary);">StoreFleet</h1>
                <p style="font-size: 0.875rem; color: var(--text-secondary);">Sign in to your account</p>
            </div>

            <!-- Status Alert -->
            <BaseAlert v-if="status" variant="success" style="margin-bottom: var(--space-4);">
                {{ status }}
            </BaseAlert>

            <!-- Login Form -->
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

                <BaseInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    placeholder="••••••••••••"
                    required
                    :error="form.errors.password"
                >
                    <template #leading><Lock :size="16" /></template>
                </BaseInput>

                <div class="flex items-center justify-between" style="margin-bottom: var(--space-6);">
                    <BaseCheckbox v-model="form.remember" label="Remember me" />

                    <Link href="/forgot-password" style="font-size: 0.825rem; font-weight: 500;">
                        Forgot password?
                    </Link>
                </div>

                <BaseButton
                    type="submit"
                    variant="primary"
                    style="width: 100%;"
                    :loading="form.processing"
                >
                    Sign In to StoreFleet
                </BaseButton>
            </form>
        </div>
    </div>
</template>
