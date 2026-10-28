<script setup>
defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    href: { type: String, default: null },
});
</script>

<template>
    <a v-if="href" :href="href" :class="['button', `button--${variant}`]"><slot /></a>
    <button v-else :type="type" :class="['button', `button--${variant}`]" :disabled="disabled || loading" :aria-busy="loading || undefined">
        <span v-if="loading" class="button__spinner" aria-hidden="true" />
        <slot />
    </button>
</template>

<style scoped>
.button {
    --lip-color: var(--color-border-strong);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    min-height: 2.375rem;
    padding: var(--space-2) var(--space-4);
    border: var(--border-width) solid transparent;
    border-radius: var(--radius);
    box-shadow: 0 var(--lip) 0 var(--lip-color);
    font-family: var(--font-button);
    font-weight: 600;
    font-size: var(--font-size-md);
    text-decoration: none;
    cursor: pointer;
    transition: transform 90ms ease, box-shadow 90ms ease, background var(--transition), border-color var(--transition), color var(--transition), opacity var(--transition);
}

.button:active:not(:disabled) { transform: translateY(var(--lip)); box-shadow: 0 0 0 var(--lip-color); }
.button:disabled { opacity: 0.5; cursor: not-allowed; }

.button--primary { --lip-color: var(--color-accent-strong); background: var(--color-accent); color: var(--color-on-accent); }
.button--primary:hover:not(:disabled) { background: var(--color-accent-strong); }

.button--secondary { background: var(--color-surface); border-color: var(--color-border-strong); color: var(--color-ink); }
.button--secondary:hover:not(:disabled) { border-color: var(--color-ink); }

.button--danger { background: var(--color-surface); border-color: var(--color-danger); color: var(--color-danger); }
.button--danger:hover:not(:disabled) { background: var(--color-danger-soft); }

.button--ghost { min-height: auto; padding: var(--space-1) var(--space-2); background: none; box-shadow: none; color: var(--color-muted); font-weight: 500; }
.button--ghost:active:not(:disabled) { transform: none; }
.button--ghost:hover:not(:disabled) { color: var(--color-ink); text-decoration: underline; text-underline-offset: 0.1875rem; }

.button__spinner { width: 0.85rem; height: 0.85rem; border: 0.125rem solid currentColor; border-right-color: transparent; border-radius: 50%; animation: button-spin 0.7s linear infinite; }

@keyframes button-spin { to { transform: rotate(360deg); } }
</style>
