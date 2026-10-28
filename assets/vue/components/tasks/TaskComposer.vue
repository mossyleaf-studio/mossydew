<script setup>
import { ref } from 'vue';
import { Plus } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import { useTaskActions } from '../../composables/useTaskActions.js';

const props = defineProps({
    defaults: { type: Object, default: () => ({}) },
    placeholder: { type: String, default: null },
});

const { t } = useI18n();
const actions = useTaskActions();
const title = ref('');
const saving = ref(false);

async function submit() {
    const text = title.value.trim();
    if (!text || saving.value) return;
    saving.value = true;
    const created = await actions.create({ title: text, ...props.defaults });
    saving.value = false;
    if (created !== null) title.value = '';
}
</script>

<template>
    <form class="task-composer" @submit.prevent="submit">
        <Plus class="task-composer__icon" size="1.25rem" aria-hidden="true" />
        <label class="visually-hidden" for="task-composer-input">{{ t('tasks.composer.label') }}</label>
        <input
            id="task-composer-input"
            v-model="title"
            class="task-composer__input"
            type="text"
            :placeholder="placeholder ?? t('tasks.composer.placeholder')"
            :maxlength="200"
            autocomplete="off"
            enterkeyhint="done"
        >
        <button type="submit" class="task-composer__submit" :disabled="!title.trim() || saving">{{ t('tasks.composer.submit') }}</button>
    </form>
</template>

<style scoped>
.task-composer {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    min-height: 3.25rem;
    padding: var(--space-2) var(--space-2) var(--space-2) var(--space-4);
    border: 0.0625rem dashed var(--color-border-strong);
    border-radius: var(--radius);
    transition: border-color var(--transition), background var(--transition);
}

.task-composer:focus-within { border-style: solid; border-color: var(--color-accent); background: var(--color-surface); box-shadow: var(--focus-ring); }
.task-composer__icon { flex-shrink: 0; color: var(--color-muted); }
.task-composer__input { flex: 1; min-width: 0; padding: var(--space-2) 0; border: none; background: none; outline: none; }
.task-composer__input::placeholder { color: var(--color-subtle); }
.task-composer__submit { padding: var(--space-2) var(--space-4); border: none; border-radius: var(--radius); background: var(--color-accent); color: var(--color-on-accent); font-weight: 600; cursor: pointer; }
.task-composer__submit:disabled { opacity: 0; pointer-events: none; }
</style>
