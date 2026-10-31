<script setup>
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { Skull } from '../../icons.js';
import { cellName } from '../../mysteries/board.js';

const props = defineProps({
    people: { type: Array, required: true },
    victim: { type: Number, required: true },
    placements: { type: Array, required: true },
    size: { type: Number, required: true },
});
const picked = defineModel('picked', { type: Number, default: null });

const { t } = useI18n();

const placedCount = computed(() => props.placements.filter((cell) => cell !== null).length);

const initial = (person) => [...props.people[person]][0].toUpperCase();

function label(person) {
    const role = person === props.victim ? 'victim' : 'suspect';
    const cell = props.placements[person];

    return cell === null
        ? t(`mysteries.palette.state.${role}.unplaced`, { name: props.people[person] })
        : t(`mysteries.palette.state.${role}.placed`, { name: props.people[person], cell: cellName(cell, props.size) });
}

const pick = (value) => { picked.value = value ?? null; };
</script>

<template>
    <div class="palette">
        <ToggleGroupRoot type="single" class="palette__chips" :model-value="picked" :aria-label="t('mysteries.palette.label')" @update:model-value="pick">
            <ToggleGroupItem
                v-for="(name, person) in people"
                :key="person"
                :value="person"
                :class="['palette__chip', { 'palette__chip--placed': placements[person] !== null }]"
                :aria-label="label(person)"
                data-test="person"
            >
                <span :class="['palette__avatar', { 'palette__avatar--victim': person === victim }]" aria-hidden="true">
                    <Skull v-if="person === victim" size="1rem" :stroke-width="2.5" />
                    <template v-else>{{ initial(person) }}</template>
                </span>
                <span class="palette__name">{{ name }}</span>
                <span v-if="person === victim" class="palette__tag" aria-hidden="true">{{ t('mysteries.palette.victim') }}</span>
                <span v-if="placements[person] !== null" class="palette__cell tabular" aria-hidden="true">{{ cellName(placements[person], size) }}</span>
            </ToggleGroupItem>
        </ToggleGroupRoot>
        <p class="palette__hint">
            {{ picked === null ? t('mysteries.palette.hint') : t('mysteries.palette.picked', { name: people[picked] }) }}
            <span class="palette__progress tabular">{{ t('mysteries.palette.progress', { placed: placedCount, total: people.length }) }}</span>
        </p>
    </div>
</template>

<style scoped>
.palette { display: flex; flex-direction: column; gap: var(--space-2); }
.palette__chips { display: flex; flex-wrap: wrap; gap: var(--space-2); }

.palette__chip {
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
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
}

.palette__chip:hover { border-color: var(--color-ink); }
.palette__chip[data-state='on'] { border-color: var(--color-accent); background: var(--color-accent-soft); box-shadow: var(--focus-ring); }

.palette__avatar { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 50%; background: var(--color-accent-soft); color: var(--color-accent-strong); font-size: var(--font-size-sm); font-weight: 700; line-height: 1; transition: background var(--transition), color var(--transition); }
.palette__chip--placed .palette__avatar { background: var(--color-accent); color: var(--color-on-accent); }
.palette__avatar--victim, .palette__chip--placed .palette__avatar--victim { background: var(--color-ink); color: var(--color-surface); }

.palette__name { font-weight: 500; }
.palette__tag { color: var(--color-muted); font-size: var(--font-size-xs); }
.palette__cell { padding: 0 var(--space-2); border-radius: var(--radius-pill); background: var(--color-bg); color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; }

.palette__hint { color: var(--color-muted); font-size: var(--font-size-md); }
.palette__progress { display: block; color: var(--color-subtle); font-size: var(--font-size-sm); }

@media (max-width: 30rem) {
    .palette__chips { gap: var(--space-1) var(--space-2); }
    .palette__chip { gap: var(--space-1); min-height: 2rem; padding-right: var(--space-2); font-size: var(--font-size-sm); }
    .palette__avatar { width: 1.5rem; height: 1.5rem; font-size: var(--font-size-xs); }
}
</style>
