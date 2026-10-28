<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import ProjectFilter from '../components/matrix/ProjectFilter.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import TaskRow from '../components/tasks/TaskRow.vue';
import { useApi } from '../composables/useApi.js';
import { useSelection } from '../composables/useSelection.js';
import { useSubtaskQuestion } from '../composables/useSubtaskQuestion.js';
import { useTaskActions } from '../composables/useTaskActions.js';
import { sortTasks } from '../tasks/compareTasks.js';
import { formatSelection, matchesSelection, mergeOrder, parseSelection } from '../tasks/projectFilter.js';
import { QUADRANTS } from '../tasks/quadrants.js';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const actions = useTaskActions();
const question = useSubtaskQuestion();
const tasks = ref(null);
useApi().load('/api/matrix', tasks);

const zones = ref({});
const unsorted = ref([]);

const selection = computed({
    get: () => parseSelection(route.query.projects),
    set: (value) => router.replace({ query: { ...route.query, projects: formatSelection(value) } }),
});

const inQuadrant = (quadrant) => sortTasks((tasks.value ?? []).filter((task) => task.quadrant === quadrant));

function layout() {
    if (!tasks.value) return;
    const shown = (quadrant) => inQuadrant(quadrant).filter((task) => matchesSelection(task, selection.value));
    zones.value = Object.fromEntries(QUADRANTS.map((quadrant) => [quadrant.value, shown(quadrant.value)]));
    unsorted.value = shown(null);
}

watch([tasks, selection], layout, { immediate: true });

const dropped = (event) => tasks.value.find((candidate) => candidate.id === event.item.dataset.task);

async function arranged(quadrant, event = null) {
    const task = event && dropped(event);
    const withSubtasks = task ? await question.ask([task], quadrant) : false;
    if (withSubtasks === null) {
        layout();
        return;
    }
    const all = inQuadrant(quadrant).map((each) => each.id);
    actions.reorder(quadrant, mergeOrder(all, zones.value[quadrant].map((each) => each.id)), withSubtasks);
}

async function unclassified(event) {
    const task = dropped(event);
    if (task && await actions.classify(task, null) === null) layout();
}

const bulk = useSelection();
const draggable = computed(() => ({ group: 'matrix', animation: 160, delay: 180, delayOnTouchOnly: true, ghostClass: 'matrix__ghost', disabled: bulk.count.value > 0 }));
</script>

<template>
    <div class="page page--wide">
        <PageHeader :title="t('matrix.title')" :subtitle="t('matrix.subtitle')" />
        <ProjectFilter v-model="selection" />
        <div v-if="tasks" class="matrix">
            <p class="matrix__axis matrix__axis--top" aria-hidden="true"><span>{{ t('matrix.urgent') }}</span><span>{{ t('matrix.notUrgent') }}</span></p>
            <p class="matrix__axis matrix__axis--side" aria-hidden="true"><span>{{ t('matrix.important') }}</span><span>{{ t('matrix.notImportant') }}</span></p>
            <div class="matrix__board">
                <section v-for="quadrant in QUADRANTS" :key="quadrant.value" :class="['matrix__zone', `matrix__zone--${quadrant.value}`]">
                    <header class="matrix__header">
                        <h2 class="matrix__name">{{ t(`matrix.quadrants.${quadrant.value}.name`) }} <span class="matrix__count">{{ zones[quadrant.value].length }}</span></h2>
                        <p class="matrix__plain">{{ t(`matrix.quadrants.${quadrant.value}.plain`) }} · {{ t(`matrix.quadrants.${quadrant.value}.advice`) }}</p>
                    </header>
                    <VueDraggable v-model="zones[quadrant.value]" v-bind="draggable" tag="ul" class="matrix__tasks" @update="arranged(quadrant.value)" @add="(event) => arranged(quadrant.value, event)">
                        <TaskRow v-for="task in zones[quadrant.value]" :key="task.id" :task="task" compact />
                    </VueDraggable>
                </section>
            </div>
            <section class="matrix__tray">
                <header class="matrix__header">
                    <h2>{{ t('matrix.unsorted') }} <span class="matrix__count">{{ unsorted.length }}</span></h2>
                    <p class="matrix__plain">{{ t('matrix.unsortedHint') }}</p>
                </header>
                <VueDraggable v-model="unsorted" v-bind="draggable" tag="ul" class="matrix__tasks matrix__tasks--tray" @add="unclassified">
                    <TaskRow v-for="task in unsorted" :key="task.id" :task="task" compact />
                </VueDraggable>
            </section>
        </div>
    </div>
</template>

<style scoped>
.matrix { display: grid; grid-template-columns: auto minmax(0, 1fr); grid-template-areas: ". top" "side board" ". tray"; gap: var(--space-2) var(--space-3); }
.matrix__axis { display: flex; color: var(--color-subtle); font-size: var(--font-size-2xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.matrix__axis span { flex: 1; text-align: center; }
.matrix__axis--top { grid-area: top; }
.matrix__axis--side { grid-area: side; flex-direction: row-reverse; writing-mode: vertical-rl; transform: rotate(180deg); }
.matrix__axis--side span { display: flex; align-items: center; justify-content: center; }
.matrix__board { grid-area: board; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: repeat(2, minmax(0, 1fr)); gap: var(--space-3); height: max(30rem, calc(100dvh - 16rem)); }
.matrix__zone { --tone: var(--quadrant-unsorted); display: flex; flex-direction: column; gap: var(--space-3); min-height: 0; overflow: hidden; padding: var(--space-4) var(--space-3) var(--space-3) var(--space-4); border: var(--border-width) solid var(--color-border); border-top: 0.1875rem solid var(--tone); border-radius: var(--radius); background: color-mix(in oklch, var(--tone) 5%, var(--color-surface)); }
.matrix__zone--do_first { --tone: var(--quadrant-do-first); }
.matrix__zone--schedule { --tone: var(--quadrant-schedule); }
.matrix__zone--delegate { --tone: var(--quadrant-delegate); }
.matrix__zone--eliminate { --tone: var(--quadrant-eliminate); }
.matrix__name { color: var(--color-ink); font-family: var(--font-display); font-size: 1.375rem; font-weight: var(--font-display-weight); }
.matrix__count { color: var(--color-subtle); font-family: var(--font-body); font-size: var(--font-size-sm); font-variant-numeric: tabular-nums; }
.matrix__plain { color: var(--color-muted); font-size: var(--font-size-sm); }
.matrix__header { flex-shrink: 0; padding-right: var(--space-1); }
.matrix__tasks { display: flex; flex: 1; flex-direction: column; gap: var(--space-2); min-height: 4rem; margin: 0; padding: 0.125rem var(--space-1) 0.125rem 0.125rem; overflow-y: auto; overscroll-behavior: contain; list-style: none; }
.matrix__tasks :deep(.task-row) { flex-shrink: 0; }
.matrix__tray { grid-area: tray; display: flex; flex-direction: column; gap: var(--space-3); max-height: 24rem; margin-top: var(--space-3); padding: var(--space-4) var(--space-3) var(--space-3) var(--space-4); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius); }
.matrix__tasks--tray { display: grid; grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr)); align-content: start; }
:deep(.matrix__ghost) { opacity: 0.4; }

@media (max-width: 48rem) {
    .matrix { grid-template-columns: 1fr; grid-template-areas: "board" "tray"; }
    .matrix__axis { display: none; }
    .matrix__board { grid-template-columns: 1fr; grid-template-rows: none; grid-auto-rows: 22rem; height: auto; }
}
</style>
