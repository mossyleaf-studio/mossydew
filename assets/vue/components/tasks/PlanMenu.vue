<script setup>
import { computed, ref } from 'vue';
import { parseDate } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from '../../icons.js';
import {
    CalendarCell, CalendarCellTrigger, CalendarGrid, CalendarGridBody, CalendarGridHead, CalendarGridRow, CalendarHeadCell,
    CalendarHeader, CalendarHeading, CalendarNext, CalendarPrev, CalendarRoot, PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger,
} from 'reka-ui';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    task: { type: Object, required: true },
});
const emit = defineEmits(['plan']);
const open = defineModel('open', { type: Boolean, default: false });

const { t } = useI18n();
const picking = ref(false);

const selected = computed({
    get: () => (props.task.plannedOn ? parseDate(props.task.plannedOn) : undefined),
    set: (value) => {
        if (!value) return;
        emit('plan', 'date', value.toString());
        open.value = false;
    },
});

function choose(when) {
    emit('plan', when);
    open.value = false;
}

function onOpenChange(value) {
    open.value = value;
    if (!value) picking.value = false;
}

const SHORTCUTS = [
    { when: 'today', key: 't' },
    { when: 'tomorrow', key: 'm' },
    { when: 'next_week', key: 'n' },
];
</script>

<template>
    <PopoverRoot :open="open" @update:open="onOpenChange">
        <PopoverTrigger class="plan-menu__trigger" :aria-label="t('tasks.plan.open')">
            <CalendarDays size="1.125rem" aria-hidden="true" />
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent class="popover plan-menu" :side-offset="4" align="end">
                <p class="popover__label">{{ t('tasks.plan.title') }}</p>
                <button v-for="shortcut in SHORTCUTS" :key="shortcut.when" type="button" class="popover__item plan-menu__item" @click="choose(shortcut.when)">
                    {{ t(`tasks.plan.${shortcut.when}`) }}<kbd class="popover__hint">{{ shortcut.key }}</kbd>
                </button>
                <button type="button" class="popover__item plan-menu__item" :aria-expanded="picking" @click="picking = !picking">
                    {{ t('tasks.plan.pick') }}<kbd class="popover__hint">d</kbd>
                </button>
                <CalendarRoot v-if="picking" v-slot="{ weekDays, grid }" v-model="selected" class="plan-menu__calendar" :week-starts-on="1" fixed-weeks initial-focus>
                    <CalendarHeader class="calendar__header">
                        <CalendarPrev class="calendar__nav" :aria-label="t('ui.date.previousMonth')"><ChevronLeft size="1rem" aria-hidden="true" /></CalendarPrev>
                        <CalendarHeading class="calendar__heading" />
                        <CalendarNext class="calendar__nav" :aria-label="t('ui.date.nextMonth')"><ChevronRight size="1rem" aria-hidden="true" /></CalendarNext>
                    </CalendarHeader>
                    <CalendarGrid v-for="month in grid" :key="month.value.toString()" class="calendar__grid">
                        <CalendarGridHead>
                            <CalendarGridRow><CalendarHeadCell v-for="day in weekDays" :key="day" class="calendar__head-cell">{{ day }}</CalendarHeadCell></CalendarGridRow>
                        </CalendarGridHead>
                        <CalendarGridBody>
                            <CalendarGridRow v-for="(week, index) in month.rows" :key="index">
                                <CalendarCell v-for="day in week" :key="day.toString()" :date="day" class="calendar__cell">
                                    <CalendarCellTrigger :day="day" :month="month.value" class="calendar__day" />
                                </CalendarCell>
                            </CalendarGridRow>
                        </CalendarGridBody>
                    </CalendarGrid>
                </CalendarRoot>
                <div class="popover__separator" />
                <button type="button" class="popover__item plan-menu__item" :disabled="!task.plannedOn" @click="choose('none')">{{ t('tasks.plan.none') }}</button>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>

<style scoped>
.plan-menu__trigger { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: none; border-radius: var(--radius-pill); background: none; color: var(--color-muted); cursor: pointer; }
.plan-menu__trigger:hover, .plan-menu__trigger[data-state="open"] { color: var(--color-text); background: var(--color-hover); }
</style>

<style>
.plan-menu { display: flex; flex-direction: column; min-width: 15rem; }
.plan-menu__item { width: 100%; border: none; background: none; text-align: left; }
.plan-menu__item:hover:not(:disabled) { background: var(--color-hover); }
.plan-menu__item:disabled { color: var(--color-subtle); cursor: default; }
.plan-menu__item kbd { font-family: inherit; }
.plan-menu__calendar { padding: var(--space-2); }
</style>

