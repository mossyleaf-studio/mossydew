<script setup>
import { computed, ref } from 'vue';
import { Lock, Shovel, Sprout } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import IconButton from '../ui/IconButton.vue';
import RarityMark from '../herbarium/RarityMark.vue';
import { useDates } from '../../composables/useDates.js';
import { speciesOf } from '../../herbarium/catalog.js';

const props = defineProps({
    pots: { type: Array, required: true },
    maxPots: { type: Number, required: true },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['plant', 'unplant']);

const { t } = useI18n();
const dates = useDates();

const rows = computed(() => props.pots.map((pot) => ({ ...pot, moss: pot.species ? speciesOf(pot.species) : null, name: pot.species ? t(`species.${pot.species}`) : null })));
const frame = ref(null);

function focusPot(number) {
    frame.value?.querySelector(`[data-test="pot-${number}"] .pots__main`)?.focus();
}

defineExpose({ focusPot });

const locked = computed(() => (props.pots.length < props.maxPots ? { number: props.pots.length + 1, level: props.pots.length } : null));
</script>

<template>
    <div ref="frame" class="pots-frame">
        <table class="pots">
            <thead class="pots__head">
                <tr>
                    <th scope="col" class="pots__number">{{ t('greenhouse.pots.number') }}</th>
                    <th scope="col">{{ t('greenhouse.pots.moss') }}</th>
                    <th scope="col">{{ t('greenhouse.pots.rarity') }}</th>
                    <th scope="col" class="pots__numeric">{{ t('greenhouse.pots.yield') }}</th>
                    <th scope="col">{{ t('greenhouse.pots.since') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ t('greenhouse.pots.actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="pot in rows" :key="pot.number" :class="['pots__row', { 'pots__row--empty': !pot.species }]" :data-test="`pot-${pot.number}`">
                    <th scope="row" class="pots__number tabular">{{ pot.number }}</th>
                    <template v-if="pot.species">
                        <td class="pots__moss">
                            <span class="pots__specimen">
                                <img v-if="pot.moss" class="pots__photo" :src="pot.moss.photo" alt="" loading="lazy" />
                                <span class="pots__names">
                                    <span class="pots__name">{{ pot.name }}</span>
                                    <span v-if="pot.moss && pot.moss.scientificName !== pot.name" class="pots__latin">{{ pot.moss.scientificName }}</span>
                                </span>
                            </span>
                        </td>
                        <td class="pots__rarity"><RarityMark :rarity="pot.rarity" /></td>
                        <td class="pots__yield pots__numeric tabular">{{ t('greenhouse.pots.perTask', { dew: pot.yield }) }}</td>
                        <td class="pots__since">{{ pot.plantedAt ? dates.short(pot.plantedAt.slice(0, 10)) : '' }}</td>
                        <td class="pots__actions">
                            <BaseButton class="pots__main" variant="secondary" :disabled="busy" @click="emit('plant', pot.number)">{{ t('greenhouse.pots.change') }}</BaseButton>
                            <IconButton :icon="Shovel" :label="t('greenhouse.pots.unplant', { name: pot.name })" :disabled="busy" @click="emit('unplant', pot.number)" />
                        </td>
                    </template>
                    <template v-else>
                        <td class="pots__empty" colspan="4"><span class="pots__slot">{{ t('greenhouse.pots.empty') }}</span></td>
                        <td class="pots__actions">
                            <BaseButton class="pots__main" :disabled="busy" @click="emit('plant', pot.number)"><Sprout size="1rem" :stroke-width="2" aria-hidden="true" />{{ t('greenhouse.pots.plant') }}</BaseButton>
                        </td>
                    </template>
                </tr>
                <tr v-if="locked" class="pots__row pots__row--locked">
                    <th scope="row" class="pots__number tabular">{{ locked.number }}</th>
                    <td class="pots__empty" colspan="5">
                        <span class="pots__lock"><Lock size="0.875rem" :stroke-width="2" aria-hidden="true" />{{ t('greenhouse.pots.locked', { level: locked.level }) }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.pots-frame { container-type: inline-size; }
.pots { width: 100%; border: var(--border-width) solid var(--color-border); border-radius: var(--radius); border-collapse: separate; border-spacing: 0; background: var(--color-surface); font-size: var(--font-size-md); }
.pots th, .pots td { padding: var(--space-2) var(--space-3); border-bottom: var(--border-width) solid var(--color-border); text-align: left; vertical-align: middle; }
.pots tbody tr:last-child > * { border-bottom: none; }
.pots__head th { color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; white-space: nowrap; }
.pots__number { width: 2.5rem; color: var(--color-muted); font-weight: 600; }
.pots__numeric { text-align: right !important; }

.pots__moss { width: 100%; min-width: 12rem; max-width: 0; }
.pots__specimen { display: flex; align-items: center; gap: var(--space-3); min-width: 0; }
.pots__photo { flex-shrink: 0; width: 2.75rem; height: 2.75rem; border-radius: var(--radius-sm); object-fit: cover; background: var(--color-accent-soft); }
.pots__names { display: flex; flex-direction: column; min-width: 0; }
.pots__name { overflow: hidden; color: var(--color-ink); font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.pots__latin { overflow: hidden; color: var(--color-muted); font-size: var(--font-size-sm); font-style: italic; text-overflow: ellipsis; white-space: nowrap; }
.pots__yield { color: color-mix(in oklch, var(--color-dew) 65%, var(--color-ink)); font-weight: 600; white-space: nowrap; }
.pots__since { color: var(--color-muted); white-space: nowrap; }
.pots__actions { white-space: nowrap; text-align: right !important; }
.pots__actions > * + * { margin-left: var(--space-1); }

.pots__slot { display: flex; align-items: center; min-height: 2.75rem; padding: 0 var(--space-3); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius-sm); color: var(--color-subtle); }
.pots__row--locked { background: var(--color-bg); }
.pots__row--locked .pots__number { color: var(--color-subtle); }
.pots__lock { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-subtle); }

@container (max-width: 44rem) {
    .pots, .pots tbody { display: block; }
    .pots__head { position: absolute; width: 0.0625rem; height: 0.0625rem; overflow: hidden; clip: rect(0 0 0 0); }
    .pots__row {
        display: grid;
        grid-template-columns: 1.75rem auto auto minmax(0, 1fr);
        grid-template-areas: "number moss moss moss" "number rarity yield since" "number actions actions actions";
        align-items: center;
        gap: var(--space-2) var(--space-3);
        padding: var(--space-3);
        border-bottom: var(--border-width) solid var(--color-border);
    }
    .pots tbody tr:last-child { border-bottom: none; }
    .pots th, .pots td { padding: 0; border: none; }
    .pots__number { grid-area: number; align-self: start; width: auto; padding-top: var(--space-1) !important; }
    .pots__moss { grid-area: moss; width: auto; max-width: none; min-width: 0; }
    .pots__rarity { grid-area: rarity; }
    .pots__yield { grid-area: yield; }
    .pots__since { grid-area: since; text-align: right; }
    .pots__actions { grid-area: actions; display: flex; justify-content: flex-end; }
    .pots__empty { grid-area: moss; }
    .pots__row--empty, .pots__row--locked { grid-template-areas: "number moss moss moss" "number actions actions actions"; }
}
</style>
