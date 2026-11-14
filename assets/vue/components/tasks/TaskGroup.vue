<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import TaskRow from './TaskRow.vue';
import { PEEK, peek } from '../../tasks/overview.js';

const props = defineProps({
    group: { type: Object, required: true },
    folders: { type: Map, required: true },
});

const emit = defineEmits(['fold']);

const { t } = useI18n();

const name = computed(() => {
    if (props.group.id === null) return t('nav.inbox');
    return props.group.project?.name ?? t('allTasks.unknownProject');
});
const link = computed(() => (props.group.id === null ? '/inbox' : `/projects/${props.group.id}`));
const tone = computed(() => (props.group.project ? `var(--project-${props.group.project.color})` : null));
const caption = computed(() => [t('allTasks.tasks', props.group.tasks), ...(props.group.subtasks ? [t('allTasks.subtasks', props.group.subtasks)] : [])].join(' · '));
</script>

<template>
    <section class="task-group">
        <h2 class="task-group__header" :style="tone ? { '--project': tone } : undefined">
            <RouterLink :to="link" class="task-group__name">{{ name }}</RouterLink>
            <span class="task-group__count">{{ caption }}</span>
        </h2>
        <ul class="task-group__list">
            <template v-for="{ task, subtasks, nextDue } in group.entries" :key="task.id">
                <TaskRow :task="task" :show-project="false" :expanded="subtasks.length ? folders.has(task.id) : null" :next-due="nextDue" dense @fold="(open) => emit('fold', task.id, open ? 'peek' : null)" />
                <li v-if="subtasks.length && folders.has(task.id)" class="task-group__subtasks">
                    <ul class="task-group__list">
                        <TaskRow v-for="subtask in peek(subtasks, folders.get(task.id) === 'all')" :key="subtask.id" :task="subtask" :show-project="false" dense nested />
                    </ul>
                    <button v-if="subtasks.length > PEEK" type="button" class="task-group__more" @click="emit('fold', task.id, folders.get(task.id) === 'all' ? 'peek' : 'all')">
                        {{ folders.get(task.id) === 'all' ? t('allTasks.showFewer') : t('allTasks.showAll', { count: subtasks.length }) }}
                    </button>
                </li>
            </template>
        </ul>
    </section>
</template>

<style scoped>
.task-group { display: flex; flex-direction: column; gap: var(--space-1); }
.task-group__header { --project: var(--color-border-strong); display: flex; align-items: baseline; flex-wrap: wrap; gap: 0 var(--space-2); min-height: 2rem; padding: 0 var(--space-1); font-family: var(--font-body); font-size: var(--font-size); font-weight: 700; }
.task-group__name { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-ink); text-decoration: none; }
.task-group__name::before { content: ""; flex-shrink: 0; width: 0.625rem; height: 0.625rem; border-radius: 50%; background: var(--project); }
.task-group__name:hover { color: var(--color-accent-strong); }
.task-group__name:focus-visible { outline: none; border-radius: var(--radius-sm); box-shadow: var(--focus-ring); }
.task-group__count { color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 400; font-variant-numeric: tabular-nums; }
.task-group__list { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; padding: 0; list-style: none; }
.task-group__subtasks { display: flex; flex-direction: column; gap: var(--space-1); margin-left: var(--space-5); padding-left: var(--space-2); border-left: 0.125rem solid var(--color-border); }
.task-group__more { align-self: flex-start; min-height: 2rem; padding: var(--space-1) var(--space-3); border: none; border-radius: var(--radius-inner); background: none; color: var(--color-accent-strong); font-size: var(--font-size-sm); font-weight: 700; cursor: pointer; }
.task-group__more:hover { background: var(--color-hover); }
.task-group__more:focus-visible { outline: none; box-shadow: var(--focus-ring); }

@media (max-width: 30rem) {
    .task-group__subtasks { margin-left: var(--space-3); }
}
</style>
