<script setup>
import { useI18n } from 'vue-i18n';
import { Lock } from '../../icons.js';
import { clueText } from '../../mysteries/clues.js';

const LOCKED_ROWS = 4;

const props = defineProps({
    clues: { type: Array, required: true },
    hidden: { type: Number, default: 0 },
    people: { type: Array, required: true },
    genders: { type: Array, required: true },
    kinds: { type: Array, required: true },
});

const { t } = useI18n();

const card = (clue) => clueText(clue, props, t);
</script>

<template>
    <div class="clues">
        <p v-if="!clues.length && hidden" class="clues__empty">{{ t('mysteries.clues.none') }}</p>
        <ol v-if="clues.length" class="clues__list">
            <li v-for="(clue, index) in clues" :key="index" class="clues__item" data-test="clue">
                <span class="clues__number tabular" aria-hidden="true">{{ index + 1 }}</span>
                <span class="clues__text">{{ card(clue) }}</span>
            </li>
        </ol>
        <ul v-if="hidden" class="clues__locked">
            <template v-if="hidden <= LOCKED_ROWS">
                <li v-for="locked in hidden" :key="locked" class="clues__item clues__item--locked" data-test="locked-clue">
                    <Lock class="clues__lock" size="1rem" :stroke-width="2" aria-hidden="true" />
                    <span class="clues__text">{{ t('mysteries.clues.locked') }}</span>
                </li>
            </template>
            <li v-else class="clues__item clues__item--locked" data-test="locked-clue">
                <Lock class="clues__lock" size="1rem" :stroke-width="2" aria-hidden="true" />
                <span class="clues__text">{{ t('mysteries.clues.lockedMany', { count: hidden }) }}</span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.clues { display: flex; flex-direction: column; gap: var(--space-2); }
.clues__empty { color: var(--color-muted); font-size: var(--font-size-md); }
.clues__list, .clues__locked { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }

.clues__item { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-3); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); color: var(--color-ink); }
.clues__item--locked { border-style: dashed; border-color: var(--color-border-strong); background: none; color: var(--color-muted); font-size: var(--font-size-md); }

.clues__number { display: grid; flex-shrink: 0; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent-strong); font-size: var(--font-size-xs); font-weight: 700; }
.clues__lock { flex-shrink: 0; margin-block: 0.125rem; color: var(--color-subtle); }
.clues__text { min-width: 0; overflow-wrap: anywhere; }
</style>
