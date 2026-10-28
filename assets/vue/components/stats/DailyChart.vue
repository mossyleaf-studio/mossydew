<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDates } from '../../composables/useDates.js';
import { axis, busiest, percent } from '../../stats/scale.js';

const props = defineProps({
    days: { type: Array, required: true },
    today: { type: String, required: true },
});

const { t } = useI18n();
const dates = useDates();

const scale = computed(() => axis(props.days.map((day) => day.count)));
const total = computed(() => props.days.reduce((sum, day) => sum + day.count, 0));
const summary = computed(() => {
    const peak = busiest(props.days);
    if (!peak) return t('stats.perDay.summaryEmpty', { days: props.days.length });
    return t('stats.perDay.summary', { total: total.value, days: props.days.length, day: dates.long(peak.date), count: peak.count }, total.value);
});

function label(day) {
    return day.date === props.today ? t('tasks.days.today') : dates.short(day.date);
}
</script>

<template>
    <figure class="daily-chart">
        <div class="daily-chart__plot" role="img" :aria-label="summary">
            <div class="daily-chart__grid" aria-hidden="true">
                <span v-for="tick in scale.ticks" :key="tick" class="daily-chart__tick" :style="{ bottom: `${percent(tick, scale.max)}%` }">
                    <span class="daily-chart__tick-label tabular">{{ tick }}</span>
                </span>
            </div>
            <div class="daily-chart__bars">
                <div v-for="day in days" :key="day.date" :class="['daily-chart__column', { 'daily-chart__column--today': day.date === today }]">
                    <span v-if="day.count" class="daily-chart__bar" :style="{ height: `${percent(day.count, scale.max)}%` }" />
                    <span class="daily-chart__tip" aria-hidden="true">{{ t('stats.perDay.tip', { day: label(day) }) }} <strong class="tabular">{{ day.count }}</strong></span>
                </div>
            </div>
        </div>
        <div class="daily-chart__axis" aria-hidden="true">
            <span>{{ dates.short(days[0].date) }}</span>
            <span class="daily-chart__axis-today">{{ t('tasks.days.today') }}</span>
        </div>
        <table class="visually-hidden">
            <caption>{{ t('stats.perDay.title') }}</caption>
            <thead><tr><th scope="col">{{ t('stats.perDay.day') }}</th><th scope="col">{{ t('stats.perDay.count') }}</th></tr></thead>
            <tbody>
                <tr v-for="day in days" :key="day.date"><th scope="row">{{ dates.long(day.date) }}</th><td>{{ day.count }}</td></tr>
            </tbody>
        </table>
    </figure>
</template>

<style scoped>
.daily-chart { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; }
.daily-chart__plot { position: relative; height: 10rem; margin-left: 1.75rem; }
.daily-chart__grid { position: absolute; inset: 0; }
.daily-chart__tick { position: absolute; right: 0; left: 0; border-top: var(--border-width) solid var(--color-border); }
.daily-chart__tick-label { position: absolute; right: calc(100% + var(--space-2)); color: var(--color-subtle); font-size: var(--font-size-xs); line-height: 1; transform: translateY(-50%); }
.daily-chart__bars { position: absolute; inset: 0; display: grid; grid-template-columns: repeat(30, minmax(0, 1fr)); gap: 0.125rem; }
.daily-chart__column { position: relative; display: flex; align-items: flex-end; justify-content: center; }
.daily-chart__column:hover { background: var(--color-hover); }
.daily-chart__bar { width: 100%; max-width: 1.5rem; border-radius: var(--radius-sm) var(--radius-sm) 0 0; background: color-mix(in oklch, var(--color-accent) 55%, var(--color-surface)); }
.daily-chart__column--today .daily-chart__bar { background: var(--color-accent); }
.daily-chart__column--today { box-shadow: inset 0 -0.125rem 0 var(--color-accent); }

.daily-chart__tip { position: absolute; top: 0; z-index: 2; display: none; padding: var(--space-1) var(--space-2); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); box-shadow: var(--shadow); color: var(--color-muted); font-size: var(--font-size-xs); white-space: nowrap; pointer-events: none; }
.daily-chart__tip strong { color: var(--color-ink); }
.daily-chart__column:hover .daily-chart__tip { display: block; }
.daily-chart__column:nth-child(-n + 5) .daily-chart__tip { left: 0; }
.daily-chart__column:nth-last-child(-n + 5) .daily-chart__tip { right: 0; }

.daily-chart__axis { display: flex; justify-content: space-between; margin-left: 1.75rem; color: var(--color-subtle); font-size: var(--font-size-xs); }
.daily-chart__axis-today { color: var(--color-accent-strong); font-weight: 600; }
</style>
