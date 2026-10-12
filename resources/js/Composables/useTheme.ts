import { ref, onMounted } from 'vue';

export interface ThemeConfig {
    theme: 'light' | 'dark';
    primaryColor: string;
}

const currentTheme = ref<'light' | 'dark'>('light');
const currentPrimaryColor = ref<string>('#2563eb');

export function useTheme() {
    const applyTheme = (theme: 'light' | 'dark') => {
        currentTheme.value = theme;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('branchwise_theme', theme);
    };

    const toggleTheme = () => {
        applyTheme(currentTheme.value === 'dark' ? 'light' : 'dark');
    };

    const setPrimaryColor = (hex: string) => {
        currentPrimaryColor.value = hex;
        document.documentElement.style.setProperty('--primary', hex);
        
        // Compute subtle hover & rgb variations
        const hexToRgb = (h: string) => {
            const clean = h.replace('#', '');
            const bigint = parseInt(clean, 16);
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            return `${r}, ${g}, ${b}`;
        };

        try {
            const rgb = hexToRgb(hex);
            document.documentElement.style.setProperty('--primary-rgb', rgb);
            document.documentElement.style.setProperty('--primary-light', `rgba(${rgb}, 0.12)`);
            document.documentElement.style.setProperty('--primary-hover', hex);
        } catch (e) {
            // fallback
        }

        localStorage.setItem('branchwise_primary_color', hex);
    };

    const initTheme = () => {
        const savedTheme = localStorage.getItem('branchwise_theme') as 'light' | 'dark' | null;
        if (savedTheme) {
            applyTheme(savedTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }

        const savedColor = localStorage.getItem('branchwise_primary_color');
        if (savedColor) {
            setPrimaryColor(savedColor);
        }
    };

    return {
        theme: currentTheme,
        primaryColor: currentPrimaryColor,
        applyTheme,
        toggleTheme,
        setPrimaryColor,
        initTheme,
    };
}
