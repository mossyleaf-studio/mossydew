<script setup>
import { computed } from 'vue';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({
    suspects: { type: Array, required: true },
    ready: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});
const murderer = defineModel('murderer', { type: Number, default: null });
const emit = defineEmits(['accuse']);

const { t } = useI18n();

const blocker = computed(() => {
    if (!props.ready) return 'needsEveryone';

    return murderer.value === null ? 'needsSuspect' : null;
});

const initial = (name) => [...name][0].toUpperCase();
const choose = (value) => { murderer.value = value ?? null; };
</script>

<template>
    <div class="accusation">
        <p class="accusation__intro">{{ t('mysteries.accusation.intro') }}</p>
        <RadioGroupRoot :model-value="murderer" class="accusation__suspects" :aria-label="t('mysteries.accusation.suspects')" @update:model-value="choose">
            <RadioGroupItem v-for="suspect in suspects" :key="suspect.person" :value="suspect.person" class="accusation__suspect" data-test="suspect">
                <span class="accusation__avatar" aria-hidden="true">{{ initial(suspect.name) }}</span>
                <span>{{ suspect.name }}</span>
            </RadioGroupItem>
        </RadioGroupRoot>
        <div class="accusation__submit">
            <BaseButton :disabled="blocker !== null" :loading="busy" data-test="accuse" @click="emit('accuse')">{{ t('mysteries.accusation.submit') }}</BaseButton>
            <p v-if="blocker" class="accusation__blocker">{{ t(`mysteries.accusation.${blocker}`) }}</p>
        </div>
    </div>
</template>

<style scoped>
.accusation { display: flex; flex-direction: column; gap: var(--space-3); }
.accusation__intro { color: var(--color-muted); font-size: var(--font-size-md); }
.accusation__suspects { display: flex; flex-wrap: wrap; gap: var(--space-2); }

.accusation__suspect {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: 2.25rem;
    padding: var(--space-1) var(--space-3) var(--space-1) var(--space-1);
    border: var(--border-width) solid var(--color-border-strong);
    border-radius: var(--radius-pill);
    background: var(--color-surface);
    color: var(--color-ink);
    font-size: var(--font-size-md);
    font-weight: 500;
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
}

.accusation__suspect:hover { border-color: var(--color-ink); }
.accusation__suspect[data-state='checked'] { border-color: var(--color-danger); background: var(--color-danger-soft); box-shadow: 0 0 0 0.1875rem var(--color-danger-soft); }

.accusation__avatar { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent-strong); font-size: var(--font-size-sm); font-weight: 700; line-height: 1; transition: background var(--transition), color var(--transition); }
.accusation__suspect[data-state='checked'] .accusation__avatar { background: var(--color-danger); color: var(--color-surface); }

.accusation__submit { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
.accusation__blocker { color: var(--color-subtle); font-size: var(--font-size-sm); }
</style>
