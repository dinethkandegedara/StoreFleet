<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import ToastContainer from '@/Components/Feedback/ToastContainer.vue';
import { 
    Layers, 
    Store, 
    User, 
    ArrowLeft, 
    Sun, 
    Moon, 
    Clock, 
    Wifi
} from 'lucide-vue-next';

interface Props {
    branchName?: string;
    cashierName?: string;
}

withDefaults(defineProps<Props>(), {
    branchName: 'Main Store',
    cashierName: 'Cashier 01',
});

const page = usePage();
const { theme, toggleTheme, initTheme, setPrimaryColor } = useTheme();
const currentTime = ref('');
let timer: number | undefined;

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

onMounted(() => {
    initTheme();
    if ((page.props as any).business?.primary_color) {
        setPrimaryColor((page.props as any).business.primary_color);
    }
    updateClock();
    timer = window.setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="pos-shell">
        <!-- Toast Notification Host -->
        <ToastContainer />

        <!-- Distraction-Free High-Performance POS Header -->
        <header class="pos-header">
            <!-- Left: Brand & Return -->
            <div class="flex items-center gap-3">
                <Link href="/" class="btn btn-ghost btn-sm" title="Exit POS to Admin Portal">
                    <ArrowLeft :size="16" />
                    <span>Admin Portal</span>
                </Link>

                <div style="height: 1.5rem; width: 1px; background-color: var(--border-subtle);"></div>

                <div class="flex items-center gap-2">
                    <div style="width: 1.75rem; height: 1.75rem; border-radius: var(--radius-sm); background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center;">
                        <Layers :size="14" />
                    </div>
                    <span style="font-weight: 700; font-size: 0.95rem;">POS Terminal</span>
                </div>
            </div>

            <!-- Middle: Active Branch & Cashier Context -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2" style="background-color: var(--bg-subtle); padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.8rem; border: 1px solid var(--border-subtle);">
                    <Store :size="14" style="color: var(--primary);" />
                    <span>Branch:</span>
                    <strong>{{ branchName }}</strong>
                </div>

                <div class="flex items-center gap-2" style="background-color: var(--bg-subtle); padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.8rem; border: 1px solid var(--border-subtle);">
                    <User :size="14" style="color: var(--success);" />
                    <span>Cashier:</span>
                    <strong>{{ cashierName }}</strong>
                </div>
            </div>

            <!-- Right: System Status & Theme -->
            <div class="flex items-center gap-3">
                <!-- Clock -->
                <div class="flex items-center gap-1 mono" style="font-size: 0.825rem; color: var(--text-secondary); background-color: var(--bg-subtle); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                    <Clock :size="14" />
                    <span>{{ currentTime }}</span>
                </div>

                <!-- Online Indicator -->
                <div class="flex items-center gap-1" style="font-size: 0.75rem; color: var(--success);" title="Terminal Online">
                    <Wifi :size="14" />
                    <span>Online</span>
                </div>

                <!-- Dark Mode Toggle -->
                <button 
                    @click="toggleTheme" 
                    class="btn btn-secondary btn-sm" 
                    :title="theme === 'dark' ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
                    aria-label="Toggle theme"
                >
                    <Sun v-if="theme === 'dark'" :size="14" />
                    <Moon v-else :size="14" />
                </button>
            </div>
        </header>

        <!-- Main POS Body (Split view ready) -->
        <main class="pos-content">
            <slot></slot>
        </main>
    </div>
</template>
