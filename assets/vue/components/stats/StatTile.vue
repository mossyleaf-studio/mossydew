<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: null },
});
</script>

<template>
    <div :class="['stat-tile', { [`stat-tile--${tone}`]: tone }]">
        <dt class="stat-tile__label">{{ label }}</dt>
        <dd class="stat-tile__value">{{ value }}</dd>
        <dd v-if="hint" class="stat-tile__hint">{{ hint }}</dd>
    </div>
</template>

<style scoped>
.stat-tile { display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-3) var(--space-4); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.stat-tile dd { margin: 0; }
.stat-tile__label { color: var(--color-muted); font-size: var(--font-size-sm); }
.stat-tile__value { color: var(--color-ink); font-size: 1.75rem; font-weight: 600; line-height: 1.1; }
.stat-tile__hint { color: var(--color-subtle); font-size: var(--font-size-xs); }
.stat-tile--alert { border-color: color-mix(in oklch, var(--color-danger) 35%, var(--color-border)); }
.stat-tile--alert .stat-tile__label { color: var(--color-danger); }
</style>
