<script setup>
import { nextTick, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { VueDraggable } from 'vue-draggable-plus';
import { Plus } from '../icons.js';
import { useI18n } from 'vue-i18n';
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import ProjectEditor from '../components/projects/ProjectEditor.vue';
import { useProjects } from '../composables/useProjects.js';
import { moveBy } from '../projects/moveBy.js';

const { t } = useI18n();
const { projects, reorder } = useProjects();
const creating = ref(false);

const ids = (list) => list.map((project) => project.id);

async function move(index, offset, link) {
    const moved = moveBy(projects.value, index, offset);
    if (!moved) return;
    reorder(ids(moved));
    await nextTick();
    link.focus();
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('projects.title')" :subtitle="projects.length ? t('projects.subtitle') : null">
            <BaseButton @click="creating = true"><Plus size="1rem" aria-hidden="true" />{{ t('projects.new') }}</BaseButton>
        </PageHeader>
        <EmptyState v-if="!projects.length" :title="t('projects.none_yet.title')" :hint="t('projects.none_yet.hint')" />
        <div v-else class="projects">
            <VueDraggable v-model="projects" :animation="160" :delay="180" :delay-on-touch-only="true" ghost-class="projects__ghost" tag="ul" class="projects__list" @update="reorder(ids(projects))">
                <li v-for="(project, index) in projects" :key="project.id">
                    <RouterLink
                        :to="`/projects/${project.id}`"
                        class="projects__link"
                        :style="{ '--project': `var(--project-${project.color})` }"
                        aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
                        @keydown.alt.up.prevent="move(index, -1, $event.currentTarget)"
                        @keydown.alt.down.prevent="move(index, 1, $event.currentTarget)"
                    >
                        <span class="projects__name">{{ project.name }}</span>
                        <span class="projects__count tabular">{{ project.openTasks }}</span>
                    </RouterLink>
                </li>
            </VueDraggable>
            <ul class="projects__list">
                <li>
                    <RouterLink to="/inbox" class="projects__link projects__link--inbox">
                        <span class="projects__name">{{ t('nav.inbox') }}</span>
                    </RouterLink>
                </li>
            </ul>
        </div>
        <ProjectEditor v-model:open="creating" @saved="(project) => project && $router.push(`/projects/${project.id}`)" />
    </div>
</template>

<style scoped>
.projects, .projects__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.projects__link { --project: var(--color-border-strong); display: flex; align-items: center; gap: var(--space-3); min-height: 3.5rem; padding: var(--space-2) var(--space-4); border-radius: var(--radius); background: var(--color-surface); color: var(--color-text); text-decoration: none; }
.projects__link::before { content: ""; width: 0.75rem; height: 0.75rem; border-radius: 50%; background: var(--project); }
.projects__link:hover { background: var(--color-surface); }
.projects__name { flex: 1; font-weight: 700; }
.projects__count { color: var(--color-muted); }
:deep(.projects__ghost) { opacity: 0.4; }
</style>
