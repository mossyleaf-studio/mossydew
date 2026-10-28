<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DailyChart from '../components/stats/DailyChart.vue';
import OnTimeMeter from '../components/stats/OnTimeMeter.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import ProjectCounts from '../components/stats/ProjectCounts.vue';
import QuadrantSplit from '../components/stats/QuadrantSplit.vue';
import StatTile from '../components/stats/StatTile.vue';
import { useApi } from '../composables/useApi.js';
import { intlLocale } from '../i18n/locale.js';

const { t } = useI18n();
const statistics = ref(null);
useApi().load('/api/stats', statistics);

const number = new Intl.NumberFormat(intlLocale());

const doneRecently = computed(() => statistics.value.perDay.reduce((sum, day) => sum + day.count, 0));

const tiles = computed(() => {
    const { totals, streak, bestStreak } = statistics.value;
    return [
        { key: 'completed', label: t('stats.totals.completed'), value: number.format(totals.completed), hint: t('stats.totals.allTime') },
        { key: 'recent', label: t('stats.totals.recent'), value: number.format(doneRecently.value) },
        { key: 'month', label: t('stats.totals.thisMonth'), value: number.format(totals.completedThisMonth) },
        { key: 'week', label: t('stats.totals.thisWeek'), value: number.format(totals.completedThisWeek) },
        { key: 'open', label: t('stats.totals.open'), value: number.format(totals.open) },
        { key: 'overdue', label: t('stats.totals.overdue'), value: number.format(totals.overdue), tone: totals.overdue ? 'alert' : null },
        { key: 'streak', label: t('stats.totals.streak'), value: t('stats.days', streak) },
        { key: 'best', label: t('stats.totals.bestStreak'), value: t('stats.days', bestStreak) },
    ];
});
</script>

<template>
    <div class="page page--wide">
        <PageHeader :title="t('stats.title')" :subtitle="t('stats.subtitle')" />
        <template v-if="statistics">
            <dl class="stats__tiles">
                <StatTile v-for="tile in tiles" :key="tile.key" :label="tile.label" :value="tile.value" :hint="tile.hint" :tone="tile.tone" />
            </dl>
            <PageSection :title="t('stats.perDay.title')">
                <div class="stats__card"><DailyChart :days="statistics.perDay" :today="statistics.today" /></div>
            </PageSection>
            <div class="stats__columns">
                <PageSection :title="t('stats.byQuadrant.title')">
                    <div class="stats__card"><QuadrantSplit :counts="statistics.byQuadrant" /></div>
                </PageSection>
                <PageSection :title="t('stats.onTime.title')">
                    <div class="stats__card"><OnTimeMeter :on-time="statistics.onTime" /></div>
                </PageSection>
                <PageSection :title="t('stats.byProject.title')" class="stats__projects">
                    <div class="stats__card">
                        <ProjectCounts v-if="statistics.byProject.length" :projects="statistics.byProject" />
                        <p v-else class="stats__empty">{{ t('stats.byProject.empty') }}</p>
                    </div>
                </PageSection>
            </div>
        </template>
    </div>
</template>

<style scoped>
.stats__tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-3); margin: 0; }
.stats__card { padding: var(--space-4) var(--space-5); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.stats__columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: var(--space-5); }
.stats__projects { grid-column: 1 / -1; }
.stats__empty { color: var(--color-muted); font-size: var(--font-size-md); }

@media (max-width: 40rem) {
    .stats__tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .stats__columns { grid-template-columns: minmax(0, 1fr); }
    .stats__card { padding: var(--space-4); }
}
</style>
