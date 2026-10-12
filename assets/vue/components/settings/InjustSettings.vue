<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { intlLocale } from '../../i18n/locale.js';

const URL = '/api/integrations/injust';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const link = ref(null);
const token = ref(null);
const address = window.location.origin;
const moment = new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' });

api.load(URL, link).catch(() => {});

const status = computed(() => {
    if (!link.value?.tokenCreatedAt) return t('settings.injust.off');
    if (!link.value.feedAt) return t('settings.injust.waitingFirst');
    return t('settings.injust.lastFeed', { moment: moment.format(new Date(link.value.feedAt)), count: link.value.waiting });
});

async function createToken() {
    try {
        token.value = (await api.post(`${URL}/token`)).token;
    } catch (error) {
        toast.error(error.message);
    }
}

async function revoke() {
    try {
        await api.del(`${URL}/token`);
        token.value = null;
        toast.success(t('settings.injust.revoked'));
    } catch (error) {
        toast.error(error.message);
    }
}

async function copy(value) {
    await navigator.clipboard.writeText(value);
    toast.success(t('settings.injust.copied'));
}
</script>

<template>
    <div class="injust">
        <p class="injust__intro">{{ t('settings.injust.intro') }}</p>
        <p class="injust__status">{{ status }}</p>
        <dl v-if="token" class="injust__secret">
            <dt>{{ t('settings.injust.address') }}</dt>
            <dd><code>{{ address }}</code> <BaseButton variant="secondary" @click="copy(address)">{{ t('settings.injust.copy') }}</BaseButton></dd>
            <dt>{{ t('settings.injust.token') }}</dt>
            <dd><code>{{ token }}</code> <BaseButton variant="secondary" @click="copy(token)">{{ t('settings.injust.copy') }}</BaseButton></dd>
        </dl>
        <p v-if="token" class="injust__hint">{{ t('settings.injust.once') }}</p>
        <div class="injust__actions">
            <BaseButton variant="secondary" @click="createToken">{{ link?.tokenCreatedAt ? t('settings.injust.replace') : t('settings.injust.create') }}</BaseButton>
            <BaseButton v-if="link?.tokenCreatedAt" variant="secondary" @click="revoke">{{ t('settings.injust.revoke') }}</BaseButton>
        </div>
    </div>
</template>

<style scoped>
.injust { display: flex; flex-direction: column; gap: var(--space-3); max-width: 36rem; }
.injust__intro, .injust__hint { color: var(--color-muted); font-size: var(--font-size-md); }
.injust__secret { display: grid; gap: var(--space-2); padding: var(--space-3); border: 1px solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.injust__secret dt { font-size: var(--font-size-sm); color: var(--color-muted); }
.injust__secret dd { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); margin: 0; }
.injust__secret code { overflow-wrap: anywhere; font-size: var(--font-size-sm); }
.injust__actions { display: flex; flex-wrap: wrap; gap: var(--space-3); }
</style>
