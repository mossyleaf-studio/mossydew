<script setup>
import { Check, Droplet } from '../../icons.js';

defineProps({
    done: { type: Boolean, required: true },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle']);
</script>

<template>
    <button type="button" :class="['drop-check', { 'drop-check--done': done }]" role="checkbox" :aria-checked="done" :aria-label="label" :disabled="disabled" @click="emit('toggle')">
        <Droplet class="drop-check__drop" size="0.875rem" aria-hidden="true" />
        <Check class="drop-check__tick" size="1rem" :stroke-width="3" aria-hidden="true" />
    </button>
</template>

<style scoped>
.drop-check {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 1.625rem;
    height: 1.625rem;
    padding: 0;
    border: 0.09375rem solid var(--drop-check-ring, var(--color-border-strong));
    border-radius: 50%;
    background: var(--color-surface);
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition);
}

.drop-check > * { grid-area: 1 / 1; }
.drop-check__drop { color: var(--color-accent); transform: scale(0.4); opacity: 0; transition: transform var(--transition), opacity var(--transition); }
.drop-check__tick { color: var(--color-on-accent); transform: scale(0.4); opacity: 0; }

.drop-check:hover { border-color: var(--color-accent); }
.drop-check:hover .drop-check__drop { opacity: 1; transform: scale(1); }

.drop-check--done { border-color: var(--color-accent); background: var(--color-accent); }
.drop-check--done .drop-check__drop { opacity: 0; }
.drop-check--done .drop-check__tick { animation: tick 240ms cubic-bezier(0.3, 1.5, 0.5, 1) 80ms forwards; }
.drop-check--done:hover .drop-check__drop { opacity: 0; }

.drop-check:disabled { border-style: dashed; cursor: not-allowed; }
.drop-check:disabled:hover { border-color: var(--drop-check-ring, var(--color-border-strong)); }
.drop-check:disabled .drop-check__drop { opacity: 0; }

@keyframes tick { to { opacity: 1; transform: scale(1); } }
</style>
