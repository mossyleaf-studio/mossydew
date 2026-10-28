<script setup>
import { ref, watch } from 'vue';
import { Plus } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import DropCheck from './DropCheck.vue';
import { useApi } from '../../composables/useApi.js';
import { useTaskActions } from '../../composables/useTaskActions.js';
import { useTaskEditor } from '../../composables/useTaskEditor.js';
import { useToast } from '../../composables/useToast.js';

const props = defineProps({
    task: { type: Object, required: true },
});

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const actions = useTaskActions();
const editor = useTaskEditor();
const subtasks = ref(null);
const title = ref('');
const saving = ref(false);

watch(() => props.task.id, (id) => {
    subtasks.value = null;
    api.load(`/api/tasks/${id}/subtasks`, subtasks).catch((error) => toast.error(error.message));
}, { immediate: true });

function toggle(subtask) {
    return subtask.done ? actions.reopen(subtask) : actions.complete(subtask);
}

async function add() {
    const text = title.value.trim();
    if (!text || saving.value) return;
    saving.value = true;
    try {
        await api.post(`/api/tasks/${props.task.id}/subtasks`, { title: text });
        title.value = '';
    } catch (error) {
        toast.error(error.message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="subtasks">
        <ul v-if="subtasks?.length" class="subtasks__list">
            <li v-for="subtask in subtasks" :key="subtask.id" :class="['subtasks__item', { 'subtasks__item--done': subtask.done }]">
                <DropCheck :done="subtask.done" :label="t(subtask.done ? 'tasks.reopen' : 'tasks.complete', { title: subtask.title })" @toggle="toggle(subtask)" />
                <button type="button" class="subtasks__title" @click="editor.edit(subtask)">{{ subtask.title }}</button>
            </li>
        </ul>
        <div class="subtasks__add">
            <Plus size="1rem" class="subtasks__icon" aria-hidden="true" />
            <input
                v-model="title"
                class="subtasks__input"
                type="text"
                :aria-label="t('tasks.subtasks.add')"
                :placeholder="t('tasks.subtasks.placeholder')"
                maxlength="200"
                autocomplete="off"
                enterkeyhint="done"
                @keydown.enter.prevent="add"
            >
            <button type="button" class="subtasks__submit" :disabled="!title.trim() || saving" @click="add">{{ t('tasks.composer.submit') }}</button>
        </div>
    </div>
</template>

<style scoped>
.subtasks { display: flex; flex-direction: column; gap: var(--space-2); }
.subtasks__list { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; padding: 0; list-style: none; }
.subtasks__item { display: flex; align-items: center; gap: var(--space-3); min-height: 2.5rem; }
.subtasks__title { flex: 1; min-width: 0; padding: 0; border: none; background: none; color: var(--color-ink); text-align: left; overflow-wrap: anywhere; cursor: pointer; }
.subtasks__title:hover { color: var(--color-accent-strong); }
.subtasks__item--done .subtasks__title { color: var(--color-subtle); text-decoration: line-through; text-decoration-color: var(--color-border-strong); }
.subtasks__add { display: flex; align-items: center; gap: var(--space-3); min-height: 2.75rem; padding: var(--space-1) var(--space-1) var(--space-1) var(--space-3); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius); }
.subtasks__add:focus-within { border-style: solid; border-color: var(--color-accent); box-shadow: var(--focus-ring); }
.subtasks__icon { flex-shrink: 0; color: var(--color-muted); }
.subtasks__input { flex: 1; min-width: 0; padding: var(--space-1) 0; border: none; background: none; outline: none; }
.subtasks__input::placeholder { color: var(--color-subtle); }
.subtasks__submit { padding: var(--space-1) var(--space-3); border: none; border-radius: var(--radius); background: var(--color-accent); color: var(--color-on-accent); font-weight: 600; cursor: pointer; }
.subtasks__submit:disabled { opacity: 0; pointer-events: none; }
</style>
