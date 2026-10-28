<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const RARITIES = ['common', 'uncommon', 'rare', 'very_rare'];

const props = defineProps({
    rarity: { type: String, required: true },
});

const { t } = useI18n();
const level = computed(() => RARITIES.indexOf(props.rarity) + 1);
</script>

<template>
    <span :class="['rarity-mark', `rarity--${rarity}`]">
        <span class="rarity-mark__spores" aria-hidden="true">
            <span v-for="spore in RARITIES.length" :key="spore" :class="['rarity-mark__spore', { 'rarity-mark__spore--on': spore <= level }]" />
        </span>
        {{ t(`herbarium.rarity.${rarity}`) }}
    </span>
</template>

<style scoped>
.rarity-mark { display: inline-flex; align-items: center; gap: var(--space-2); color: color-mix(in oklch, var(--rarity) 75%, var(--color-ink)); font-size: var(--font-size-xs); font-weight: 600; white-space: nowrap; }
.rarity-mark__spores { display: inline-flex; gap: 0.1875rem; }
.rarity-mark__spore { width: 0.4375rem; height: 0.4375rem; border: var(--border-width) solid var(--rarity); border-radius: 50%; }
.rarity-mark__spore--on { background: var(--rarity); }
</style>
