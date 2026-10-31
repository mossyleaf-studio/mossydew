<script setup>
import { useI18n } from 'vue-i18n';
import { clueText } from '../../mysteries/clues.js';

defineProps({
    investigation: { type: Object, required: true },
});

const { t } = useI18n();
</script>

<template>
    <ul class="board-rules">
        <li v-for="(rule, index) in investigation.rules" :key="index" class="board-rules__item" data-test="board-rule">{{ clueText(rule, investigation, t) }}</li>
    </ul>
</template>

<style scoped>
.board-rules { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: var(--space-3) var(--space-3) var(--space-3) var(--space-6); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-accent-soft); color: var(--color-ink); }
.board-rules__item { overflow-wrap: anywhere; }
</style>
