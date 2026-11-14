<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { AlignLeft, ChevronRight, CornerDownRight, ListChecks, Repeat, Sun } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import IconButton from '../ui/IconButton.vue';
import DropCheck from './DropCheck.vue';
import PlanMenu from './PlanMenu.vue';
import QuadrantTag from './QuadrantTag.vue';
import TaskMenu from './TaskMenu.vue';
import { useCollapsed } from '../../composables/useCollapsed.js';
import { useDates } from '../../composables/useDates.js';
import { useProjects } from '../../composables/useProjects.js';
import { useSelection } from '../../composables/useSelection.js';
import { useSubtaskComposer } from '../../composables/useSubtaskComposer.js';
import { useTaskActions } from '../../composables/useTaskActions.js';
import { useTaskDrag } from '../../composables/useTaskDrag.js';
import { useTaskEditor } from '../../composables/useTaskEditor.js';
import { useToast } from '../../composables/useToast.js';
import { isOverdue, isPlannedFor } from '../../tasks/days.js';
import { recurrenceLabel } from '../../tasks/recurrence.js';
import { canNest, hasOpenSubtasks } from '../../tasks/subtasks.js';

const props = defineProps({
    task: { type: Object, required: true },
    showProject: { type: Boolean, default: true },
    showPlanned: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
    nested: { type: Boolean, default: false },
    nestable: { type: Boolean, default: false },
    collapsible: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
    expanded: { type: Boolean, default: null },
    nextDue: { type: String, default: null },
});

const emit = defineEmits(['fold']);

const { t } = useI18n();
const dates = useDates();
const { byId } = useProjects();
const actions = useTaskActions();
const editor = useTaskEditor();
const composer = useSubtaskComposer();
const drag = useTaskDrag();
const toast = useToast();
const selection = useSelection();
const collapse = useCollapsed();
const controlled = computed(() => props.expanded !== null);
const foldable = computed(() => props.collapsible || controlled.value);
const unfolded = computed(() => (controlled.value ? props.expanded : !collapse.isCollapsed(props.task)));
const planOpen = ref(false);
const dropping = ref(false);

const project = computed(() => (props.task.projectId ? byId.value.get(props.task.projectId) : null));
const inToday = computed(() => isPlannedFor(props.task, dates.today.value));
const due = computed(() => props.task.dueOn ?? props.nextDue);
const overdue = computed(() => isOverdue({ done: props.task.done, dueOn: due.value }, dates.today.value));

function dayLabel(day) {
    const relative = dates.relative(day);
    if (relative === 'weekday') return dates.weekday(day);
    if (relative === 'past' || relative === 'later') return dates.short(day);
    return t(`tasks.days.${relative}`);
}

const plannedLabel = computed(() => {
    if (!props.showPlanned || !props.task.plannedOn || props.task.done) return null;
    return dayLabel(props.task.plannedOn);
});

const locked = computed(() => hasOpenSubtasks(props.task));
const selected = computed(() => selection.has(props.task));

function select() {
    if (!props.task.done) selection.toggle(props.task);
}

function onClick(event) {
    if (event.target.closest('.task-row') !== event.currentTarget) return;
    const onTitle = event.target.closest('.task-row__title') !== null;
    if (!onTitle && event.target.closest('button, a, input')) return;
    const selecting = event.metaKey || event.ctrlKey || event.shiftKey || selection.count.value > 0;
    if (selecting && !props.task.done) {
        if (event.shiftKey) selection.extend(props.task);
        else select();
    } else if (onTitle) {
        editor.edit(props.task);
    }
}

onMounted(() => selection.show(props.task.id, () => props.task));
onBeforeUnmount(() => selection.hide(props.task.id));
const parentLabel = computed(() => (props.nested ? null : props.task.parentTitle));
const checkLabel = computed(() => {
    if (locked.value) return t('tasks.subtasks.locked', { title: props.task.title });
    return t(props.task.done ? 'tasks.reopen' : 'tasks.complete', { title: props.task.title });
});

const repeatLabel = computed(() => recurrenceLabel(props.task.recurrence, t));

const showQuadrant = computed(() => !props.compact && !props.task.done && props.task.quadrant !== null);

const dueLabel = computed(() => (due.value ? t('tasks.due', { day: dayLabel(due.value) }) : null));

function fold(open) {
    if (controlled.value) emit('fold', open);
    else collapse.toggle(props.task, !open);
}

function toggle() {
    if (locked.value) return null;
    return props.task.done ? actions.reopen(props.task) : actions.complete(props.task);
}

function addSubtask() {
    if (props.task.parentId) return;
    if (props.nestable) composer.open(props.task);
    else editor.edit(props.task);
}

async function promote() {
    if (await actions.promote(props.task)) toast.success(t('tasks.subtasks.promoted', { title: props.task.title }));
}

const draggable = computed(() => props.nestable && !props.task.done && !selection.count.value);
const dragged = computed(() => drag.state.task?.id === props.task.id);

function onDragStart(event) {
    if (!draggable.value) return;
    drag.start(props.task);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', props.task.title);
}

