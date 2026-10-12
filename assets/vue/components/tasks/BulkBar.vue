<script setup>
import { CalendarDays, Check, FolderInput, Grid2x2, Trash2, X } from '@lucide/vue';
import {
    DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger,
} from 'reka-ui';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import ConfirmButton from '../ui/ConfirmButton.vue';
import { useApi } from '../../composables/useApi.js';
import { useProjects } from '../../composables/useProjects.js';
import { useSelection } from '../../composables/useSelection.js';
import { useTaskActions } from '../../composables/useTaskActions.js';
import { useToast } from '../../composables/useToast.js';
import { movePayload, runEach, subtasksFirst } from '../../tasks/bulk.js';
import { QUADRANTS } from '../../tasks/quadrants.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const actions = useTaskActions();
const selection = useSelection();
const { projects } = useProjects();

const PLANS = ['today', 'tomorrow', 'next_week', 'none'];

async function run(action, tasks = selection.tasks.value) {
    const { done, failed, error } = await runEach(tasks, action);
    selection.clear();
    if (failed) toast.error(t('tasks.bulk.partial', { done, failed, error }));
    else toast.success(t('tasks.bulk.done', { count: done }, done));
}

const complete = () => run(async (task) => {
    if (await actions.complete(task) === null) throw new Error(t('tasks.bulk.refused'));
}, subtasksFirst(selection.tasks.value));
const plan = (when) => run((task) => api.post(`/api/tasks/${task.id}/plan`, { when, date: null }));
const classify = (quadrant) => run((task) => api.post(`/api/tasks/${task.id}/classify`, { quadrant }));
const move = (projectId) => run((task) => api.patch(`/api/tasks/${task.id}`, movePayload(task, projectId)), selection.tasks.value.filter((task) => !task.parentId));
const remove = () => run((task) => api.del(`/api/tasks/${task.id}`));
</script>

<template>
    <Transition name="bulk-bar">
        <div v-if="selection.count.value" class="bulk-bar" role="toolbar" :aria-label="t('tasks.bulk.label')">
            <span class="bulk-bar__count tabular" aria-live="polite">{{ t('tasks.bulk.selected', { count: selection.count.value }, selection.count.value) }}</span>
            <BaseButton variant="secondary" @click="complete"><Check size="1rem" aria-hidden="true" />{{ t('tasks.bulk.complete') }}</BaseButton>
            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child><BaseButton variant="secondary"><CalendarDays size="1rem" aria-hidden="true" />{{ t('tasks.plan.open') }}</BaseButton></DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent class="popover" :side-offset="6" side="top">
                        <DropdownMenuItem v-for="when in PLANS" :key="when" class="popover__item" @select="plan(when)">{{ t(`tasks.plan.${when}`) }}</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child><BaseButton variant="secondary"><Grid2x2 size="1rem" aria-hidden="true" />{{ t('matrix.title') }}</BaseButton></DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent class="popover task-menu" :side-offset="6" side="top">
                        <DropdownMenuItem v-for="known in QUADRANTS" :key="known.value" class="popover__item" @select="classify(known.value)">
                            <span :class="['task-menu__dot', `task-menu__dot--${known.value}`]" aria-hidden="true" />{{ t(`matrix.quadrants.${known.value}.name`) }}
                        </DropdownMenuItem>
                        <DropdownMenuItem class="popover__item" @select="classify(null)"><span class="task-menu__dot task-menu__dot--unsorted" aria-hidden="true" />{{ t('matrix.unsorted') }}</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <DropdownMenuRoot>
                <DropdownMenuTrigger as-child><BaseButton variant="secondary"><FolderInput size="1rem" aria-hidden="true" />{{ t('tasks.bulk.move') }}</BaseButton></DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent class="popover" :side-offset="6" side="top">
                        <DropdownMenuItem class="popover__item" @select="move(null)">{{ t('nav.inbox') }}</DropdownMenuItem>
                        <DropdownMenuItem v-for="project in projects" :key="project.id" class="popover__item" @select="move(project.id)">{{ project.name }}</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
            <ConfirmButton :icon="Trash2" :label="t('tasks.bulk.delete')" :message="t('tasks.bulk.deleteMessage', { count: selection.count.value }, selection.count.value)" @confirm="remove" />
            <button type="button" class="bulk-bar__clear" :aria-label="t('tasks.bulk.clear')" @click="selection.clear()"><X size="1.125rem" aria-hidden="true" /></button>
        </div>
    </Transition>
</template>

<style scoped>
.bulk-bar {
    position: fixed;
    z-index: 30;
    bottom: calc(var(--space-4) + env(safe-area-inset-bottom));
    left: 50%;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    width: max-content;
    max-width: calc(100vw - 2rem);
    padding: var(--space-2) var(--space-3);
    border: 0.0625rem solid var(--color-border-strong);
    border-radius: var(--radius);
    background: var(--color-surface);
    box-shadow: var(--shadow);
    transform: translateX(-50%);
}
.bulk-bar__count { padding: 0 var(--space-2); font-weight: 600; }
.bulk-bar__clear { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: none; border-radius: var(--radius-pill); background: none; color: var(--color-muted); cursor: pointer; }
.bulk-bar__clear:hover { background: var(--color-hover); color: var(--color-text); }
.bulk-bar-enter-from, .bulk-bar-leave-to { opacity: 0; transform: translate(-50%, 0.5rem); }
.bulk-bar-enter-active, .bulk-bar-leave-active { transition: opacity 160ms ease, transform 160ms ease; }

@media (max-width: 48rem) {
    .bulk-bar { bottom: calc(4.5rem + env(safe-area-inset-bottom)); }
}
</style>
