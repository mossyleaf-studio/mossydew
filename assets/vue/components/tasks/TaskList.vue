<script setup>
import { computed } from 'vue';
import { Plus } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import SubtaskComposer from './SubtaskComposer.vue';
import TaskRow from './TaskRow.vue';
import { useCollapsed } from '../../composables/useCollapsed.js';
import { useSubtaskComposer } from '../../composables/useSubtaskComposer.js';
import { sortTasks } from '../../tasks/compareTasks.js';
import { nestSubtasks } from '../../tasks/subtasks.js';

const props = defineProps({
    tasks: { type: Array, required: true },
    sorted: { type: Boolean, default: true },
    showProject: { type: Boolean, default: true },
    showPlanned: { type: Boolean, default: true },
    nestable: { type: Boolean, default: true },
});

const { t } = useI18n();
const composer = useSubtaskComposer();
const { isCollapsed } = useCollapsed();
const nested = computed(() => nestSubtasks(props.sorted ? sortTasks(props.tasks) : props.tasks));
const composing = (task) => props.nestable && composer.state.parentId === task.id;
const items = computed(() => nested.value.flatMap(({ task, subtasks }) => [
    { key: `${task.id}:row`, task, collapsible: subtasks.length > 0 },
    ...((subtasks.length && !isCollapsed(task)) || composing(task) ? [{ key: `${task.id}:subtasks`, task, subtasks }] : []),
]));
</script>

<template>
    <TransitionGroup tag="ul" name="task-list" class="task-list">
        <template v-for="{ key, task, subtasks, collapsible } in items" :key="key">
            <li v-if="subtasks" class="task-list__subtasks">
                <ul v-if="subtasks.length" class="task-list">
                    <TaskRow v-for="subtask in subtasks" :key="subtask.id" :task="subtask" :show-project="false" :show-planned="showPlanned" :nestable="nestable" nested />
                </ul>
                <SubtaskComposer v-if="composing(task)" :parent="task" />
                <button v-else-if="nestable && !task.done" type="button" class="task-list__more" @click="composer.open(task)">
                    <Plus size="1rem" aria-hidden="true" />{{ t('tasks.subtasks.more') }}
                </button>
            </li>
            <TaskRow v-else :task="task" :show-project="showProject" :show-planned="showPlanned" :nestable="nestable" :collapsible="collapsible" />
        </template>
    </TransitionGroup>
</template>

<style scoped>
.task-list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.task-list__subtasks { display: flex; flex-direction: column; gap: var(--space-2); margin-left: var(--space-6); padding-left: var(--space-3); border-left: 0.125rem solid var(--color-border); }
.task-list__more { display: inline-flex; align-items: center; align-self: flex-start; gap: var(--space-2); padding: var(--space-1) var(--space-2); border: none; border-radius: var(--radius); background: none; color: var(--color-muted); font-size: var(--font-size-sm); cursor: pointer; }
.task-list__more:hover { background: var(--color-hover); color: var(--color-accent-strong); }
.task-list__more:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.task-list-move { transition: transform 260ms ease; }
.task-list-enter-from { opacity: 0; transform: translateY(-0.25rem); }
.task-list-enter-active { transition: opacity 200ms ease, transform 200ms ease; }
.task-list-leave-active { display: none; }

@media (max-width: 30rem) {
    .task-list__subtasks { margin-left: var(--space-3); padding-left: var(--space-2); }
}
</style>
