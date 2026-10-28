<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Pencil, Trash2 } from '../icons.js';
import { useI18n } from 'vue-i18n';
import ConfirmButton from '../components/ui/ConfirmButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import IconButton from '../components/ui/IconButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import ProjectEditor from '../components/projects/ProjectEditor.vue';
import TaskComposer from '../components/tasks/TaskComposer.vue';
import TaskList from '../components/tasks/TaskList.vue';
import { useApi } from '../composables/useApi.js';
import { useProjects } from '../composables/useProjects.js';
import { useToast } from '../composables/useToast.js';

const props = defineProps({
    id: { type: String, required: true },
});

const { t } = useI18n();
const router = useRouter();
const api = useApi();
const toast = useToast();
const projects = useProjects();
const tasks = ref(null);
const editing = ref(false);

watch(() => props.id, (id) => {
    tasks.value = null;
    api.load(`/api/projects/${id}/tasks`, tasks).catch((error) => toast.error(error.message));
}, { immediate: true });

const project = computed(() => projects.byId.value.get(props.id));
const open = computed(() => (tasks.value ?? []).filter((task) => !task.done));
const done = computed(() => (tasks.value ?? []).filter((task) => task.done));

async function remove() {
    try {
        await projects.remove(props.id);
        toast.success(t('projects.deleted', { name: project.value?.name }));
        router.push('/');
    } catch (error) {
        toast.error(error.message);
    }
}
</script>

<template>
    <div v-if="project" class="page">
        <PageHeader :title="project.name" :subtitle="t('projects.left', open.length)" :tone="`var(--project-${project.color})`">
            <IconButton :icon="Pencil" :label="t('projects.edit')" @click="editing = true" />
            <ConfirmButton :icon="Trash2" :label="t('projects.delete')" :message="t('projects.deleteMessage', { name: project.name })" @confirm="remove" />
        </PageHeader>
        <TaskComposer :defaults="{ projectId: id }" :placeholder="t('projects.composer', { name: project.name })" />
        <template v-if="tasks">
            <EmptyState v-if="!tasks.length" :title="t('projects.empty.title')" :hint="t('projects.empty.hint')" />
            <TaskList v-if="open.length" :tasks="open" :show-project="false" />
            <PageSection v-if="done.length" :title="t('projects.done')" :count="done.length">
                <TaskList :tasks="done" :show-project="false" :show-planned="false" />
            </PageSection>
        </template>
        <ProjectEditor v-model:open="editing" :project="project" />
    </div>
</template>
