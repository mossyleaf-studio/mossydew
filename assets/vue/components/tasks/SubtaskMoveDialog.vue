<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import BaseModal from '../ui/BaseModal.vue';
import { useSubtaskQuestion } from '../../composables/useSubtaskQuestion.js';

const { t } = useI18n();
const question = useSubtaskQuestion();

const open = computed({
    get: () => question.state.parents.length > 0,
    set: (value) => {
        if (!value) question.answer(null);
    },
});

const body = computed(() => {
    const { parents } = question.state;
    return parents.length === 1 ? t('tasks.subtasks.move.one', { title: parents[0].title }) : t('tasks.subtasks.move.many', { count: parents.length });
});
</script>

<template>
    <BaseModal v-model:open="open" :title="t('tasks.subtasks.move.title')">
        <p class="subtask-move__body">{{ body }}</p>
        <div class="subtask-move__actions">
            <BaseButton variant="secondary" @click="question.answer(false)">{{ t('tasks.subtasks.move.only') }}</BaseButton>
            <BaseButton @click="question.answer(true)">{{ t('tasks.subtasks.move.all') }}</BaseButton>
        </div>
    </BaseModal>
</template>

<style scoped>
.subtask-move__body { margin: 0 0 var(--space-4); color: var(--color-ink); }
.subtask-move__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }
</style>
