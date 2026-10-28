<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { CornerDownRight } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import { useSubtaskComposer } from '../../composables/useSubtaskComposer.js';
import { useTaskActions } from '../../composables/useTaskActions.js';

const props = defineProps({
    parent: { type: Object, required: true },
});

const { t } = useI18n();
const actions = useTaskActions();
const composer = useSubtaskComposer();
const title = ref('');
const saving = ref(false);
const input = ref(null);

onMounted(() => input.value?.focus());

async function submit() {
    const text = title.value.trim();
    if (!text || saving.value) return;
    saving.value = true;
    const created = await actions.addSubtask(props.parent, text);
    saving.value = false;
    if (created === null) return;
    title.value = '';
    await nextTick();
    input.value?.focus();
}

function closeIfEmpty() {
    if (!title.value.trim() && !saving.value) composer.close();
}
</script>

<template>
    <form class="subtask-composer" @submit.prevent="submit">
        <CornerDownRight class="subtask-composer__icon" size="1rem" aria-hidden="true" />
        <input
            ref="input"
            v-model="title"
            class="subtask-composer__input"
            type="text"
            :aria-label="t('tasks.subtasks.add')"
            :placeholder="t('tasks.subtasks.placeholder')"
            maxlength="200"
            autocomplete="off"
            enterkeyhint="done"
            @keydown.esc.prevent="composer.close()"
            @blur="closeIfEmpty"
        >
        <button type="submit" class="subtask-composer__submit" :disabled="!title.trim() || saving">{{ t('tasks.composer.submit') }}</button>
    </form>
</template>

<style scoped>
.subtask-composer { display: flex; align-items: center; gap: var(--space-3); min-height: 2.75rem; padding: var(--space-1) var(--space-1) var(--space-1) var(--space-3); border: var(--border-width) solid var(--color-accent); border-radius: var(--radius); background: var(--color-surface); box-shadow: var(--focus-ring); }
.subtask-composer__icon { flex-shrink: 0; color: var(--color-muted); }
.subtask-composer__input { flex: 1; min-width: 0; padding: var(--space-1) 0; border: none; background: none; outline: none; }
.subtask-composer__input::placeholder { color: var(--color-subtle); }
.subtask-composer__submit { padding: var(--space-1) var(--space-3); border: none; border-radius: var(--radius); background: var(--color-accent); color: var(--color-on-accent); font-weight: 600; cursor: pointer; }
.subtask-composer__submit:disabled { opacity: 0; pointer-events: none; }
</style>
