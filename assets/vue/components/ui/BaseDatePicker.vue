<script setup>
import { computed } from 'vue';
import { parseDate } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight, X } from '../../icons.js';
import {
    DatePickerCalendar, DatePickerCell, DatePickerCellTrigger, DatePickerContent, DatePickerField, DatePickerGrid,
    DatePickerGridBody, DatePickerGridHead, DatePickerGridRow, DatePickerHeadCell, DatePickerHeader, DatePickerHeading,
    DatePickerInput, DatePickerNext, DatePickerPrev, DatePickerRoot, DatePickerTrigger,
} from 'reka-ui';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const model = defineModel({ type: [String, null], default: null });

const date = computed({
    get: () => (model.value ? parseDate(model.value) : undefined),
    set: (value) => { model.value = value ? value.toString().slice(0, 10) : null; },
});
</script>

<template>
    <DatePickerRoot v-model="date" granularity="day" :week-starts-on="1" fixed-weeks>
        <DatePickerField v-slot="{ segments }" class="control date-field">
            <template v-for="item in segments" :key="item.part">
                <DatePickerInput v-if="item.part === 'literal'" :part="item.part" class="date-field__literal">{{ item.value }}</DatePickerInput>
                <DatePickerInput v-else :part="item.part" class="date-field__segment">{{ item.value }}</DatePickerInput>
            </template>
            <button v-if="model" type="button" class="date-field__clear" :aria-label="t('ui.date.clear')" @click="model = null"><X size="0.875rem" aria-hidden="true" /></button>
            <DatePickerTrigger class="date-field__trigger" :aria-label="t('ui.date.open')"><CalendarDays size="1rem" aria-hidden="true" /></DatePickerTrigger>
        </DatePickerField>
        <DatePickerContent class="popover date-field__content" :side-offset="4" align="start">
            <DatePickerCalendar v-slot="{ weekDays, grid }">
                <DatePickerHeader class="calendar__header">
                    <DatePickerPrev class="calendar__nav" :aria-label="t('ui.date.previousMonth')"><ChevronLeft size="1rem" aria-hidden="true" /></DatePickerPrev>
                    <DatePickerHeading class="calendar__heading" />
                    <DatePickerNext class="calendar__nav" :aria-label="t('ui.date.nextMonth')"><ChevronRight size="1rem" aria-hidden="true" /></DatePickerNext>
                </DatePickerHeader>
                <DatePickerGrid v-for="month in grid" :key="month.value.toString()" class="calendar__grid">
                    <DatePickerGridHead>
                        <DatePickerGridRow><DatePickerHeadCell v-for="day in weekDays" :key="day" class="calendar__head-cell">{{ day }}</DatePickerHeadCell></DatePickerGridRow>
                    </DatePickerGridHead>
                    <DatePickerGridBody>
                        <DatePickerGridRow v-for="(week, index) in month.rows" :key="index">
                            <DatePickerCell v-for="day in week" :key="day.toString()" :date="day" class="calendar__cell">
                                <DatePickerCellTrigger :day="day" :month="month.value" class="calendar__day" />
                            </DatePickerCell>
                        </DatePickerGridRow>
                    </DatePickerGridBody>
                </DatePickerGrid>
            </DatePickerCalendar>
        </DatePickerContent>
    </DatePickerRoot>
</template>

