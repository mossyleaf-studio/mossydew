<script setup>
import { computed, watch } from 'vue';
import { Trophy } from '../../icons.js';
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import LevelUpReveal from './LevelUpReveal.vue';
import { useCelebration } from '../../composables/useCelebration.js';
import { usePlayer } from '../../composables/usePlayer.js';

const { t } = useI18n();
const { state, achievementsDue, unlocked, dismissLevelUp, dismissAchievements } = useCelebration();
const { player, markAchievementsSeen } = usePlayer();

watch(() => player.value?.newAchievements, (ids) => {
    if (ids?.length) unlocked(ids);
}, { immediate: true });

const levelUpOpen = computed({ get: () => state.levelUp !== null, set: (open) => { if (!open) dismissLevelUp(); } });
const achievementsOpen = computed({
    get: () => achievementsDue.value,
    set: (open) => {
        if (!open) {
            dismissAchievements();
            markAchievementsSeen().catch(() => {});
        }
    },
});
</script>

<template>
    <DialogRoot v-model:open="levelUpOpen">
        <DialogPortal>
            <DialogOverlay class="modal">
                <DialogContent class="modal__panel celebration celebration--level-up">
                    <LevelUpReveal v-if="state.levelUp" :level="state.levelUp" :species="state.newSpecies" @continue="levelUpOpen = false" />
                </DialogContent>
            </DialogOverlay>
        </DialogPortal>
    </DialogRoot>

    <DialogRoot v-model:open="achievementsOpen">
        <DialogPortal>
            <DialogOverlay class="modal">
                <DialogContent class="modal__panel celebration">
                    <DialogTitle class="celebration__title">{{ t('celebration.achievement', state.achievements.length) }}</DialogTitle>
                    <DialogDescription as="div">
                        <ul class="celebration__list">
                            <li v-for="id in state.achievements" :key="id" class="celebration__item">
                                <Trophy class="celebration__trophy" size="1.5rem" aria-hidden="true" />
                                <span>
                                    <strong>{{ t(`achievements.${id}.title`) }}</strong>
                                    <span class="celebration__text">{{ t(`achievements.${id}.description`) }}</span>
                                </span>
                            </li>
                        </ul>
                    </DialogDescription>
                    <div class="actions-row"><BaseButton @click="achievementsOpen = false">{{ t('celebration.continue') }}</BaseButton></div>
                </DialogContent>
            </DialogOverlay>
        </DialogPortal>
    </DialogRoot>
</template>

<style>
.celebration { width: min(26rem, 100%); text-align: left; }
.celebration--level-up { overflow-x: hidden; padding-block: var(--space-6); }
.celebration__title { margin: 0; color: var(--color-ink); font-family: var(--font-display); font-size: 1.9rem; font-weight: var(--font-display-weight); }
.celebration__text { display: block; color: var(--color-muted); }
.celebration__list { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.celebration__item { display: flex; align-items: flex-start; gap: var(--space-3); }
.celebration__trophy { flex-shrink: 0; color: var(--color-accent); }
</style>
