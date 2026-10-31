<script setup>
import { CollapsibleContent, CollapsibleRoot, CollapsibleTrigger } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { ChevronDown } from '../../icons.js';
import { SEATS } from '../../mysteries/board.js';
import { BLOCKERS, FURNITURE } from '../../mysteries/furniture.js';

defineProps({
    defaultOpen: { type: Boolean, default: false },
});

const RULES = ['grid', 'murderer', 'furniture', 'directions', 'terms', 'counts', 'clues', 'board', 'marks', 'accusation'];
const LEGEND = [
    { id: 'seats', pieces: SEATS },
    { id: 'blocking', pieces: BLOCKERS },
];

const { t } = useI18n();
</script>

<template>
    <CollapsibleRoot :default-open="defaultOpen" class="rules" data-test="rules">
        <CollapsibleTrigger class="rules__trigger">
            <ChevronDown class="rules__chevron" size="1.125rem" :stroke-width="2" aria-hidden="true" />
            {{ t('mysteries.rules.title') }}
        </CollapsibleTrigger>
        <CollapsibleContent>
            <div class="rules__content">
                <ul class="rules__list">
                    <li v-for="rule in RULES" :key="rule">{{ t(`mysteries.rules.${rule}`) }}</li>
                </ul>
                <div v-for="group in LEGEND" :key="group.id" :class="['rules__group', `rules__group--${group.id}`]">
                    <p :id="`rules-${group.id}`" class="rules__group-title">{{ t(`mysteries.rules.${group.id}`) }}</p>
                    <ul class="rules__legend" :aria-labelledby="`rules-${group.id}`">
                        <li v-for="piece in group.pieces" :key="piece" class="rules__piece">
                            <component :is="FURNITURE[piece]" class="rules__icon" size="1.125rem" :stroke-width="2" aria-hidden="true" />
                            {{ t(`mysteries.furniture.${piece}.name`) }}
                        </li>
                    </ul>
                </div>
            </div>
        </CollapsibleContent>
    </CollapsibleRoot>
</template>

<style scoped>
.rules { border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }

.rules__trigger { display: flex; align-items: center; gap: var(--space-2); width: 100%; padding: var(--space-3) var(--space-4); border: none; border-radius: var(--radius); background: none; color: var(--color-ink); font-weight: 600; text-align: left; cursor: pointer; }
.rules__trigger:hover { background: var(--color-hover); }
.rules__chevron { flex-shrink: 0; color: var(--color-muted); transition: transform var(--transition); }
.rules__trigger[data-state='open'] .rules__chevron { transform: rotate(180deg); }

.rules__content { display: flex; flex-direction: column; gap: var(--space-4); padding: 0 var(--space-4) var(--space-4); }
.rules__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding-left: var(--space-5); color: var(--color-muted); font-size: var(--font-size-md); }
.rules__group { display: flex; flex-direction: column; gap: var(--space-2); }
.rules__group-title { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.rules__legend { display: flex; flex-wrap: wrap; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.rules__piece { display: inline-flex; align-items: center; gap: var(--space-2); padding: var(--space-1) var(--space-3) var(--space-1) var(--space-2); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-pill); color: var(--color-ink); font-size: var(--font-size-sm); }
.rules__icon { flex-shrink: 0; color: var(--color-accent-strong); }
.rules__group--blocking .rules__icon { color: var(--color-muted); }
</style>
