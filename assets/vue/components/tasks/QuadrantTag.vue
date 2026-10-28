<script setup>
import { Droplets, Recycle, Scissors, Sprout } from '../../icons.js';
import { useI18n } from 'vue-i18n';

const ICONS = { do_first: Droplets, schedule: Sprout, delegate: Scissors, eliminate: Recycle };

defineProps({
    quadrant: { type: String, required: true },
});

const { t } = useI18n();
</script>

<template>
    <span :class="['quadrant-tag', `quadrant-tag--${quadrant}`]" :title="t(`matrix.quadrants.${quadrant}.plain`)">
        <component :is="ICONS[quadrant]" size="0.875rem" :stroke-width="2" aria-hidden="true" />{{ t(`matrix.quadrants.${quadrant}.name`) }}
    </span>
</template>

<style scoped>
.quadrant-tag { --tone: var(--quadrant-unsorted); display: inline-flex; align-items: center; gap: 0.1875rem; color: color-mix(in oklch, var(--tone) 80%, var(--color-ink)); font-weight: 600; }
.quadrant-tag svg { color: var(--tone); }
.quadrant-tag--do_first { --tone: var(--quadrant-do-first); }
.quadrant-tag--schedule { --tone: var(--quadrant-schedule); }
.quadrant-tag--delegate { --tone: var(--quadrant-delegate); }
.quadrant-tag--eliminate { --tone: var(--quadrant-eliminate); }
</style>
