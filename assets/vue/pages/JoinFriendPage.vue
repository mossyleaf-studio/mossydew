<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import BaseButton from '../components/ui/BaseButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { useApi } from '../composables/useApi.js';
import { useToast } from '../composables/useToast.js';

const props = defineProps({ token: { type: String, required: true } });

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const router = useRouter();
const invitation = ref(null);
const problem = ref(null);
const accepting = ref(false);

api.get(`/api/friends/invites/${props.token}`).then((found) => { invitation.value = found; }).catch((error) => { problem.value = error.message; });

async function accept() {
    accepting.value = true;
    try {
        const friend = await api.post(`/api/friends/invites/${props.token}/accept`);
        toast.success(t('friends.join.accepted', { name: friend.displayName }));
        router.push('/friends');
    } catch (error) {
        problem.value = error.message;
    } finally {
        accepting.value = false;
    }
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('friends.join.title')" />
        <p v-if="problem" class="join__text">{{ problem }}</p>
        <template v-else-if="invitation">
            <p class="join__text">{{ t('friends.join.intro', { name: invitation.inviter }) }}</p>
            <div><BaseButton :loading="accepting" @click="accept">{{ t('friends.join.accept') }}</BaseButton></div>
        </template>
    </div>
</template>

<style scoped>
.join__text { max-width: 36rem; font-size: var(--font-size-md); }
</style>
