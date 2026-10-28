<script setup>
import { computed } from 'vue';
import { CloudRain, Droplet, Sprout, Warehouse } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import { useDew } from '../../composables/useDew.js';
import { yieldBreakdown } from '../../greenhouse/formulas.js';

const props = defineProps({
    greenhouse: { type: Object, required: true },
});

const { t } = useI18n();
const { number, decimal, yieldOf } = useDew();

const facility = (id) => props.greenhouse.facilities.find((candidate) => candidate.id === id);
const misters = computed(() => facility('misters')?.effect ?? 0);
const rainBarrel = computed(() => facility('rain_barrel')?.level ?? 0);
const usedPots = computed(() => props.greenhouse.pots.filter((pot) => pot.species).length);
const halfMultiplier = computed(() => decimal(props.greenhouse.wateringMultiplier / 2));

const yieldFormula = computed(() => {
    const { terms, sum, factor } = yieldBreakdown(props.greenhouse.pots, misters.value);
    if (!terms.length) return t('greenhouse.resources.noMoss');

    const mosses = terms.map(number).join(' + ');
    if (!misters.value) return t('greenhouse.resources.mosses', { mosses });

    const summed = terms.length > 1 ? `${mosses} = ${number(sum)}` : mosses;

    return t('greenhouse.resources.mossesMisted', { mosses: summed, factor: decimal(factor), bonus: misters.value });
});
</script>

<template>
    <dl class="resources">
        <div class="resources__tile resources__tile--dew">
            <dt class="resources__label"><Droplet size="0.875rem" :stroke-width="2" aria-hidden="true" />{{ t('greenhouse.resources.dew') }}</dt>
            <dd class="resources__value tabular" data-test="dew-balance">{{ number(greenhouse.dew) }}</dd>
            <dd class="resources__hint">{{ t('greenhouse.resources.gathered', { gathered: number(greenhouse.dewGathered) }) }}</dd>
        </div>
        <div class="resources__tile">
            <dt class="resources__label"><Sprout size="0.875rem" :stroke-width="2" aria-hidden="true" />{{ t('greenhouse.resources.yield') }}</dt>
            <dd class="resources__value tabular" data-test="yield">{{ t('greenhouse.resources.perTask', { yield: yieldOf(greenhouse.yieldTenths) }) }}</dd>
            <dd class="resources__hint tabular" data-test="yield-formula">{{ yieldFormula }}</dd>
        </div>
        <div class="resources__tile">
            <dt class="resources__label"><Warehouse size="0.875rem" :stroke-width="2" aria-hidden="true" />{{ t('greenhouse.resources.pots') }}</dt>
            <dd class="resources__value tabular">{{ t('greenhouse.resources.potsUsed', { used: usedPots, total: greenhouse.pots.length }) }}</dd>
            <dd class="resources__hint">{{ t('greenhouse.resources.potsMax', { max: greenhouse.maxPots }) }}</dd>
        </div>
        <div class="resources__tile">
            <dt class="resources__label"><CloudRain size="0.875rem" :stroke-width="2" aria-hidden="true" />{{ t('greenhouse.resources.watering') }}</dt>
            <dd class="resources__value tabular" data-test="watering">{{ t('greenhouse.resources.multiplier', { n: greenhouse.wateringMultiplier }) }}</dd>
            <dd class="resources__hint">{{ t('greenhouse.resources.wateringHint', { level: rainBarrel, n: greenhouse.wateringMultiplier, half: halfMultiplier }) }}</dd>
        </div>
    </dl>
</template>

<style scoped>
.resources { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-3); margin: 0; }
.resources__tile { display: flex; flex-direction: column; gap: var(--space-1); min-width: 0; padding: var(--space-3) var(--space-4); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.resources__tile dd { margin: 0; }
.resources__tile--dew { border-color: color-mix(in oklch, var(--color-dew) 40%, var(--color-border)); }
.resources__label { display: inline-flex; align-items: center; gap: var(--space-1); color: var(--color-muted); font-size: var(--font-size-sm); }
.resources__label svg { color: var(--color-dew); }
.resources__value { color: var(--color-ink); font-size: 1.5rem; font-weight: 600; line-height: 1.15; overflow-wrap: anywhere; }
.resources__tile--dew .resources__value { color: color-mix(in oklch, var(--color-dew) 65%, var(--color-ink)); }
.resources__hint { color: var(--color-subtle); font-size: var(--font-size-xs); }

@media (max-width: 64rem) {
    .resources { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 30rem) {
    .resources { gap: var(--space-2); }
    .resources__tile { padding: var(--space-3); }
    .resources__value { font-size: 1.25rem; }
}
</style>
