import { ref } from 'vue';
import { useUserPreferences } from '@/composables/useUserPreferences.js';

let idCounter = 0;
const toasts = ref([]);

export function useToast() {
    const { allowsToast } = useUserPreferences();

    function addToast(message, type = 'success', duration = 4000) {
        if (!allowsToast(type)) {
            return;
        }

        const id = ++idCounter;
        toasts.value.push({ id, message, type });
        setTimeout(() => removeToast(id), duration);
    }

    function removeToast(id) {
        const index = toasts.value.findIndex((toast) => toast.id === id);

        if (index !== -1) {
            toasts.value.splice(index, 1);
        }
    }

    return { toasts, addToast, removeToast };
}
