import { createApp } from 'vue';
import './styles/fonts.css';
import './styles/tokens.css';
import './styles/base.css';
import './styles/calendar.css';
import AppShell from './vue/layouts/AppShell.vue';
import { i18n } from './vue/i18n/index.js';
import { router } from './router.js';
import './vue/composables/useInstall.js';

createApp(AppShell).use(i18n).use(router).mount('#app');

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}
