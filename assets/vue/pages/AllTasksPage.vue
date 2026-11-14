<script setup>
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import TaskGroup from '../components/tasks/TaskGroup.vue';
import { useApi } from '../composables/useApi.js';
import { useProjects } from '../composables/useProjects.js';
import { overview, splitColumns } from '../tasks/overview.js';

const { t } = useI18n();
const { projects } = useProjects();
const tasks = ref(null);
useApi().load('/api/matrix', tasks);

const folders = reactive(new Map());
const view = computed(() => overview(tasks.value ?? [], projects.value));
const columns = computed(() => splitColumns(view.value.groups));
const subtitle = computed(() => {
    const { tasks: count, subtasks } = view.value;
    if (!subtasks) return t('allTasks.summaryFlat', count);
    return t('allTasks.summary', { total: count + subtasks, tasks: t('allTasks.tasks', count), subtasks: t('allTasks.subtasks', subtasks) });
});

function fold(id, mode) {
    if (mode) folders.set(id, mode);
    else folders.delete(id);
}
</script>

<template>
    <div class="page page--wide">
        <PageHeader :title="t('allTasks.title')" :subtitle="tasks ? subtitle : null" />
        <template v-if="tasks">
            <EmptyState v-if="!view.groups.length" :title="t('allTasks.empty.title')" :hint="t('allTasks.empty.hint')" />
            <div v-else class="all-tasks">
                <div v-for="(column, index) in columns" :key="index" class="all-tasks__column">
                    <TaskGroup v-for="group in column" :key="group.id ?? 'inbox'" :group="group" :folders="folders" @fold="fold" />
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.all-tasks { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: var(--space-5); }
.all-tasks__column { display: flex; flex-direction: column; gap: var(--space-5); min-width: 0; }

@media (max-width: 64rem) {
    .all-tasks { grid-template-columns: minmax(0, 1fr); }
}
</style>
