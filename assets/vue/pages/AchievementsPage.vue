<script setup>
import { computed, ref } from 'vue';
import { Lock, Trophy } from '../icons.js';
import { useI18n } from 'vue-i18n';
import PageHeader from '../components/ui/PageHeader.vue';
import { useApi } from '../composables/useApi.js';
import { useDates } from '../composables/useDates.js';
import { usePlayer } from '../composables/usePlayer.js';

const { t } = useI18n();
const dates = useDates();
const { player } = usePlayer();
const achievements = ref(null);
useApi().load('/api/achievements', achievements);

const unlockedCount = computed(() => (achievements.value ?? []).filter((achievement) => achievement.unlockedAt).length);
</script>

<template>
    <div class="page page--wide">
        <PageHeader :title="t('achievements.title')" :subtitle="achievements ? t('achievements.progress', { unlocked: unlockedCount, total: achievements.length }) : null" />
        <p v-if="player" class="badges__best">{{ t('achievements.bestStreak', player.bestStreak) }}</p>
        <ul v-if="achievements" class="badges">
            <li v-for="achievement in achievements" :key="achievement.id" :class="['badges__item', { 'badges__item--locked': !achievement.unlockedAt }]">
                <span class="badges__medal" aria-hidden="true">
                    <Trophy v-if="achievement.unlockedAt" size="1.5rem" />
                    <Lock v-else size="1.25rem" />
                </span>
                <span class="badges__text">
                    <strong>{{ t(`achievements.${achievement.id}.title`) }}</strong>
                    <span class="badges__description">{{ t(`achievements.${achievement.id}.description`) }}</span>
                    <span v-if="achievement.unlockedAt" class="badges__date">{{ t('achievements.unlockedOn', { date: dates.short(achievement.unlockedAt.slice(0, 10)) }) }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.badges__best { color: var(--color-muted); }
.badges { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.badges__item { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-4); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.badges__medal { display: grid; place-items: center; flex-shrink: 0; width: 3rem; height: 3rem; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent); }
.badges__item--locked { border-style: dashed; background: transparent; }
.badges__item--locked .badges__medal { background: var(--color-hover); color: var(--color-subtle); }
.badges__item--locked strong { color: var(--color-muted); font-weight: 600; }
.badges__text { display: flex; flex-direction: column; gap: 0.125rem; }
.badges__description { color: var(--color-muted); font-size: var(--font-size-sm); }
.badges__date { color: var(--color-subtle); font-size: var(--font-size-xs); }
</style>
