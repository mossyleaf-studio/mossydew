<script setup>
import { useI18n } from 'vue-i18n';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { allSpecies } from '../herbarium/catalog.js';

const { t } = useI18n();

const CREDITS = [
    { name: 'Patua One', author: 'LatinoType', license: 'SIL Open Font License 1.1', url: 'https://fonts.google.com/specimen/Patua+One' },
    { name: 'Inter', author: 'The Inter Project Authors', license: 'SIL Open Font License 1.1', url: 'https://rsms.me/inter/' },
    { name: 'Fredoka', author: 'Milena Brandão, Hafontia', license: 'SIL Open Font License 1.1', url: 'https://fonts.google.com/specimen/Fredoka' },
    { name: 'Nunito', author: 'Vernon Adams, Cyreal, Jacques Le Bailly', license: 'SIL Open Font License 1.1', url: 'https://fonts.google.com/specimen/Nunito' },
    { name: 'Lucide icons', author: 'Lucide contributors', license: 'ISC', url: 'https://lucide.dev' },
    { name: 'Phosphor icons', author: 'Phosphor Icons', license: 'MIT', url: 'https://phosphoricons.com' },
    { name: 'Reka UI', author: 'Reka UI contributors', license: 'MIT', url: 'https://reka-ui.com' },
    { name: 'Vue, Vue Router, Vue I18n', author: 'Vue contributors, intlify contributors', license: 'MIT', url: 'https://vuejs.org' },
    { name: 'Vue Draggable Plus (SortableJS)', author: 'Alfred-Skyblue, SortableJS contributors', license: 'MIT', url: 'https://github.com/Alfred-Skyblue/vue-draggable-plus' },
];
</script>

<template>
    <div class="page">
        <PageHeader :title="t('credits.title')" :subtitle="t('credits.subtitle')" />
        <p class="credits__note">{{ t('credits.sound') }}</p>
        <ul class="credits">
            <li v-for="credit in CREDITS" :key="credit.name" class="credits__item">
                <a :href="credit.url" rel="noopener" target="_blank">{{ credit.name }}</a>
                <span>{{ credit.author }} · {{ credit.license }}</span>
            </li>
        </ul>
        <PageSection :title="t('credits.photos')">
            <p class="credits__note">{{ t('credits.photosIntro') }}</p>
            <ul class="credits">
                <li v-for="species in allSpecies()" :key="species.slug" class="credits__item">
                    <a :href="species.source" rel="noopener" target="_blank">{{ species.scientificName }}</a>
                    <span>{{ species.attribution }} · {{ species.licenseLabel }}</span>
                </li>
            </ul>
        </PageSection>
    </div>
</template>

<style scoped>
.credits__note { max-width: 40rem; color: var(--color-muted); }
.credits { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.credits__item { display: flex; flex-direction: column; }
.credits__item span { color: var(--color-muted); font-size: var(--font-size-sm); }
</style>
