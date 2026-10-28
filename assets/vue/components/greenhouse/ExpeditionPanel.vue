<script setup>
import { computed, ref } from 'vue';
import { Compass } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import BaseModal from '../ui/BaseModal.vue';
import SpeciesCard from '../herbarium/SpeciesCard.vue';
import { useDew } from '../../composables/useDew.js';

const props = defineProps({
    expedition: { type: Object, required: true },
    dew: { type: Number, required: true },
    busy: { type: Boolean, default: false },
});
const found = defineModel('found', { type: Object, default: null });
const emit = defineEmits(['launch', 'returned']);

const { t } = useI18n();
const { number, dew: dewAmount } = useDew();

const missing = computed(() => Math.max(0, props.expedition.cost - props.dew));
const panel = ref(null);

function returnToPanel(event) {
    event.preventDefault();
    window.setTimeout(() => {
        (panel.value?.querySelector('[data-test="expedition"]:not(:disabled)') ?? panel.value)?.focus();
        emit('returned');
    }, 0);
}

const revealOpen = computed({ get: () => found.value !== null, set: (open) => { if (!open) found.value = null; } });
</script>

<template>
    <div ref="panel" class="expedition" tabindex="-1">
        <span class="expedition__icon" aria-hidden="true"><Compass size="1.5rem" :stroke-width="1.75" /></span>
        <div class="expedition__text">
            <p class="expedition__title">{{ t('greenhouse.expedition.title') }}</p>
            <p v-if="expedition.speciesLeft" class="expedition__description">
                {{ t('greenhouse.expedition.description') }}
                <span class="expedition__left tabular">{{ t('greenhouse.expedition.left', { n: expedition.speciesLeft }, expedition.speciesLeft) }}</span>
                <span class="expedition__formula tabular" data-test="expedition-formula">{{ t('greenhouse.expedition.formula', { trips: expedition.trips, cost: number(expedition.cost) }, expedition.trips) }}</span>
            </p>
            <p v-else class="expedition__description">{{ t('greenhouse.expedition.complete') }}</p>
        </div>
        <div v-if="expedition.speciesLeft" class="expedition__action">
            <span class="expedition__cost tabular">{{ dewAmount(expedition.cost) }}</span>
            <BaseButton :variant="missing ? 'secondary' : 'primary'" :disabled="busy || missing > 0" data-test="expedition" @click="emit('launch')">{{ t('greenhouse.expedition.launch') }}</BaseButton>
            <span v-if="missing" class="expedition__short">{{ t('greenhouse.facilities.short', { n: number(missing) }, missing) }}</span>
        </div>
    </div>

    <BaseModal v-model:open="revealOpen" :title="t('greenhouse.expedition.back')" @close-auto-focus="returnToPanel">
        <template v-if="found">
            <SpeciesCard :slug="found.species.slug" :rarity="found.species.rarity" />
            <p class="expedition__next">{{ t('greenhouse.expedition.next', { cost: dewAmount(found.nextCost) }) }}</p>
            <div class="actions-row"><BaseButton @click="revealOpen = false">{{ t('celebration.continue') }}</BaseButton></div>
        </template>
    </BaseModal>
</template>

<style scoped>
.expedition { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: var(--space-4); padding: var(--space-4); border: var(--border-width) solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.expedition__icon { display: grid; place-items: center; width: 3rem; height: 3rem; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent); }
.expedition__text { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
.expedition__title { color: var(--color-ink); font-weight: 600; }
.expedition__description { color: var(--color-muted); font-size: var(--font-size-md); }
.expedition__left { display: block; color: var(--color-subtle); font-size: var(--font-size-sm); }
.expedition__action { display: grid; grid-template-columns: auto auto; align-items: center; justify-items: end; gap: var(--space-1) var(--space-3); }
.expedition__cost { color: color-mix(in oklch, var(--color-dew) 65%, var(--color-ink)); font-weight: 600; white-space: nowrap; }
.expedition__formula { display: block; color: var(--color-subtle); font-size: var(--font-size-xs); }
.expedition__short { grid-column: 1 / -1; color: var(--color-subtle); font-size: var(--font-size-xs); }
.expedition__next { color: var(--color-muted); font-size: var(--font-size-md); }

@media (max-width: 40rem) {
    .expedition { grid-template-columns: auto minmax(0, 1fr); }
    .expedition__action { grid-column: 1 / -1; justify-content: end; }
}
</style>
