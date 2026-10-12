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
}

export interface SharedPageProps {
    appName: string;
    flash: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };
    auth: {
        user?: AuthUser | null;
    };
    [key: string]: unknown;
}
