<script setup>
import { computed, onMounted } from 'vue';
import { Sprout } from '../../icons.js';
import { DialogDescription, DialogTitle } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import SpeciesCard from './SpeciesCard.vue';
import { useSound } from '../../composables/useSound.js';

const RARITIES = ['common', 'uncommon', 'rare', 'very_rare'];
const SPORES = { common: 22, uncommon: 28, rare: 38, very_rare: 50 };

const props = defineProps({
    level: { type: Number, required: true },
    species: { type: Array, required: true },
});

const emit = defineEmits(['continue']);

const { t } = useI18n();

const rarest = computed(() => props.species.reduce((best, one) => (RARITIES.indexOf(one.rarity) > RARITIES.indexOf(best) ? one.rarity : best), 'common'));
const spores = Array.from({ length: SPORES[rarest.value] }, () => {
    const angle = Math.random() * Math.PI * 2;
    const start = 2 + Math.random() * 4;
    const distance = start + 5 + Math.random() * 7;
    return {
        sx: `${(Math.cos(angle) * start).toFixed(2)}rem`,
        sy: `${(Math.sin(angle) * start * 1.4).toFixed(2)}rem`,
        x: `${(Math.cos(angle) * distance).toFixed(2)}rem`,
        y: `${(Math.sin(angle) * distance * 1.2 - 3).toFixed(2)}rem`,
        size: `${(0.3 + Math.random() * 0.45).toFixed(2)}rem`,
        delay: `${Math.round(Math.random() * 260)}ms`,
        duration: `${Math.round(1300 + Math.random() * 1000)}ms`,
    };
});

const message = computed(() => {
    if (props.species.length === 0) return t('celebration.herbariumComplete');
    if (props.species.length > 1) return t('celebration.newSpeciesMany', props.species.length);
    return t(`celebration.newSpecies.${props.species[0].rarity}`);
});

onMounted(() => useSound().levelUp());
</script>

<template>
    <div :class="['level-up', `rarity--${rarest}`]">
        <div class="level-up__badge" aria-hidden="true">
            <span class="level-up__digits">
                <span class="level-up__from">{{ level - 1 }}</span>
                <span class="level-up__to">{{ level }}</span>
            </span>
        </div>
        <DialogTitle class="level-up__title">{{ t('celebration.levelUp', { level }) }}</DialogTitle>

        <div v-if="species.length" :class="['level-up__stage', `rarity--${rarest}`]">
            <div class="level-up__halo" aria-hidden="true" />
            <div v-for="(one, index) in species" :key="one.slug" :class="['level-up__card', `rarity--${one.rarity}`]" :style="{ '--stagger': `${index * 450}ms` }">
                <div class="level-up__flip">
                    <div class="level-up__back" aria-hidden="true">
                        <Sprout size="2.5rem" :stroke-width="1.25" />
                    </div>
                    <div class="level-up__front">
                        <SpeciesCard :slug="one.slug" :rarity="one.rarity" />
                    </div>
                </div>
            </div>
            <div class="level-up__spores" aria-hidden="true">
                <span
                    v-for="(spore, index) in spores"
                    :key="index"
                    class="level-up__spore"
                    :style="{ '--sx': spore.sx, '--sy': spore.sy, '--x': spore.x, '--y': spore.y, '--size': spore.size, '--delay': spore.delay, '--duration': spore.duration }"
                />
            </div>
        </div>

        <DialogDescription class="level-up__message">{{ message }}</DialogDescription>
        <div class="level-up__actions"><BaseButton @click="emit('continue')">{{ t('celebration.continue') }}</BaseButton></div>
    </div>
</template>

<style scoped>
.level-up { display: flex; flex-direction: column; align-items: center; gap: var(--space-4); text-align: center; }

.level-up__badge { position: relative; width: 4.5rem; height: 4.5rem; }
.level-up__badge::after { content: ""; position: absolute; inset: 0; border: 0.125rem solid var(--color-accent); border-radius: 50%; opacity: 0; animation: level-up-ring 900ms 650ms ease-out both; }
.level-up__digits {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 50%;
    background: var(--color-accent);
    color: var(--color-on-accent);
    font-family: var(--font-display);
    font-weight: var(--font-display-weight);
    font-size: 2.25rem;
    line-height: 1;
}
.level-up__from, .level-up__to { grid-area: 1 / 1; }
.level-up__from { animation: level-up-out 450ms 250ms cubic-bezier(0.5, 0, 0.75, 0) both; }
.level-up__to { animation: level-up-in 600ms 450ms cubic-bezier(0.3, 1.5, 0.5, 1) both; }

.level-up__title { margin: 0; color: var(--color-ink); font-family: var(--font-display); font-size: 2rem; font-weight: var(--font-display-weight); line-height: 1; }

.level-up__stage { position: relative; isolation: isolate; display: grid; gap: var(--space-4); width: min(19rem, 100%); perspective: 70rem; }

