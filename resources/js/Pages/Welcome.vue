<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { 
    Sun, 
    Moon, 
    CheckCircle2, 
    Server, 
    Database, 
    Cpu, 
    Layers, 
    ShieldCheck, 
    Sparkles,
    Palette
} from 'lucide-vue-next';

interface Props {
    appName: string;
    phpVersion: string;
    laravelVersion: string;
    dbDriver: string;
    dbConnected: boolean;
    appEnv: string;
}

const props = defineProps<Props>();
const { theme, primaryColor, toggleTheme, setPrimaryColor, initTheme } = useTheme();

onMounted(() => {
    initTheme();
});

const presetColors = [
    { name: 'Classic Blue', hex: '#2563eb' },
    { name: 'Emerald POS', hex: '#059669' },
    { name: 'Indigo Modern', hex: '#4f46e5' },
    { name: 'Purple Luxe', hex: '#7c3aed' },
    { name: 'Amber Glow', hex: '#d97706' },
    { name: 'Rose Accent', hex: '#e11d48' },
];
</script>

<template>
    <Head title="System Foundation - BranchWise POS" />

    <div class="min-h-screen flex flex-col">
        <!-- Glass Navigation Header -->
        <header class="glass-nav">
            <div class="container flex items-center justify-between" style="height: 4.25rem;">
                <div class="flex items-center gap-3">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: var(--radius-md); background: linear-gradient(135deg, var(--primary), var(--primary-hover)); display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 10px rgba(var(--primary-rgb), 0.35);">
                        <Layers :size="22" />
                    </div>
                    <div>
                        <h1 style="font-size: 1.25rem; font-weight: 700; line-height: 1.2;">{{ appName }}</h1>
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Phase 1: Application Foundation</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Theme Toggle -->
                    <button 
                        @click="toggleTheme" 
                        class="btn btn-secondary btn-sm" 
                        :title="theme === 'dark' ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
                        aria-label="Toggle theme"
                    >
                        <Sun v-if="theme === 'dark'" :size="16" />
                        <Moon v-else :size="16" />
                        <span>{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container flex-1" style="padding-top: 2.5rem; padding-bottom: 3.5rem;">
            <!-- Hero Banner -->
            <div class="card card-elevated" style="margin-bottom: 2rem; background: linear-gradient(180deg, var(--bg-surface) 0%, var(--bg-subtle) 100%); border-left: 4px solid var(--primary);">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <span class="badge badge-success">
                            <CheckCircle2 :size="12" /> Phase 1 Verified
                        </span>
                        <span class="badge badge-primary">Shared Hosting Ready</span>
                    </div>
                    <h2>Production POS Architecture Initialized</h2>
                    <p style="max-width: 48rem; font-size: 1.05rem;">
                        The foundation for <strong>BranchWise POS</strong> is configured according to the approved architectural blueprint.
                        The backend couples Laravel 12 with an Inertia.js protocol layer driving Vue 3 and TypeScript, all compiled to static assets compatible with ordinary shared hosting.
                    </p>
                </div>
            </div>

            <!-- Diagnostics & Theme Controls Grid -->
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <!-- System Diagnostics -->
                <div class="card">
                    <div class="flex items-center gap-2" style="margin-bottom: 1.25rem;">
                        <Server :size="20" style="color: var(--primary);" />
                        <h3>Runtime Diagnostics</h3>
                    </div>

                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between" style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                            <span style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                <Cpu :size="16" /> Framework
                            </span>
                            <span class="mono" style="font-weight: 600; font-size: 0.875rem;">Laravel {{ laravelVersion }}</span>
                        </div>

                        <div class="flex items-center justify-between" style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                            <span style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                <Layers :size="16" /> PHP Environment
                            </span>
                            <span class="mono" style="font-weight: 600; font-size: 0.875rem;">PHP {{ phpVersion }}</span>
                        </div>

                        <div class="flex items-center justify-between" style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                            <span style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                <Database :size="16" /> Relational Database
                            </span>
                            <span class="badge" :class="dbConnected ? 'badge-success' : 'badge-danger'">
                                {{ dbDriver.toUpperCase() }} {{ dbConnected ? 'Connected' : 'Error' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span style="font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                <ShieldCheck :size="16" /> Environment Mode
                            </span>
                            <span class="badge badge-primary">{{ appEnv.toUpperCase() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Theme Customization Engine -->
                <div class="card">
                    <div class="flex items-center gap-2" style="margin-bottom: 1.25rem;">
                        <Palette :size="20" style="color: var(--primary);" />
                        <h3>Customizable Theme Engine</h3>
                    </div>

                    <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                        Test the dynamic business accent color system. Brand colors are stored as CSS custom properties and adapt instantly across both light and dark themes without page reloads.
                    </p>

                    <div style="margin-bottom: 1.25rem;">
                        <span class="form-label" style="display: block; margin-bottom: 0.5rem;">Select Accent Palette:</span>
                        <div class="flex items-center gap-2" style="flex-wrap: wrap;">
                            <button 
                                v-for="color in presetColors" 
                                :key="color.hex"
                                @click="setPrimaryColor(color.hex)"
                                :style="{ backgroundColor: color.hex }"
                                style="width: 2.25rem; height: 2.25rem; border-radius: var(--radius-md); border: 2px solid white; box-shadow: var(--shadow-sm); cursor: pointer; transition: transform var(--transition-fast);"
                                :title="color.name"
                            ></button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button class="btn btn-primary btn-sm">Primary Action</button>
                        <button class="btn btn-secondary btn-sm">Secondary Action</button>
                    </div>
                </div>
            </div>

            <!-- Architectural Foundations Summary -->
            <div class="card">
                <div class="flex items-center gap-2" style="margin-bottom: 1.25rem;">
                    <ShieldCheck :size="20" style="color: var(--primary);" />
                    <h3>Architectural Guardrails for Subsequent Phases</h3>
                </div>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                    <div style="padding: 1rem; border-radius: var(--radius-md); background-color: var(--bg-subtle);">
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.35rem;">Multi-Branch Scoping</h4>
                        <p style="font-size: 0.825rem;">Strict tenant isolation enforced via database session context, gate policies, and Eloquent global scopes.</p>
                    </div>

                    <div style="padding: 1rem; border-radius: var(--radius-md); background-color: var(--bg-subtle);">
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.35rem;">Super Admin Separation</h4>
                        <p style="font-size: 0.825rem;">Independent administrative identity with dedicated audit logs, distinct from store owners and cashiers.</p>
                    </div>

                    <div style="padding: 1rem; border-radius: var(--radius-md); background-color: var(--bg-subtle);">
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.35rem;">Transaction-Safe Checkout</h4>
                        <p style="font-size: 0.825rem;">Database transactions, sequential invoice numbers, idempotency tokens, and immutable price snapshots.</p>
                    </div>

                    <div style="padding: 1rem; border-radius: var(--radius-md); background-color: var(--bg-subtle);">
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.35rem;">Optimized Image Pipeline</h4>
                        <p style="font-size: 0.825rem;">Intervention GD processor generating cropped WebP assets while stripping EXIF metadata and purging originals.</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer style="border-top: 1px solid var(--border-subtle); padding: 1.5rem 0; background-color: var(--bg-surface); text-align: center;">
            <div class="container flex items-center justify-between" style="flex-wrap: wrap; gap: 1rem;">
                <p style="font-size: 0.825rem;">
                    <strong>{{ appName }}</strong> &bull; Production-Grade POS Foundation
                </p>
                <p style="font-size: 0.825rem; color: var(--text-muted);">
                    Built with Laravel, Vue 3, Inertia.js &amp; TypeScript for Shared Hosting
                </p>
            </div>
        </footer>
    </div>
</template>
