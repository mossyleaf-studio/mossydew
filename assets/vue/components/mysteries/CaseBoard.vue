<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowUp, X } from '../../icons.js';
import { blocksCell, cellName, columnLetter, roomTints, wallsOf } from '../../mysteries/board.js';
import { FURNITURE } from '../../mysteries/furniture.js';
import { occupantOf } from '../../mysteries/notes.js';

const props = defineProps({
    investigation: { type: Object, required: true },
    placements: { type: Array, required: true },
    crosses: { type: Array, default: () => [] },
    murderer: { type: Number, default: null },
    readonly: { type: Boolean, default: false },
    placing: { type: Boolean, default: false },
});
const emit = defineEmits(['tap']);

const STEPS = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

const { t } = useI18n();
const grid = ref(null);
const active = ref(null);

const size = computed(() => props.investigation.size);
const tints = computed(() => roomTints(props.investigation.rooms, size.value));
const crossed = computed(() => new Set(props.crosses));

const cells = computed(() => {
    const { rooms, kinds, furniture } = props.investigation;

    return rooms.map((room, cell) => {
        const piece = furniture[cell];

        return {
            cell,
            kind: kinds[room],
            piece,
            blocked: blocksCell(piece),
            person: occupantOf(props.placements, cell),
            crossed: crossed.value.has(cell),
            tint: tints.value[room],
            ...wallsOf(rooms, size.value, cell),
            lastColumn: cell % size.value === size.value - 1,
            lastRow: cell + size.value >= rooms.length,
        };
    });
});

function roleOf(person) {
    if (person === props.murderer) return 'murderer';

    return person === props.investigation.victim ? 'victim' : 'suspect';
}

const initial = (person) => [...props.investigation.people[person]][0].toUpperCase();

function parts(view) {
    return [
        cellName(view.cell, size.value),
        t(`mysteries.rooms.${view.kind}.name`),
        view.piece && t(view.blocked ? 'mysteries.board.blocking' : 'mysteries.board.seat', { name: t(`mysteries.furniture.${view.piece}.name`) }),
        view.person !== null && t(`mysteries.board.here.${roleOf(view.person)}`, { name: props.investigation.people[view.person] }),
        view.crossed && t('mysteries.board.crossed'),
    ].filter(Boolean);
}

const status = computed(() => (active.value === null ? t('mysteries.board.hint') : parts(cells.value[active.value]).join(' · ')));

function choose(view) {
    active.value = view.cell;
    if (!props.readonly && !view.blocked) emit('tap', view.cell);
}

function move(event) {
    const step = STEPS[event.key];
    if (!step) return;
    event.preventDefault();
    const cell = Number(event.target.dataset.cell);
    const column = Math.min(size.value - 1, Math.max(0, (cell % size.value) + step[0]));
    const row = Math.min(size.value - 1, Math.max(0, Math.floor(cell / size.value) + step[1]));
    grid.value.children[row * size.value + column].focus();
}
</script>

<template>
    <div :class="['board', { 'board--placing': placing, 'board--readonly': readonly }]" :style="{ '--size': size }" data-test="board">
        <p class="board__north"><ArrowUp size="0.875rem" :stroke-width="2.5" aria-hidden="true" />{{ t('mysteries.board.north') }}</p>
        <div class="board__frame">
            <ol class="board__columns" aria-hidden="true">
                <li v-for="column in size" :key="column">{{ columnLetter(column - 1) }}</li>
            </ol>
            <ol class="board__rows" aria-hidden="true">
                <li v-for="row in size" :key="row" class="tabular">{{ row }}</li>
            </ol>
            <div ref="grid" class="board__grid" role="group" :aria-label="t('mysteries.board.label')" @keydown="move">
                <button
                    v-for="view in cells"
                    :key="view.cell"
                    type="button"
                    :class="[
                        'board__cell',
                        `board__cell--tint-${view.tint}`,
                        {
                            'board__cell--blocked': view.blocked,
                            'board__cell--wall-right': view.right,
                            'board__cell--wall-bottom': view.bottom,
                            'board__cell--last-column': view.lastColumn,
                            'board__cell--last-row': view.lastRow,
                            'board__cell--occupied': view.person !== null,
                            'board__cell--crossed': view.crossed,
                            'board__cell--active': view.cell === active,
                        },
                    ]"
                    :tabindex="view.cell === (active ?? 0) ? 0 : -1"
                    :aria-label="parts(view).join(', ')"
                    :aria-disabled="view.blocked || undefined"
                    :data-cell="view.cell"
                    data-test="cell"
                    @click="choose(view)"
                    @focus="active = view.cell"
                >
                    <span class="board__body" aria-hidden="true">
                        <component :is="FURNITURE[view.piece]" v-if="view.piece" :class="['board__furniture', view.blocked ? 'board__furniture--blocking' : 'board__furniture--seat']" :stroke-width="2" />
                        <X v-if="view.crossed" class="board__cross" :stroke-width="2.5" />
                        <span v-if="view.person !== null" :class="['board__person', `board__person--${roleOf(view.person)}`]">{{ initial(view.person) }}</span>
                    </span>
                </button>
            </div>
        </div>
        <p class="board__status" aria-live="polite" data-test="board-status">{{ status }}</p>
    </div>
