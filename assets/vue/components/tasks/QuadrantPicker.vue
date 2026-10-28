<script setup>
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { QUADRANTS } from '../../tasks/quadrants.js';

const model = defineModel({ type: [String, null], default: null });
const { t } = useI18n();
const UNSORTED = 'unsorted';
</script>

<template>
    <RadioGroupRoot
        :model-value="model ?? UNSORTED"
        class="quadrant-picker"
        :aria-label="t('matrix.title')"
        @update:model-value="(value) => (model = value === UNSORTED ? null : value)"
    >
        <RadioGroupItem v-for="quadrant in QUADRANTS" :key="quadrant.value" :value="quadrant.value" :class="['quadrant-picker__option', `quadrant-picker__option--${quadrant.value}`]">
            <span class="quadrant-picker__name">{{ t(`matrix.quadrants.${quadrant.value}.name`) }}</span>
            <span class="quadrant-picker__plain">{{ t(`matrix.quadrants.${quadrant.value}.plain`) }}</span>
        </RadioGroupItem>
        <RadioGroupItem :value="UNSORTED" class="quadrant-picker__option quadrant-picker__option--unsorted">
            <span class="quadrant-picker__name">{{ t('matrix.unsorted') }}</span>
        </RadioGroupItem>
    </RadioGroupRoot>
</template>

<style scoped>
.quadrant-picker { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-2); }
.quadrant-picker__option {
    --tone: var(--quadrant-unsorted);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.125rem;
    padding: var(--space-2) var(--space-3);
    border: var(--border-width) solid var(--color-border-strong);
    border-radius: var(--radius);
    background: var(--color-surface);
    text-align: left;
    cursor: pointer;
}
.quadrant-picker__option--do_first { --tone: var(--quadrant-do-first); }
.quadrant-picker__option--schedule { --tone: var(--quadrant-schedule); }
.quadrant-picker__option--delegate { --tone: var(--quadrant-delegate); }
.quadrant-picker__option--eliminate { --tone: var(--quadrant-eliminate); }
.quadrant-picker__option--unsorted { grid-column: 1 / -1; }
.quadrant-picker__option:hover { border-color: var(--tone); }
.quadrant-picker__option[data-state="checked"] { border-color: var(--tone); background: color-mix(in oklch, var(--tone) 10%, var(--color-surface)); box-shadow: inset 0 0 0 0.0625rem var(--tone); }
.quadrant-picker__name { color: var(--color-ink); font-weight: 600; }
.quadrant-picker__option[data-state="checked"] .quadrant-picker__name { color: var(--tone); }
.quadrant-picker__plain { color: var(--color-muted); font-size: var(--font-size-xs); }
</style>
