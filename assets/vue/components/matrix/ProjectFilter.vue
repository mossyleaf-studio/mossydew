<script setup>
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { useProjects } from '../../composables/useProjects.js';
import { INBOX } from '../../tasks/projectFilter.js';

const model = defineModel({ type: Array, required: true });
const { t } = useI18n();
const { projects } = useProjects();

const options = computed(() => [
    ...projects.value.map((project) => ({ value: project.id, label: project.name, tone: `var(--project-${project.color})` })),
    { value: INBOX, label: t('matrix.filter.inbox'), tone: 'var(--color-subtle)' },
]);
</script>

<template>
    <div class="project-filter">
        <ToggleGroupRoot v-model="model" type="multiple" class="project-filter__options" :aria-label="t('matrix.filter.label')">
            <ToggleGroupItem v-for="option in options" :key="option.value" :value="option.value" class="project-filter__option" :style="{ '--tone': option.tone }">
                {{ option.label }}
            </ToggleGroupItem>
        </ToggleGroupRoot>
        <button v-if="model.length" type="button" class="project-filter__clear" @click="model = []">{{ t('matrix.filter.all') }}</button>
    </div>
</template>

<style scoped>
.project-filter { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }
.project-filter__options { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.project-filter__option {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    min-height: 2rem;
    padding: var(--space-1) var(--space-3);
    border: var(--border-width) solid var(--color-border-strong);
    border-radius: 999rem;
    background: var(--color-surface);
    color: var(--color-ink);
    font-size: var(--font-size-sm);
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition);
}
.project-filter__option::before { content: ""; width: 0.5rem; height: 0.5rem; border-radius: 50%; background: var(--tone); }
.project-filter__option:hover { border-color: var(--tone); }
.project-filter__option:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.project-filter__option[data-state="on"] { border-color: var(--tone); background: color-mix(in oklch, var(--tone) 14%, var(--color-surface)); font-weight: 600; }
.project-filter__clear { padding: var(--space-1) var(--space-2); border: none; background: none; color: var(--color-muted); font-size: var(--font-size-sm); text-decoration: underline; cursor: pointer; }
.project-filter__clear:hover { color: var(--color-accent-strong); }
</style>