</template>

<style scoped>
.board {
    --wall: var(--color-ink);
    --line: var(--color-border-strong);
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    width: 100%;
    max-width: calc(var(--size) * 4rem + 1.25rem);
}

.board__north { display: inline-flex; align-items: center; gap: var(--space-1); align-self: flex-end; color: var(--color-muted); font-size: var(--font-size-2xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }

.board__frame { display: grid; grid-template-columns: 1.25rem minmax(0, 1fr); }

.board__columns, .board__rows { display: grid; margin: 0; padding: 0; color: var(--color-subtle); font-size: var(--font-size-2xs); font-weight: 600; line-height: 1; list-style: none; }
.board__columns { grid-column: 2; grid-template-columns: repeat(var(--size), minmax(0, 1fr)); padding: 0 calc(var(--border-width) * 2) var(--space-1); }
.board__columns li { text-align: center; }
.board__rows { grid-column: 1; grid-row: 2; grid-template-rows: repeat(var(--size), minmax(0, 1fr)); padding: calc(var(--border-width) * 2) 0; }
.board__rows li { display: grid; place-items: center; }

.board__grid { grid-column: 2; grid-row: 2; display: grid; grid-template-columns: repeat(var(--size), minmax(0, 1fr)); overflow: hidden; border: calc(var(--border-width) * 2) solid var(--wall); border-radius: var(--radius-sm); background: var(--color-surface); }

.board__cell {
    --tint: var(--color-surface);
    position: relative;
    min-width: 0;
    margin: 0;
    padding: 0;
    aspect-ratio: 1;
    border: 0 solid var(--line);
    border-right-width: var(--border-width);
    border-bottom-width: var(--border-width);
    border-radius: 0;
    background-color: var(--tint);
    color: var(--color-ink);
    cursor: pointer;
    user-select: none;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
    transition: background-color var(--transition);
}

.board__cell--tint-1 { --tint: color-mix(in oklch, var(--color-accent) 9%, var(--color-surface)); }
.board__cell--tint-2 { --tint: color-mix(in oklch, var(--color-accent) 19%, var(--color-surface)); }
.board__cell--wall-right { border-right: calc(var(--border-width) * 2) solid var(--wall); }
.board__cell--wall-bottom { border-bottom: calc(var(--border-width) * 2) solid var(--wall); }
.board__cell--last-column { border-right-width: 0; }
.board__cell--last-row { border-bottom-width: 0; }

.board__cell--blocked {
    background-image: repeating-linear-gradient(135deg, transparent 0 0.25rem, color-mix(in oklch, var(--color-ink) 10%, transparent) 0.25rem 0.3125rem);
    cursor: not-allowed;
}

.board--placing .board__cell:not(.board__cell--blocked):hover { background-color: color-mix(in oklch, var(--color-accent) 28%, var(--tint)); }
.board--readonly .board__cell { cursor: default; }
.board__cell:focus-visible { z-index: 1; outline: 0.125rem solid var(--color-accent); outline-offset: -0.125rem; }
.board__cell--active { box-shadow: inset 0 0 0 0.125rem var(--color-accent); }

.board__body { position: absolute; inset: 0; container-type: inline-size; pointer-events: none; }
.board__furniture, .board__cross, .board__person { position: absolute; inset: 0; margin: auto; }
.board__furniture { width: 64%; height: 64%; transition: opacity var(--transition); }
.board__furniture--seat { color: var(--color-accent-strong); }
.board__furniture--blocking { color: var(--color-muted); }
.board__cell--occupied .board__furniture, .board__cell--crossed .board__furniture { opacity: 0.3; }
.board__cross { width: 54%; height: 54%; color: var(--color-muted); }

.board__person { display: grid; place-items: center; width: 82%; height: 82%; border-radius: 50%; background: var(--color-accent); color: var(--color-on-accent); font-size: 44cqw; font-weight: 700; line-height: 1; }
.board__person--victim { background: var(--color-ink); color: var(--color-surface); }
.board__person--murderer { background: var(--color-danger); color: var(--color-surface); outline: 0.125rem solid var(--color-danger); outline-offset: 0.125rem; }

.board__status { min-height: 2.75rem; color: var(--color-muted); font-size: var(--font-size-md); }
</style>
