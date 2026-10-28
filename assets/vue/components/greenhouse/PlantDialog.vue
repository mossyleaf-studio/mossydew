<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import BaseModal from '../ui/BaseModal.vue';
import EmptyState from '../ui/EmptyState.vue';
import RarityMark from '../herbarium/RarityMark.vue';
import { speciesOf } from '../../herbarium/catalog.js';

const props = defineProps({
    pot: { type: Number, default: null },
    plantable: { type: Array, required: true },
    busy: { type: Boolean, default: false },
});
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['choose', 'closeAutoFocus']);

const { t } = useI18n();

const mosses = computed(() => props.plantable.map((moss) => ({ ...moss, photo: speciesOf(moss.species)?.photo, name: t(`species.${moss.species}`) })));
</script>

<template>
    <BaseModal v-model:open="open" :title="t('greenhouse.plant.title', { pot: pot ?? '' })" @close-auto-focus="emit('closeAutoFocus', $event)">
        <EmptyState v-if="!mosses.length" :title="t('greenhouse.plant.emptyTitle')" :hint="t('greenhouse.plant.emptyHint')">
            <RouterLink to="/herbarium" class="plant-dialog__link" @click="open = false">{{ t('greenhouse.plant.herbarium') }}</RouterLink>
        </EmptyState>
        <ul v-else class="plant-dialog">
            <li v-for="moss in mosses" :key="moss.species">
                <button
                    type="button"
                    :class="['plant-dialog__moss', `rarity--${moss.rarity}`]"
                    :disabled="busy || moss.pot !== null"
                    :data-test="`plant-${moss.species}`"
                    @click="emit('choose', moss.species)"
                >
                    <img class="plant-dialog__photo" :src="moss.photo" alt="" loading="lazy" />
                    <span class="plant-dialog__text">
                        <span class="plant-dialog__name">{{ moss.name }}</span>
                        <RarityMark :rarity="moss.rarity" />
                    </span>
                    <span class="plant-dialog__yield tabular">
                        {{ t('greenhouse.pots.perTask', { dew: moss.yield }) }}
                        <span v-if="moss.pot !== null" class="plant-dialog__where">{{ t('greenhouse.plant.inPot', { pot: moss.pot }) }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </BaseModal>
</template>

<style scoped>
.plant-dialog { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.plant-dialog__moss {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: var(--space-3);
    width: 100%;
    padding: var(--space-2);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
    text-align: left;
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition);
}
.plant-dialog__moss:hover:not(:disabled) { border-color: var(--color-accent); background: var(--color-accent-soft); }
.plant-dialog__moss:disabled { cursor: default; opacity: 0.55; }
.plant-dialog__photo { width: 3rem; height: 3rem; border-radius: var(--radius-sm); object-fit: cover; background: var(--color-accent-soft); }
.plant-dialog__text { display: flex; flex-direction: column; align-items: flex-start; gap: 0.125rem; min-width: 0; }
.plant-dialog__name { max-width: 100%; overflow: hidden; color: var(--color-ink); font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.plant-dialog__yield { display: flex; flex-direction: column; align-items: flex-end; color: color-mix(in oklch, var(--color-dew) 65%, var(--color-ink)); font-weight: 600; white-space: nowrap; }
.plant-dialog__where { color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 400; }
.plant-dialog__link { margin-top: var(--space-2); color: var(--color-accent-strong); font-weight: 600; }
</style>
