import { ref } from 'vue';
import { useApi } from './useApi.js';
import { useSession } from './useSession.js';

export const mysteriesEnabled = ref(useSession()?.mysteries ?? false);

export async function chooseMysteries(enabled) {
    const previous = mysteriesEnabled.value;
    mysteriesEnabled.value = enabled;
    try {
        await useApi().put('/api/me/mysteries', { enabled });
    } catch (error) {
        mysteriesEnabled.value = previous;
        throw error;
    }
}
