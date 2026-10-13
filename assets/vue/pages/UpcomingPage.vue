<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import BaseButton from '../components/ui/BaseButton.vue';
import IconButton from '../components/ui/IconButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import TaskRow from '../components/tasks/TaskRow.vue';
import { useApi } from '../composables/useApi.js';
import { useDates } from '../composables/useDates.js';
import { useSelection } from '../composables/useSelection.js';
import { useTaskActions } from '../composables/useTaskActions.js';
import { sortTasks } from '../tasks/compareTasks.js';
import { addDays } from '../tasks/days.js';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const api = useApi();
const dates = useDates();
const actions = useTaskActions();

const view = ref(null);
const columns = ref([]);

const from = computed(() => (typeof route.query.from === 'string' ? route.query.from : null));

watch(from, (day) => {
    api.load(day ? `/api/tasks/upcoming?from=${day}` : '/api/tasks/upcoming', view);
}, { immediate: true });

watch(view, (value) => {
    if (!value) return;
    columns.value = value.days.map((day) => ({ day, tasks: sortTasks(value.tasks.filter((task) => task.plannedOn === day)) }));
}, { immediate: true });

function shift(days) {
    const start = addDays(view.value.days[0], days);
    router.replace({ query: start === view.value.today ? {} : { from: start } });
}

function dropped(event, day) {
    const task = view.value.tasks.find((candidate) => candidate.id === event.item.dataset.task);
    if (task) actions.plan(task, 'date', day);
}

function heading(day) {
    const relative = dates.relative(day);
    return relative === 'today' || relative === 'tomorrow' ? t(`tasks.days.${relative}`) : dates.weekday(day);
}
const selection = useSelection();
</script>

<template>
    <div class="page">
        <PageHeader :title="t('upcoming.title')" :subtitle="t('upcoming.subtitle')">
            <IconButton :icon="ChevronLeft" :label="t('upcoming.previous')" :disabled="!view" @click="shift(-7)" />
            <BaseButton v-if="from" variant="ghost" @click="router.replace({ query: {} })">{{ t('upcoming.thisWeek') }}</BaseButton>
            <IconButton :icon="ChevronRight" :label="t('upcoming.next')" :disabled="!view" @click="shift(7)" />
        </PageHeader>
        <div v-if="view" class="upcoming">
            <section v-for="column in columns" :key="column.day" :class="['upcoming__day', { 'upcoming__day--today': column.day === view.today }]">
                <header class="upcoming__header">
                    <h2 class="upcoming__weekday">{{ heading(column.day) }}</h2>
                    <span class="upcoming__date">{{ dates.short(column.day) }}</span>
                </header>
                <div class="upcoming__body">
                <VueDraggable
                    v-model="column.tasks"
                    tag="ul"
                    class="upcoming__tasks"
                    group="upcoming"
                    :animation="160"
                    :delay="180"
                    :delay-on-touch-only="true"
                    :disabled="selection.count.value > 0"
                    ghost-class="upcoming__ghost"
                    @add="(event) => dropped(event, column.day)"
                >
                    <TaskRow v-for="task in column.tasks" :key="task.id" :task="task" :show-planned="false" />
                </VueDraggable>
                <p v-if="!column.tasks.length" class="upcoming__free">{{ t('upcoming.free') }}</p>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.upcoming { display: flex; flex-direction: column; gap: var(--space-2); }
.upcoming__day { display: grid; grid-template-columns: 9rem minmax(0, 1fr); align-items: start; gap: var(--space-4); padding: var(--space-3); border-radius: var(--radius); }
.upcoming__day--today { background: var(--color-accent-soft); box-shadow: inset 0.125rem 0 0 var(--color-accent); }
.upcoming__header { display: flex; flex-direction: column; padding-top: var(--space-2); }
.upcoming__weekday { color: var(--color-ink); font-family: var(--font-display); font-size: 1.25rem; font-weight: 400; text-transform: capitalize; }
.upcoming__day--today .upcoming__weekday { color: var(--color-accent-strong); }
.upcoming__date { color: var(--color-subtle); font-size: var(--font-size-sm); }
.upcoming__body { position: relative; min-width: 0; }
.upcoming__tasks { display: flex; flex-direction: column; gap: var(--space-2); min-height: 3.5rem; margin: 0; padding: 0; list-style: none; border-radius: var(--radius); }
.upcoming__free { position: absolute; inset: 0; display: flex; align-items: center; padding-left: var(--space-4); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius); color: var(--color-subtle); font-size: var(--font-size-sm); pointer-events: none; }
:deep(.upcoming__ghost) { opacity: 0.4; }

@media (max-width: 40rem) {
    .upcoming__day { grid-template-columns: 1fr; gap: var(--space-2); padding-inline: 0; }
    .upcoming__day--today { padding-inline: var(--space-3); }
    .upcoming__header { flex-direction: row; align-items: baseline; gap: var(--space-2); padding-top: 0; }
}
</style>
