<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import RarityMark from './RarityMark.vue';
import { useDates } from '../../composables/useDates.js';
import { speciesOf } from '../../herbarium/catalog.js';

const props = defineProps({
    slug: { type: String, required: true },
    rarity: { type: String, required: true },
    number: { type: Number, default: null },
    collectedAt: { type: String, default: null },
});

const { t } = useI18n();
const dates = useDates();
const species = computed(() => speciesOf(props.slug));
const name = computed(() => t(`species.${props.slug}`));
</script>

<template>
    <article v-if="species" :class="['species-card', `rarity--${rarity}`]" data-test="species-card">
        <img class="species-card__photo" :src="species.photo" alt="" loading="lazy" />
        <div class="species-card__label">
            <p class="species-card__header">
                <span v-if="number" class="species-card__number tabular">{{ t('herbarium.number', { number }) }}</span>
                <RarityMark :rarity="rarity" />
            </p>
            <h3 class="species-card__name">{{ name }}</h3>
            <p v-if="name !== species.scientificName" class="species-card__latin">{{ species.scientificName }}</p>
            <p class="species-card__note">{{ t(`speciesNotes.${slug}`) }}</p>
            <p v-if="collectedAt" class="species-card__date">{{ t('herbarium.collectedOn', { date: dates.short(collectedAt.slice(0, 10)) }) }}</p>
        </div>
        <a class="species-card__credit" :href="species.source" target="_blank" rel="noopener">{{ t('herbarium.photoBy', { attribution: species.attribution }) }}</a>
    </article>
</template>

<style scoped>
.species-card {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    height: 100%;
    padding: var(--space-3);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
}

.species-card.rarity--rare, .species-card.rarity--very_rare { border-color: color-mix(in oklch, var(--rarity) 45%, var(--color-border)); }
.species-card.rarity--very_rare { box-shadow: inset 0 0 0 0.0625rem color-mix(in oklch, var(--rarity) 30%, transparent); }

.species-card__photo { display: block; width: 100%; aspect-ratio: 4 / 3; border-radius: var(--radius-sm); object-fit: cover; background: var(--color-accent-soft); }

.species-card__label {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--space-1);
    padding: var(--space-3);
    border: var(--border-width) solid var(--color-border-strong);
    border-radius: var(--radius-sm);
}

.species-card__header { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); margin-bottom: var(--space-1); }
.species-card__number { color: var(--color-muted); font-size: var(--font-size-xs); }
.species-card__name { margin: 0; color: var(--color-ink); font-family: var(--font-display); font-size: 1.2rem; font-weight: var(--font-display-weight); line-height: 1.15; }
.species-card__latin { color: var(--color-muted); font-size: var(--font-size-sm); font-style: italic; }
.species-card__note { margin-top: var(--space-1); color: var(--color-text); font-size: var(--font-size-sm); line-height: 1.45; }
.species-card__date { margin-top: auto; padding-top: var(--space-2); color: var(--color-subtle); font-size: var(--font-size-xs); }
.species-card__credit { overflow: hidden; padding: 0 var(--space-1); color: var(--color-subtle); font-size: var(--font-size-2xs); text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.species-card__credit:hover { color: var(--color-muted); text-decoration: underline; }
</style>
