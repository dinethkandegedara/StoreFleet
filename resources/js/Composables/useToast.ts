import { ref } from 'vue';

export interface ToastItem {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    message: string;
    title?: string;
    duration?: number;
}

const toasts = ref<ToastItem[]>([]);

export function useToast() {
    const removeToast = (id: string) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };

    const addToast = (toast: Omit<ToastItem, 'id'>) => {
        const id = Math.random().toString(36).substring(2, 9);
        const duration = toast.duration ?? 4500;
        const newToast: ToastItem = { ...toast, id, duration };

        toasts.value.push(newToast);

        if (duration > 0) {
            setTimeout(() => {
                removeToast(id);
            }, duration);
        }

        return id;
    };

    const success = (message: string, title?: string) => {
        return addToast({ type: 'success', message, title });
    };

    const error = (message: string, title?: string) => {
        return addToast({ type: 'error', message, title, duration: 6000 });
    };

    const warning = (message: string, title?: string) => {
        return addToast({ type: 'warning', message, title });
    };

    const info = (message: string, title?: string) => {
        return addToast({ type: 'info', message, title });
    };

    return {
        toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
        info,
    };
}
