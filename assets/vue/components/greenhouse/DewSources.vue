<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDew } from '../../composables/useDew.js';
import { bonusBreakdown } from '../../greenhouse/formulas.js';

const props = defineProps({
    sources: { type: Array, required: true },
    yieldTenths: { type: Number, required: true },
    wateringMultiplier: { type: Number, required: true },
});

const { t } = useI18n();
const { number, decimal, precise, yieldOf } = useDew();

function extra(source) {
    if (source.quadrant === 'schedule') return t('greenhouse.sources.watering', { n: number(source.watering) });
    if (source.quadrant === 'do_first') return t('greenhouse.sources.halfWatering', { n: number(source.watering) });
    if (source.quadrant === 'delegate') return t('greenhouse.sources.mist', { n: number(source.mist) });
    return t('greenhouse.sources.none');
}

function formula(source) {
    const bonus = bonusBreakdown(source.quadrant, props.yieldTenths, props.wateringMultiplier);
    if (!bonus) return null;
    if (!props.yieldTenths) return t('greenhouse.sources.noYield');

    const values = { yield: decimal(bonus.yield), multiplier: bonus.multiplier, exact: precise(bonus.exact), n: number(source.watering + source.mist) };
    const key = bonus.halved ? 'greenhouse.sources.halfFormula' : 'greenhouse.sources.formula';

    return t(bonus.rounded ? `${key}Rounded` : key, values);
}

const rows = computed(() => props.sources.map((source) => {
    const key = source.quadrant ?? 'unsorted';
    return {
        key,
        name: source.quadrant ? t(`matrix.quadrants.${source.quadrant}.name`) : t('matrix.unsorted'),
        base: number(source.base),
        extra: extra(source),
        formula: formula(source),
        total: number(source.amount),
        sum: t('greenhouse.sources.sum', { base: number(source.base), bonus: number(source.watering + source.mist) }),
        color: `var(--quadrant-${key.replace('_', '-')})`,
    };
}));
</script>

<template>
    <div class="sources">
        <table class="sources__table">
            <thead>
                <tr>
                    <th scope="col">{{ t('greenhouse.sources.task') }}</th>
                    <th scope="col" class="sources__numeric">{{ t('greenhouse.sources.base') }}</th>
                    <th scope="col">{{ t('greenhouse.sources.extra') }}</th>
                    <th scope="col" class="sources__numeric">{{ t('greenhouse.sources.total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.key" :class="['sources__row', { 'sources__row--best': row.key === 'schedule' }]" :data-test="`dew-source-${row.key}`">
                    <th scope="row" class="sources__name"><span class="sources__dot" :style="{ background: row.color }" aria-hidden="true" />{{ row.name }}</th>
                    <td class="sources__numeric tabular">{{ row.base }}</td>
                    <td class="sources__extra">
                        {{ row.extra }}
                        <span v-if="row.formula" class="sources__formula tabular">{{ row.formula }}</span>
                    </td>
                    <td class="sources__numeric tabular">
                        <span class="sources__total">{{ row.total }}</span>
                        <span v-if="row.formula" class="sources__formula sources__sum">{{ row.sum }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="sources__note">{{ t('greenhouse.sources.note', { yield: yieldOf(yieldTenths), multiplier: wateringMultiplier }) }}</p>
    </div>
</template>

<style scoped>
.sources { display: flex; flex-direction: column; gap: var(--space-2); }
.sources__table { width: 100%; border: var(--border-width) solid var(--color-border); border-radius: var(--radius); border-collapse: separate; border-spacing: 0; background: var(--color-surface); font-size: var(--font-size-md); }
.sources__table th, .sources__table td { padding: var(--space-2) var(--space-3); border-bottom: var(--border-width) solid var(--color-border); text-align: left; }
.sources__table tbody tr:last-child > * { border-bottom: none; }
.sources__table thead th { color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; white-space: nowrap; }
.sources__numeric { text-align: right !important; }
.sources__name { color: var(--color-ink); font-weight: 500; white-space: nowrap; }
.sources__dot { display: inline-block; width: 0.625rem; height: 0.625rem; margin-right: var(--space-2); border-radius: 50%; }
.sources__extra { color: var(--color-muted); }
.sources__formula { display: block; color: var(--color-subtle); font-size: var(--font-size-xs); }
.sources__sum { white-space: nowrap; }
.sources__total { color: color-mix(in oklch, var(--color-dew) 65%, var(--color-ink)); font-weight: 600; }
.sources__row--best > * { background: var(--color-accent-soft); }
.sources__row--best .sources__name { font-weight: 700; }
.sources__note { color: var(--color-subtle); font-size: var(--font-size-xs); }

@media (max-width: 30rem) {
    .sources__table th, .sources__table td { padding-inline: var(--space-2); }
}
</style>
