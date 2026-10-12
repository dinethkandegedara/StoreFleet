import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/vite-env';

export function usePermission() {
    const page = usePage<SharedPageProps>();

    const user = computed(() => page.props.auth?.user ?? null);
    const permissions = computed<string[]>(() => page.props.auth?.permissions ?? []);
    const roles = computed<string[]>(() => page.props.auth?.roles ?? []);

    const isSuperAdmin = computed(() => Boolean(user.value?.is_super_admin));

    const can = (permissionSlug: string): boolean => {
        if (isSuperAdmin.value) {
            return true;
        }
        return permissions.value.includes(permissionSlug);
    };

    const hasRole = (roleSlug: string): boolean => {
        if (isSuperAdmin.value) {
            return true;
        }
        return roles.value.includes(roleSlug);
    };

    return {
        user,
        permissions,
        roles,
        isSuperAdmin,
        can,
        hasRole,
    };
}
