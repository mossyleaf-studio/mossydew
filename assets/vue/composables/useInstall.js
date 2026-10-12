import { computed, ref } from 'vue';

const offer = ref(null);
const installed = ref(window.matchMedia('(display-mode: standalone)').matches);

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    offer.value = event;
});

window.addEventListener('appinstalled', () => {
    installed.value = true;
    offer.value = null;
});

export function useInstall() {
    const state = computed(() => {
        if (installed.value) return 'installed';
        return offer.value ? 'prompt' : 'manual';
    });

    async function install() {
        if (!offer.value) return;
        await offer.value.prompt();
        offer.value = null;
    }

    return { state, install };
}
