<script setup>
import { ref } from 'vue';
import { Flame, Leaf, LogOut, Plus, UserMinus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import GoalDialog from '../components/friends/GoalDialog.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ConfirmButton from '../components/ui/ConfirmButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { useDates } from '../composables/useDates.js';
import { useToast } from '../composables/useToast.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const page = ref(null);
const invite = ref(null);
const goalOpen = ref(false);
const dates = useDates();
api.load('/api/friends', page);

async function createInvite() {
    try {
        const { token } = await api.post('/api/friends/invites');
        invite.value = `${window.location.origin}/friends/join/${token}`;
    } catch (error) {
        toast.error(error.message);
    }
}

async function copyInvite() {
    await navigator.clipboard.writeText(invite.value);
    toast.success(t('friends.copied'));
}

async function leave(goal) {
    try {
        await api.post(`/api/goals/${goal.id}/leave`);
        toast.success(t('friends.goals.left', { title: goal.title }));
    } catch (error) {
        toast.error(error.message);
    }
}

async function remove(friend) {
    try {
        await api.del(`/api/friends/${friend.id}`);
        toast.success(t('friends.removed', { name: friend.displayName }));
    } catch (error) {
        toast.error(error.message);
    }
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('friends.title')" :subtitle="t('friends.subtitle')" />
        <PageSection :title="t('friends.invite.title')">
            <p class="friends__intro">{{ t('friends.invite.intro') }}</p>
            <div v-if="invite" class="friends__invite">
                <code>{{ invite }}</code>
                <BaseButton variant="secondary" @click="copyInvite">{{ t('friends.invite.copy') }}</BaseButton>
            </div>
            <div v-else><BaseButton @click="createInvite">{{ t('friends.invite.create') }}</BaseButton></div>
        </PageSection>
        <PageSection v-if="page" :title="t('friends.goals.title')" :count="page.goals.length">
            <template #actions>
                <BaseButton v-if="page.friends.length" variant="secondary" @click="goalOpen = true"><Plus size="1rem" aria-hidden="true" />{{ t('friends.goals.new') }}</BaseButton>
            </template>
            <p v-if="!page.goals.length" class="friends__intro">{{ page.friends.length ? t('friends.goals.empty') : t('friends.goals.needFriends') }}</p>
            <ul v-else class="goals">
                <li v-for="goal in page.goals" :key="goal.id" :class="['goals__card', { 'goals__card--reached': goal.reachedAt }]">
                    <div class="goals__header">
                        <strong>{{ goal.title }}</strong>
                        <span class="goals__count tabular">{{ t('friends.goals.progress', { total: Math.min(goal.total, goal.target), target: goal.target }) }}</span>
                        <ConfirmButton :icon="LogOut" :label="t('friends.goals.leave', { title: goal.title })" :title="t('friends.goals.leave', { title: goal.title })" :message="t('friends.goals.leaveMessage')" :confirm-label="t('friends.goals.leaveConfirm')" @confirm="leave(goal)" />
                    </div>
                    <div class="goals__bar" role="img" :aria-label="t('friends.goals.progress', { total: Math.min(goal.total, goal.target), target: goal.target })">
                        <span v-for="member in goal.members.filter((candidate) => candidate.contributed)" :key="member.id" :class="['goals__share', { 'goals__share--me': member.me }]" :style="{ flexGrow: member.contributed }" />
                        <span class="goals__rest" :style="{ flexGrow: Math.max(0, goal.target - goal.total) }" />
                    </div>
                    <p class="goals__meta">
                        <span v-for="member in goal.members" :key="member.id">{{ t('friends.goals.member', { name: member.me ? t('friends.goals.you') : member.displayName, count: member.contributed }) }}</span>
                    </p>
                    <p class="goals__meta">{{ goal.reachedAt ? t('friends.goals.reached', { date: dates.short(goal.reachedAt.slice(0, 10)) }) : (goal.active ? t('friends.goals.until', { date: dates.short(goal.endsOn) }) : t('friends.goals.ended', { date: dates.short(goal.endsOn) })) }}</p>
                </li>
            </ul>
            <GoalDialog v-model:open="goalOpen" :friends="page.friends" />
        </PageSection>
        <PageSection v-if="page" :title="t('friends.list')" :count="page.friends.length">
            <EmptyState v-if="!page.friends.length" :title="t('friends.empty')" :hint="t('friends.emptyHint')" />
            <ul v-else class="friends">
                <li v-for="friend in page.friends" :key="friend.id" class="friends__card">
                    <span class="friends__level tabular" :aria-label="t('friends.level', { level: friend.level })">{{ friend.level }}</span>
                    <span class="friends__text">
                        <strong>{{ friend.displayName }}</strong>
                        <span class="friends__stats">
                            <span>{{ t('friends.xp', { xp: friend.xp }) }}</span>
                            <span><Flame size="0.875rem" aria-hidden="true" /> {{ t('friends.streak', { days: friend.streak }) }}</span>
                            <span><Leaf size="0.875rem" aria-hidden="true" /> {{ t('friends.species', { count: friend.speciesCollected }) }}</span>
                        </span>
                    </span>
                    <ConfirmButton :icon="UserMinus" :label="t('friends.remove', { name: friend.displayName })" :title="t('friends.remove', { name: friend.displayName })" :message="t('friends.removeMessage', { name: friend.displayName })" :confirm-label="t('friends.removeConfirm')" @confirm="remove(friend)" />
                </li>
            </ul>
        </PageSection>
    </div>
</template>

<style scoped>
.friends__intro { max-width: 36rem; color: var(--color-muted); font-size: var(--font-size-md); }
.friends__invite { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
.friends__invite code { overflow-wrap: anywhere; font-size: var(--font-size-sm); }
.friends { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.friends__card { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.friends__level { display: grid; place-items: center; flex-shrink: 0; width: 2.5rem; height: 2.5rem; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent); font-family: var(--font-display); font-size: 1.125rem; }
.friends__text { display: flex; flex: 1; flex-direction: column; gap: 0.125rem; min-width: 0; }
.friends__stats { display: flex; flex-wrap: wrap; gap: var(--space-3); color: var(--color-muted); font-size: var(--font-size-sm); }
.friends__stats span { display: inline-flex; align-items: center; gap: 0.25rem; }
.goals { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.goals__card { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3) var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.goals__card--reached { border-color: var(--color-accent); }
.goals__header { display: flex; align-items: center; gap: var(--space-3); }
.goals__header strong { flex: 1; min-width: 0; }
.goals__count { color: var(--color-muted); font-size: var(--font-size-sm); }
.goals__bar { display: flex; gap: 0.125rem; height: 0.5rem; overflow: hidden; border-radius: var(--radius-pill); background: var(--color-hover); }
.goals__share { background: color-mix(in oklch, var(--color-accent) 55%, var(--color-surface)); }
.goals__share--me { background: var(--color-accent); }
.goals__meta { display: flex; flex-wrap: wrap; gap: var(--space-3); margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); }
</style>