function onDragOver(event) {
    if (!props.nestable || !canNest(drag.state.task, props.task)) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dropping.value = true;
}

function onDragLeave(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) dropping.value = false;
}

async function onDrop(event) {
    dropping.value = false;
    const task = drag.state.task;
    if (!props.nestable || !canNest(task, props.task)) return;
    event.preventDefault();
    drag.end();
    if (await actions.nest(task, props.task)) toast.success(t('tasks.subtasks.nested', { title: task.title, parent: props.task.title }));
}

function plan(when, date = null) {
    actions.plan(props.task, when, date);
}

function onKeydown(event) {
    if (event.target !== event.currentTarget || event.metaKey || event.ctrlKey || event.altKey) return;
    const shortcuts = {
        t: () => plan('today'),
        m: () => plan('tomorrow'),
        n: () => plan('next_week'),
        d: () => { planOpen.value = true; },
        e: () => editor.edit(props.task),
        s: () => addSubtask(),
        x: () => select(),
        ...(foldable.value ? { ArrowLeft: () => fold(false), ArrowRight: () => fold(true) } : {}),
        Enter: () => editor.edit(props.task),
        ' ': () => toggle(),
    };
    if (shortcuts[event.key]) {
        event.preventDefault();
        shortcuts[event.key]();
    }
}
</script>

<template>
    <li
        :class="['task-row', `task-row--${task.quadrant ?? 'unsorted'}`, { 'task-row--done': task.done, 'task-row--compact': compact, 'task-row--nested': nested, 'task-row--dense': dense, 'task-row--drop': dropping, 'task-row--dragged': dragged, 'task-row--selected': selected, 'task-row--selectable': selection.count.value > 0 && !task.done }]"
        tabindex="0"
        :draggable="draggable ? 'true' : undefined"
        @dragstart="onDragStart"
        @dragend="drag.end()"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
        :data-task="task.id"
        @keydown="onKeydown"
        @click="onClick"
    >
        <DropCheck :done="task.done" :disabled="locked" :label="checkLabel" @toggle="toggle" />
        <div class="task-row__main">
            <button type="button" class="task-row__title" :title="dense ? task.title : undefined">{{ task.title }}</button>
            <p v-if="showQuadrant || parentLabel || (showProject && project) || task.subtaskCount || plannedLabel || dueLabel || repeatLabel || task.notes" class="task-row__meta">
                <QuadrantTag v-if="showQuadrant" :quadrant="task.quadrant" />
                <span v-if="parentLabel" class="task-row__parent"><CornerDownRight size="0.875rem" aria-hidden="true" />{{ t('tasks.subtasks.of', { title: parentLabel }) }}</span>
                <span v-if="showProject && project" class="task-row__project" :style="{ '--project': `var(--project-${project.color})` }">{{ project.name }}</span>
                <span v-if="plannedLabel" class="task-row__planned">{{ plannedLabel }}</span>
                <span v-if="dueLabel" :class="['task-row__due', { 'task-row__due--overdue': overdue }]">{{ dueLabel }}</span>
                <span v-if="repeatLabel" class="task-row__repeat"><Repeat size="0.875rem" aria-hidden="true" />{{ repeatLabel }}</span>
                <AlignLeft v-if="task.notes" size="0.875rem" class="task-row__notes" :aria-label="t('tasks.hasNotes')" role="img" />
                <button v-if="task.subtaskCount && foldable" type="button" :class="['task-row__folder', { 'task-row__folder--open': unfolded }]" :aria-expanded="unfolded" :aria-label="t(unfolded ? 'tasks.subtasks.hide' : 'tasks.subtasks.show', { title: task.title })" @click="fold(!unfolded)">
                    <ListChecks size="0.875rem" aria-hidden="true" /><span>{{ task.subtasksDone }}/{{ task.subtaskCount }}</span><ChevronRight size="0.75rem" class="task-row__folder-chevron" aria-hidden="true" />
                </button>
                <span v-else-if="task.subtaskCount" class="task-row__folder" role="img" :aria-label="t('tasks.subtasks.progress', { done: task.subtasksDone, total: task.subtaskCount })">
                    <ListChecks size="0.875rem" aria-hidden="true" /><span aria-hidden="true">{{ task.subtasksDone }}/{{ task.subtaskCount }}</span>
                </span>
            </p>
        </div>
        <div v-if="!task.done" class="task-row__actions">
            <IconButton
                :icon="Sun"
                :label="inToday ? t('tasks.removeFromToday') : t('tasks.addToToday')"
                :pressed="inToday"
                class="task-row__sun"
                @click="plan(inToday ? 'none' : 'today')"
            />
            <PlanMenu v-model:open="planOpen" :task="task" @plan="plan" />
            <TaskMenu :task="task" :selected="selected" @edit="editor.edit(task)" @classify="(quadrant) => actions.classify(task, quadrant)" @add-subtask="addSubtask" @promote="promote" @select="select" />
        </div>
    </li>
</template>

<style scoped>
.task-row {
    --notch: var(--quadrant-unsorted);
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--space-3);
    min-height: 3.25rem;
    padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
    outline: none;
    transition: background var(--transition), opacity var(--transition);
}