.level-up__card { min-width: 0; animation: level-up-rise 650ms calc(250ms + var(--stagger)) cubic-bezier(0.2, 0.8, 0.2, 1) both; }

.level-up__flip {
    display: grid;
    min-width: 0;
    transform-style: preserve-3d;
    animation: level-up-turn 900ms calc(900ms + var(--stagger)) cubic-bezier(0.3, 1.3, 0.5, 1) both;
}

.level-up__back, .level-up__front { grid-area: 1 / 1; min-width: 0; backface-visibility: hidden; }
.level-up__front { position: relative; transform: rotateY(180deg); text-align: left; }
.level-up__front :deep(.species-card__photo) { animation: level-up-develop 1300ms calc(1250ms + var(--stagger)) ease-out both; }

.level-up__back {
    display: grid;
    place-items: center;
    border: var(--border-width) solid color-mix(in oklch, var(--rarity) 50%, var(--color-border));
    border-radius: var(--radius);
    background:
        radial-gradient(circle at 50% 45%, color-mix(in oklch, var(--rarity) 22%, var(--color-surface)) 0, transparent 60%),
        repeating-radial-gradient(circle at 50% 45%, transparent 0 0.75rem, color-mix(in oklch, var(--rarity) 10%, transparent) 0.75rem 0.8125rem),
        var(--color-surface);
    color: var(--rarity);
}

.level-up__card.rarity--rare .level-up__front::after,
.level-up__card.rarity--very_rare .level-up__front::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: var(--radius);
    background: linear-gradient(105deg, transparent 35%, color-mix(in oklch, var(--color-surface) 75%, transparent) 50%, transparent 65%);
    background-size: 250% 100%;
    pointer-events: none;
    animation: level-up-shine 1100ms calc(1700ms + var(--stagger)) ease-in-out both;
}

.level-up__stage.rarity--common { --rarity: var(--color-accent); }

.level-up__halo {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: -1;
    width: 26rem;
    height: 26rem;
    margin: -13rem 0 0 -13rem;
    border-radius: 50%;
    background: radial-gradient(circle, color-mix(in oklch, var(--rarity) 45%, transparent) 0, color-mix(in oklch, var(--rarity) 12%, transparent) 40%, transparent 70%);
    opacity: 0;
    pointer-events: none;
    animation: level-up-halo 1600ms 1000ms ease-out both;
}

.level-up__spores { position: absolute; top: 40%; left: 50%; z-index: 1; pointer-events: none; }
.level-up__spore {
    position: absolute;
    width: var(--size);
    height: var(--size);
    margin: calc(var(--size) / -2);
    border-radius: 50%;
    background: radial-gradient(circle at 35% 35%, color-mix(in oklch, var(--rarity) 35%, var(--color-surface)) 0, color-mix(in oklch, var(--rarity) 85%, var(--color-accent)) 70%);
    box-shadow: 0 0 0.5rem color-mix(in oklch, var(--rarity) 50%, transparent);
    opacity: 0;
    animation: level-up-spore var(--duration) calc(1100ms + var(--delay)) cubic-bezier(0.1, 0.75, 0.3, 1) both;
}

.level-up__message { margin: 0; color: var(--color-muted); animation: level-up-fade 500ms 1700ms ease both; }
.level-up__actions { animation: level-up-fade 500ms 1900ms ease both; }

@keyframes level-up-out { to { opacity: 0; transform: translateY(-110%); } }
@keyframes level-up-in { from { opacity: 0; transform: translateY(110%); } to { opacity: 1; transform: none; } }
@keyframes level-up-ring { 0% { opacity: 0.8; transform: scale(1); } 100% { opacity: 0; transform: scale(1.9); } }
@keyframes level-up-rise { from { opacity: 0; transform: translateY(2.5rem) scale(0.88); } to { opacity: 1; transform: none; } }
@keyframes level-up-turn { from { transform: rotateY(0deg); } to { transform: rotateY(180deg); } }
@keyframes level-up-halo { 0% { opacity: 0; transform: scale(0.3); } 25% { opacity: 1; } 100% { opacity: 0; transform: scale(1.3); } }
@keyframes level-up-develop { from { filter: blur(0.75rem) saturate(0) brightness(1.25); } to { filter: none; } }
@keyframes level-up-shine { from { background-position: 120% 0; } to { background-position: -120% 0; } }
@keyframes level-up-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes level-up-spore {
    0% { opacity: 0; transform: translate(var(--sx), var(--sy)) scale(0.3); }
    12% { opacity: 1; }
    70% { opacity: 0.9; }
    100% { opacity: 0; transform: translate(var(--x), var(--y)) scale(1); }
}

@media (prefers-reduced-motion: reduce) {
    .level-up__badge::after, .level-up__spores, .level-up__halo, .level-up__from, .level-up__back { display: none; }
    .level-up__to, .level-up__card, .level-up__flip, .level-up__message, .level-up__actions, .level-up__front :deep(.species-card__photo), .level-up__front::after { animation: none; }
    .level-up__front { transform: none; }
}
</style>
