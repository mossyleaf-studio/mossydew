<script setup>
import { computed, ref } from 'vue';
import { ArrowRight, CloudDrizzle, CloudRain, Warehouse } from '../../icons.js';
import { useI18n } from 'vue-i18n';
import ConfirmButton from '../ui/ConfirmButton.vue';
import { useDew } from '../../composables/useDew.js';

const ICONS = { glasshouse: Warehouse, misters: CloudDrizzle, rain_barrel: CloudRain };

const props = defineProps({
    facilities: { type: Array, required: true },
    dew: { type: Number, required: true },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['upgrade']);

const { t } = useI18n();
const { number, dew: dewAmount } = useDew();

const frame = ref(null);

function focusFacility(id) {
    const row = frame.value?.querySelector(`[data-test="facility-${id}"]`);
    (row?.querySelector('.facilities__action button:not(:disabled)') ?? row?.querySelector('.facilities__name'))?.focus();
}

defineExpose({ focusFacility });

const effect = (id, value) => t(`greenhouse.facilities.${id}.effect`, { n: number(value) }, value);

const rows = computed(() => props.facilities.map((facility) => ({
    ...facility,
    icon: ICONS[facility.id],
    name: t(`greenhouse.facilities.${facility.id}.name`),
    now: effect(facility.id, facility.effect),
    next: facility.nextEffect === null ? null : effect(facility.id, facility.nextEffect),
    missing: facility.cost === null ? 0 : Math.max(0, facility.cost - props.dew),
})));
</script>

<template>
    <div ref="frame" class="facilities-frame">
        <table class="facilities">
            <thead class="facilities__head">
                <tr>
                    <th scope="col">{{ t('greenhouse.facilities.facility') }}</th>
                    <th scope="col">{{ t('greenhouse.facilities.level') }}</th>
                    <th scope="col">{{ t('greenhouse.facilities.effect') }}</th>
                    <th scope="col" class="facilities__numeric">{{ t('greenhouse.facilities.cost') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ t('greenhouse.pots.actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="facility in rows" :key="facility.id" class="facilities__row" :data-test="`facility-${facility.id}`">
                    <th scope="row" class="facilities__name" tabindex="-1">
                        <span class="facilities__facility">
                            <span class="facilities__icon" aria-hidden="true"><component :is="facility.icon" size="1.125rem" :stroke-width="1.75" /></span>
                            <span class="facilities__label">
                                <span class="facilities__title">{{ facility.name }}</span>
                                <span class="facilities__description">{{ t(`greenhouse.facilities.${facility.id}.description`) }}</span>
                            </span>
                        </span>
                    </th>
                    <td class="facilities__level tabular">
                        <span class="facilities__mobile-label">{{ t('greenhouse.facilities.level') }}</span>
                        {{ t('greenhouse.facilities.levelOf', { level: facility.level, max: facility.maxLevel }) }}
                    </td>
                    <td class="facilities__effect tabular">
                        <span>{{ facility.now }}</span>
                        <template v-if="facility.next">
                            <ArrowRight size="0.875rem" :stroke-width="2" class="facilities__arrow" aria-hidden="true" /><span class="visually-hidden">{{ t('greenhouse.facilities.then') }}</span>
                            <strong class="facilities__next">{{ facility.next }}</strong>
                        </template>
                    </td>
                    <td class="facilities__cost facilities__numeric tabular">{{ facility.cost === null ? '' : dewAmount(facility.cost) }}</td>
                    <td class="facilities__action">
                        <span v-if="facility.cost === null" class="facilities__max">{{ t('greenhouse.facilities.max') }}</span>
                        <template v-else>
                            <ConfirmButton
                                :label="t('greenhouse.facilities.upgrade')"
                                :title="t(`greenhouse.facilities.${facility.id}.upgradeTo`, { level: facility.level + 1 })"
                                :message="t('greenhouse.facilities.confirm', { cost: dewAmount(facility.cost) })"
                                :confirm-label="t('greenhouse.facilities.upgrade')"
                                :variant="facility.missing ? 'secondary' : 'primary'"
                                confirm-variant="primary"
                                :disabled="busy || facility.missing > 0"
                                @confirm="emit('upgrade', facility.id)"
                            />
                            <span v-if="facility.missing" class="facilities__short">{{ t('greenhouse.facilities.short', { n: number(facility.missing) }, facility.missing) }}</span>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.facilities-frame { container-type: inline-size; }
.facilities { width: 100%; border: var(--border-width) solid var(--color-border); border-radius: var(--radius); border-collapse: separate; border-spacing: 0; background: var(--color-surface); font-size: var(--font-size-md); }
.facilities th, .facilities td { padding: var(--space-3); border-bottom: var(--border-width) solid var(--color-border); text-align: left; vertical-align: middle; }
.facilities tbody tr:last-child > * { border-bottom: none; }
.facilities__head th { padding-block: var(--space-2); color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; white-space: nowrap; }
.facilities__numeric { text-align: right !important; }

.facilities__name { font-weight: 400; }
.facilities__name, .facilities__label { min-width: 0; }
.facilities__facility { display: flex; align-items: center; }
.facilities__icon { display: grid; flex-shrink: 0; place-items: center; width: 2.25rem; height: 2.25rem; margin-right: var(--space-3); border-radius: var(--radius); background: color-mix(in oklch, var(--color-dew) 12%, var(--color-surface)); color: color-mix(in oklch, var(--color-dew) 70%, var(--color-ink)); }
.facilities__label { display: inline-flex; flex-direction: column; }
.facilities__title { color: var(--color-ink); font-weight: 600; }
.facilities__description { color: var(--color-muted); font-size: var(--font-size-sm); }
.facilities__level { color: var(--color-ink); font-weight: 600; white-space: nowrap; }
.facilities__mobile-label { display: none; }
.facilities__effect { color: var(--color-muted); white-space: nowrap; }
.facilities__effect > * { vertical-align: middle; }
.facilities__arrow { margin: 0 var(--space-1); color: var(--color-subtle); }
.facilities__next { color: var(--color-ink); font-weight: 600; }
.facilities__cost { color: color-mix(in oklch, var(--color-dew) 65%, var(--color-ink)); font-weight: 600; white-space: nowrap; }
.facilities__action { width: 1%; text-align: right; white-space: nowrap; }
.facilities__short { display: block; margin-top: var(--space-1); color: var(--color-subtle); font-size: var(--font-size-xs); }
.facilities__max { display: inline-block; padding: var(--space-1) var(--space-3); border-radius: var(--radius-pill); background: var(--color-accent-soft); color: var(--color-accent-strong); font-size: var(--font-size-sm); font-weight: 600; }

@container (max-width: 44rem) {
    .facilities, .facilities tbody { display: block; }
    .facilities__head { position: absolute; width: 0.0625rem; height: 0.0625rem; overflow: hidden; clip: rect(0 0 0 0); }
    .facilities__row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas: "name name" "level cost" "effect action";
        align-items: center;
        gap: var(--space-2) var(--space-3);
        padding: var(--space-3);
        border-bottom: var(--border-width) solid var(--color-border);
    }
    .facilities tbody tr:last-child { border-bottom: none; }
    .facilities th, .facilities td { padding: 0; border: none; }
    .facilities__name { grid-area: name; }
    .facilities__level { grid-area: level; }
    .facilities__mobile-label { display: inline; color: var(--color-muted); font-weight: 400; }
    .facilities__effect { grid-area: effect; white-space: normal; }
    .facilities__cost { grid-area: cost; }
    .facilities__action { grid-area: action; width: auto; }
}
</style>