.task-row::before { content: ""; position: absolute; inset: calc(-1 * var(--border-width)); border-radius: var(--radius); background: linear-gradient(to right, var(--notch) 0.3125rem, transparent 0); pointer-events: none; }
.task-row--unsorted::before { top: 0.625rem; right: auto; bottom: 0.625rem; width: 0.1875rem; border-radius: 0 0.125rem 0.125rem 0; background: var(--notch); }
.task-row:hover, .task-row:focus-within { border-color: var(--color-border-strong); }
.task-row:focus-visible { box-shadow: var(--focus-ring); }

.task-row--do_first { --notch: var(--quadrant-do-first); }
.task-row--schedule { --notch: var(--quadrant-schedule); }
.task-row--delegate { --notch: var(--quadrant-delegate); }
.task-row--eliminate { --notch: var(--quadrant-eliminate); }
.task-row:not(.task-row--unsorted) { --drop-check-ring: color-mix(in oklch, var(--notch) 70%, var(--color-border-strong)); }

.task-row__main { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.task-row__title { padding: 0; border: none; background: none; color: var(--color-ink); font-size: var(--font-size); text-align: left; overflow-wrap: anywhere; cursor: pointer; }
.task-row__title:hover { color: var(--color-accent-strong); }
.task-row__meta { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-1) var(--space-3); color: var(--color-muted); font-size: var(--font-size-sm); }
.task-row__project { display: inline-flex; align-items: center; gap: var(--space-1); }
.task-row__project::before { content: ""; width: 0.5rem; height: 0.5rem; border-radius: 50%; background: var(--project); }
.task-row__due--overdue { color: var(--color-danger); font-weight: 600; }
.task-row__repeat, .task-row__parent { display: inline-flex; align-items: center; gap: var(--space-1); }
.task-row__parent { min-width: 0; overflow-wrap: anywhere; }
.task-row__notes { color: var(--color-subtle); }
.task-row__actions { display: flex; align-items: center; flex-shrink: 0; }
.task-row__sun[aria-pressed="true"] { color: var(--color-warning); }

.task-row--done { border-color: transparent; background: transparent; }
.task-row--done::before { opacity: 0.3; }
.task-row--done .task-row__title { color: var(--color-subtle); text-decoration: line-through; text-decoration-color: var(--color-border-strong); }

.task-row--nested { min-height: 2.75rem; }
.task-row[draggable="true"] { cursor: grab; }
.task-row--dragged { opacity: 0.45; }
.task-row--selectable { cursor: pointer; }
.task-row--selected { border-color: var(--color-accent); background: var(--color-accent-soft); }
.task-row--drop { border-color: var(--color-accent); background: var(--color-accent-soft); box-shadow: var(--focus-ring); }

.task-row--compact { min-height: 3rem; gap: var(--space-2); padding-left: var(--space-3); cursor: grab; }
.task-row--compact .task-row__title { font-size: var(--font-size-md); }

.task-row__folder { display: inline-flex; flex-shrink: 0; align-items: center; gap: var(--space-1); min-height: 1.75rem; padding: 0.125rem var(--space-2); border: var(--border-width) solid var(--color-border-strong); border-radius: var(--radius-pill); background: none; color: var(--color-text); font-size: var(--font-size-sm); font-weight: 600; font-variant-numeric: tabular-nums; }
button.task-row__folder { cursor: pointer; }
button.task-row__folder:hover { background: var(--color-hover); }
.task-row__folder:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.task-row__folder--open { border-color: var(--color-accent); background: var(--color-accent-soft); }
.task-row__folder :deep(svg) { flex-shrink: 0; color: var(--color-muted); }
.task-row__folder-chevron { transition: transform var(--transition); }
.task-row__folder--open .task-row__folder-chevron { transform: rotate(90deg); }

.task-row--dense { min-height: 2.75rem; padding-block: var(--space-1); border-radius: var(--radius-inner); }
.task-row--dense::before { border-radius: var(--radius-inner); }
.task-row--dense .task-row__main { flex-direction: row; align-items: center; gap: var(--space-3); }
.task-row--dense .task-row__title { flex: 1 1 0; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; overflow-wrap: normal; }
.task-row--dense .task-row__meta { flex-shrink: 0; flex-wrap: nowrap; white-space: nowrap; }
.task-row--dense .task-row__actions { margin-block: calc(-1 * var(--space-1)); }

@media (hover: hover) {
    .task-row--dense:not(:hover, :focus-within, :has([data-state="open"])) .task-row__actions > :deep(:not(.task-row__sun[aria-pressed="true"])) { display: none; }
}

@media (max-width: 30rem) {
    .task-row { gap: var(--space-2); padding-left: var(--space-3); }
    .task-row__sun { display: none; }
    .task-row--dense .task-row__main { flex-wrap: wrap; gap: 0 var(--space-3); }
    .task-row--dense .task-row__title { display: -webkit-box; flex-basis: 9rem; white-space: normal; overflow-wrap: anywhere; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
}
</style>
