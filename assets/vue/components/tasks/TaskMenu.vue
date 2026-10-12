<script setup>
import { computed } from 'vue';
import { Ellipsis } from '@lucide/vue';
import {
    DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuPortal, DropdownMenuRadioGroup, DropdownMenuRadioItem,
    DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger,
} from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { QUADRANTS } from '../../tasks/quadrants.js';

const props = defineProps({
    task: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});
const emit = defineEmits(['edit', 'classify', 'addSubtask', 'promote', 'select']);

const { t } = useI18n();
const UNSORTED = 'unsorted';

const quadrant = computed({
    get: () => props.task.quadrant ?? UNSORTED,
    set: (value) => emit('classify', value === UNSORTED ? null : value),
});
</script>

<template>
    <DropdownMenuRoot>
        <DropdownMenuTrigger class="task-menu__trigger" :aria-label="t('tasks.menu.open', { title: task.title })">
            <Ellipsis size="1.125rem" aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent class="popover task-menu" :side-offset="4" align="end">
                <DropdownMenuItem class="popover__item" @select="emit('edit')">{{ t('tasks.menu.edit') }}<kbd class="popover__hint">e</kbd></DropdownMenuItem>
                <DropdownMenuItem v-if="!task.parentId" class="popover__item" @select="emit('addSubtask')">{{ t('tasks.menu.addSubtask') }}<kbd class="popover__hint">s</kbd></DropdownMenuItem>
                <DropdownMenuItem v-else class="popover__item" @select="emit('promote')">{{ t('tasks.menu.promote') }}</DropdownMenuItem>
                <DropdownMenuItem class="popover__item" @select="emit('select')">{{ t(selected ? 'tasks.menu.deselect' : 'tasks.menu.select') }}<kbd class="popover__hint">x</kbd></DropdownMenuItem>
                <DropdownMenuSeparator class="popover__separator" />
                <DropdownMenuLabel class="popover__label">{{ t('matrix.title') }}</DropdownMenuLabel>
                <DropdownMenuRadioGroup v-model="quadrant">
                    <DropdownMenuRadioItem v-for="known in QUADRANTS" :key="known.value" :value="known.value" class="popover__item">
                        <span :class="['task-menu__dot', `task-menu__dot--${known.value}`]" aria-hidden="true" />
                        {{ t(`matrix.quadrants.${known.value}.name`) }}
                        <span class="popover__hint">{{ t(`matrix.quadrants.${known.value}.plain`) }}</span>
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem :value="UNSORTED" class="popover__item">
                        <span class="task-menu__dot task-menu__dot--unsorted" aria-hidden="true" />{{ t('matrix.unsorted') }}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>

<style scoped>
.task-menu__trigger { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border: none; border-radius: var(--radius-pill); background: none; color: var(--color-muted); cursor: pointer; }
.task-menu__trigger:hover, .task-menu__trigger[data-state="open"] { color: var(--color-text); background: var(--color-hover); }
</style>

<style>
.task-menu { min-width: 17rem; }
.task-menu kbd { font-family: inherit; }
.task-menu__dot { width: 0.625rem; height: 0.625rem; border-radius: 50%; flex-shrink: 0; }
.task-menu__dot--do_first { background: var(--quadrant-do-first); }
.task-menu__dot--schedule { background: var(--quadrant-schedule); }
.task-menu__dot--delegate { background: var(--quadrant-delegate); }
.task-menu__dot--eliminate { background: var(--quadrant-eliminate); }
.task-menu__dot--unsorted { background: var(--quadrant-unsorted); }
</style>
