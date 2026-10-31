<script setup>
import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AccusationPanel from '../components/mysteries/AccusationPanel.vue';
import CaseBoard from '../components/mysteries/CaseBoard.vue';
import CaseClues from '../components/mysteries/CaseClues.vue';
import HowToPlay from '../components/mysteries/HowToPlay.vue';
import PeoplePalette from '../components/mysteries/PeoplePalette.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { useCaseNotes } from '../composables/useCaseNotes.js';
import { mysteriesEnabled } from '../composables/useMysteries.js';
import { useToast } from '../composables/useToast.js';
import { everyonePlaced } from '../mysteries/notes.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const mystery = ref(null);
api.load('/api/mystery', mystery);

const investigation = computed(() => mystery.value?.investigation ?? null);
const solvedCount = computed(() => mystery.value?.solved ?? 0);
const { notes, mark, clearCrosses, settle } = useCaseNotes(investigation);

const picked = ref(null);
const murderer = ref(null);
const opening = ref(false);
const accusing = ref(false);

watch(() => investigation.value?.id, () => {
    picked.value = null;
    murderer.value = null;
});

const caseOpen = computed(() => investigation.value !== null && !investigation.value.solved);
const subtitle = computed(() => {
    if (!mystery.value) return null;
    if (!caseOpen.value) return t('mysteries.solvedCount', solvedCount.value);
    const { clues, hiddenClues } = investigation.value;

    return t('mysteries.progress', { revealed: clues.length, total: clues.length + hiddenClues });
});

const suspects = computed(() => (investigation.value?.people ?? [])
    .map((name, person) => ({ person, name }))
    .filter(({ person }) => person !== investigation.value.victim));
const ready = computed(() => notes.value !== null && everyonePlaced(notes.value));
const murdererName = computed(() => investigation.value.people[investigation.value.murderer]);
const victimName = computed(() => investigation.value.people[investigation.value.victim]);
const sceneRoom = computed(() => {
    const { solution, victim, rooms, kinds } = investigation.value;

    return t(`mysteries.rooms.${kinds[rooms[solution[victim]]]}.in`);
});

function tapCell(cell) {
    mark(cell, picked.value);
    picked.value = null;
}

async function openCase() {
    opening.value = true;
    await toast.attempt(() => api.post('/api/mystery/investigations'));
    opening.value = false;
}

async function accuse() {
    accusing.value = true;
    const { id } = investigation.value;
    await settle();
    const name = investigation.value.people[murderer.value];
    await toast.attempt(
        () => api.post(`/api/mystery/investigations/${id}/accusation`, { placements: notes.value.placements, murderer: murderer.value }),
        t('mysteries.accusation.solved', { name }),
    );
    accusing.value = false;
}
</script>

<template>
    <div class="page page--wide mysteries">
        <PageHeader :title="t('mysteries.title')" :subtitle="subtitle" />
        <template v-if="mystery">
            <p v-if="!mysteriesEnabled" class="mysteries__notice" data-test="mysteries-off">
                {{ t('mysteries.off') }}
                <RouterLink to="/settings">{{ t('mysteries.offAction') }}</RouterLink>
            </p>

            <section v-if="!investigation" class="mysteries__intro">
                <p class="mysteries__lead">{{ t('mysteries.intro') }}</p>
                <div>
                    <BaseButton :loading="opening" data-test="open-case" @click="openCase">{{ t(opening ? 'mysteries.opening' : 'mysteries.open') }}</BaseButton>
                </div>
                <HowToPlay default-open />
            </section>

            <div v-else-if="investigation.solved" class="mysteries__layout">
                <div class="mysteries__main">
                    <PageSection :title="t('mysteries.board.title')">
                        <CaseBoard :key="investigation.id" :investigation="investigation" :placements="investigation.solution" :murderer="investigation.murderer" readonly />
                    </PageSection>
                </div>
                <div class="mysteries__side">
                    <section class="verdict" data-test="verdict">
                        <h2 class="verdict__title">{{ t('mysteries.verdict.title') }}</h2>
                        <p class="verdict__culprit">{{ t('mysteries.verdict.culprit', { name: murdererName }) }}</p>
                        <p class="verdict__story">{{ t('mysteries.verdict.story', { murderer: murdererName, victim: victimName, room: sceneRoom }) }}</p>
                        <div>
                            <BaseButton :loading="opening" data-test="open-case" @click="openCase">{{ t(opening ? 'mysteries.opening' : 'mysteries.openNew') }}</BaseButton>
                        </div>
                    </section>
                    <PageSection :title="t('mysteries.clues.title')" :count="investigation.clues.length">
                        <CaseClues :clues="investigation.clues" :people="investigation.people" :kinds="investigation.kinds" />
                    </PageSection>
                </div>
            </div>

            <div v-else-if="notes" class="mysteries__layout">
                <div class="mysteries__main">
                    <PageSection :title="t('mysteries.palette.title')">
                        <PeoplePalette v-model:picked="picked" :people="investigation.people" :victim="investigation.victim" :placements="notes.placements" :size="investigation.size" />
                    </PageSection>
                    <PageSection :title="t('mysteries.board.title')">
                        <template #actions>
                            <BaseButton variant="ghost" :disabled="!notes.crosses.length" data-test="clear-crosses" @click="clearCrosses">{{ t('mysteries.board.clearCrosses') }}</BaseButton>
                        </template>
                        <CaseBoard :key="investigation.id" :investigation="investigation" :placements="notes.placements" :crosses="notes.crosses" :placing="picked !== null" @tap="tapCell" />
                    </PageSection>
                </div>
                <div class="mysteries__side">
                    <PageSection :title="t('mysteries.clues.title')" :count="investigation.clues.length">
                        <CaseClues :clues="investigation.clues" :hidden="investigation.hiddenClues" :people="investigation.people" :kinds="investigation.kinds" />
                    </PageSection>
                    <PageSection :title="t('mysteries.accusation.title')">
                        <AccusationPanel v-model:murderer="murderer" :suspects="suspects" :ready="ready" :busy="accusing" @accuse="accuse" />
                    </PageSection>
                    <HowToPlay />
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.mysteries__notice { padding: var(--space-3) var(--space-4); border: var(--border-width) solid var(--color-border-strong); border-radius: var(--radius); background: var(--color-warning-soft); color: var(--color-ink); font-size: var(--font-size-md); }
.mysteries__intro { display: flex; flex-direction: column; gap: var(--space-4); max-width: 46rem; }
.mysteries__lead { color: var(--color-muted); font-size: var(--font-size-md); }

.mysteries__layout { display: grid; grid-template-columns: minmax(0, 1fr) minmax(16rem, 24rem); align-items: start; gap: var(--space-5); }
.mysteries__main, .mysteries__side { display: flex; flex-direction: column; gap: var(--space-5); min-width: 0; }

.verdict { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-4); border: var(--border-width) solid var(--color-success); border-radius: var(--radius); background: var(--color-success-soft); }
.verdict__title { color: var(--color-ink); font-family: var(--font-display); font-size: 1.5rem; font-weight: var(--font-display-weight); }
.verdict__culprit { color: var(--color-ink); font-weight: 600; }
.verdict__story { margin-bottom: var(--space-2); color: var(--color-muted); font-size: var(--font-size-md); }

@media (max-width: 80rem) {
    .mysteries__layout { grid-template-columns: minmax(0, 1fr); }
}
</style>
