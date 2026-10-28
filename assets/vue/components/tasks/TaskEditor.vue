<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Trash2 } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import BaseDatePicker from '../ui/BaseDatePicker.vue';
import BaseModal from '../ui/BaseModal.vue';
import BaseSelect from '../ui/BaseSelect.vue';
import ConfirmButton from '../ui/ConfirmButton.vue';
import FormField from '../ui/FormField.vue';
import QuadrantPicker from './QuadrantPicker.vue';
import SubtaskChecklist from './SubtaskChecklist.vue';
import { ApiError, useApi } from '../../composables/useApi.js';
import { useProjects } from '../../composables/useProjects.js';
import { useTaskActions } from '../../composables/useTaskActions.js';
import { useToast } from '../../composables/useToast.js';
import { RECURRENCE_UNITS, parseInterval, recurrenceLabel } from '../../tasks/recurrence.js';

const props = defineProps({
    task: { type: Object, default: null },
});
const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const actions = useTaskActions();
const { projects } = useProjects();

const form = reactive({ title: '', notes: '', projectId: null, dueOn: null, quadrant: null, repeatUnit: null, repeatInterval: '1' });
const errors = ref({});
const saving = ref(false);

watch(() => [open.value, props.task], () => {
    if (!open.value || !props.task) return;
    Object.assign(form, {
        title: props.task.title,
        notes: props.task.notes ?? '',
        projectId: props.task.projectId,
        dueOn: props.task.dueOn,
        quadrant: props.task.quadrant,
        repeatUnit: props.task.recurrence?.unit ?? null,
        repeatInterval: String(props.task.recurrence?.interval ?? 1),
    });
    errors.value = {};
}, { immediate: true });

const projectOptions = computed(() => [
    { value: null, label: t('projects.none') },
    ...projects.value.map((project) => ({ value: project.id, label: project.name, color: project.color })),
]);

const interval = computed(() => parseInterval(form.repeatInterval));

const recurrence = computed(() => (form.repeatUnit && interval.value !== null ? { interval: interval.value, unit: form.repeatUnit } : null));

const unitOptions = computed(() => [
    { value: null, label: t('tasks.repeat.never') },
    ...RECURRENCE_UNITS.map((unit) => ({ value: unit, label: t(`tasks.repeat.units.${unit}`, interval.value ?? 1) })),
]);

async function save() {
    errors.value = {};
    if (form.repeatUnit && interval.value === null) {
        errors.value = { 'recurrence.interval': t('tasks.repeat.invalid') };
        return;
    }
    saving.value = true;
    try {
        await api.patch(`/api/tasks/${props.task.id}`, { title: form.title, notes: form.notes, projectId: form.projectId, dueOn: form.dueOn, recurrence: recurrence.value });
        if (form.quadrant !== props.task.quadrant) await api.post(`/api/tasks/${props.task.id}/classify`, { quadrant: form.quadrant });
        open.value = false;
    } catch (error) {
        if (error instanceof ApiError && error.violations.length) errors.value = error.fieldErrors;
        else toast.error(error.message);
    } finally {
        saving.value = false;
    }
}

async function remove() {
    await actions.remove(props.task);
    open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('tasks.editor.title')">
        <form v-if="task" class="task-editor" @submit.prevent="save">
            <FormField :label="t('tasks.editor.name')" :error="errors.title">
                <input v-model="form.title" type="text" maxlength="200" required>
            </FormField>
            <FormField :label="t('tasks.editor.notes')">
                <textarea v-model="form.notes" rows="3" />
            </FormField>
            <p v-if="task.parentId" class="task-editor__parent">{{ t('tasks.subtasks.of', { title: task.parentTitle }) }}</p>
            <div class="task-editor__pair">
                <FormField v-if="!task.parentId" :label="t('tasks.editor.project')" as="div">
                    <BaseSelect v-model="form.projectId" :options="projectOptions" />
                </FormField>
                <FormField :label="t('tasks.editor.deadline')" as="div" :error="errors.dueOn">
                    <BaseDatePicker v-model="form.dueOn" />
                </FormField>
            </div>
            <FormField v-if="!task.parentId" :label="t('tasks.subtasks.title')" as="div" :hint="t('tasks.subtasks.hint')">
                <SubtaskChecklist :task="task" />
            </FormField>
            <FormField v-if="!task.done && !task.parentId" :label="t('tasks.editor.repeat')" as="div" :error="errors['recurrence.interval']" :hint="recurrenceLabel(recurrence, t)">
                <div class="task-editor__repeat">
                    <input
                        v-if="form.repeatUnit"
                        v-model="form.repeatInterval"
                        type="text"
                        inputmode="numeric"
                        maxlength="3"
                        class="control task-editor__interval"
                        :aria-label="t('tasks.repeat.interval')"
                    >
                    <BaseSelect v-model="form.repeatUnit" :options="unitOptions" />
                </div>
            </FormField>
            <FormField :label="t('tasks.editor.matrix')" as="div">
                <QuadrantPicker v-model="form.quadrant" />
            </FormField>
            <div class="task-editor__actions">
                <ConfirmButton :icon="Trash2" :label="t('tasks.editor.delete')" :message="t('tasks.editor.deleteMessage', { title: task.title })" @confirm="remove" />
                <span class="task-editor__spacer" />
                <BaseButton variant="ghost" @click="open = false">{{ t('common.cancel') }}</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ t('common.save') }}</BaseButton>
            </div>
        </form>
    </BaseModal>
</template>

<style scoped>
.task-editor { display: flex; flex-direction: column; gap: var(--space-4); }
.task-editor__parent { margin: 0; color: var(--color-muted); font-size: var(--font-size-sm); }
.task-editor__pair { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: var(--space-4); }
.task-editor__repeat { display: flex; gap: var(--space-2); }
.task-editor__interval { flex: none; width: 4.5rem; text-align: center; font-variant-numeric: tabular-nums; }
.task-editor__actions { display: flex; align-items: center; gap: var(--space-2); }
.task-editor__spacer { flex: 1; }
</style>
