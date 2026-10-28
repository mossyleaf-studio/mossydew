<script setup>
import { Sprout } from '../../icons.js';

defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: null },
});
</script>

<template>
    <div class="empty-state">
        <span class="empty-state__badge" aria-hidden="true"><Sprout size="2rem" /></span>
        <p class="empty-state__title">{{ title }}</p>
        <p v-if="hint" class="empty-state__hint">{{ hint }}</p>
        <slot />
    </div>
</template>

<style scoped>
.empty-state { display: flex; flex-direction: column; align-items: center; gap: var(--space-1); padding: var(--space-6) var(--space-5); border: var(--border-width) dashed var(--color-border-strong); border-radius: var(--radius); text-align: center; }
.empty-state__badge { display: inline-flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; margin-bottom: var(--space-2); border-radius: var(--radius-pill); background: var(--color-accent-soft); color: var(--color-accent); }
.empty-state__title { color: var(--color-ink); font-weight: 600; }
.empty-state__hint { color: var(--color-muted); font-size: var(--font-size-md); }
</style>
