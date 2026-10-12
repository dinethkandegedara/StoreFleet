/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: string;
    is_super_admin: boolean;
    theme_preference?: string;
}

export interface BusinessConfig {
    name: string;
    logo?: string | null;
    primary_color: string;
    currency_code: string;
    currency_symbol: string;
    currency_precision: number;
    timezone: string;
}

export interface BranchSummary {
    id: number;
    name: string;
    code: string;
}

export interface SharedPageProps {
    appName: string;
    business?: BusinessConfig;
    flash: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };
    auth: {
        user?: AuthUser | null;
        active_branch?: BranchSummary | null;
        authorized_branches?: BranchSummary[];
        permissions?: string[];
        roles?: string[];
        active_branch_id?: number | null;
    };
    [key: string]: unknown;
}
