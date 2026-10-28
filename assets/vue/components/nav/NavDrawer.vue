<script setup>
import { watch } from 'vue';
import { useRoute } from 'vue-router';
import { X } from '../../icons.js';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import NavMenu from './NavMenu.vue';

const emit = defineEmits(['new-project']);
const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const route = useRoute();

watch(() => route.fullPath, () => { open.value = false; });

function newProject() {
    open.value = false;
    emit('new-project');
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="nav-drawer__overlay">
                <DialogContent class="nav-drawer" :aria-describedby="undefined">
                    <DialogTitle class="visually-hidden">{{ t('nav.menu') }}</DialogTitle>
                    <DialogClose class="nav-drawer__close" :aria-label="t('common.close')"><X size="1.25rem" aria-hidden="true" /></DialogClose>
                    <NavMenu @new-project="newProject" />
                </DialogContent>
            </DialogOverlay>
        </DialogPortal>
    </DialogRoot>
</template>

<style>
.nav-drawer__overlay { position: fixed; inset: 0; z-index: 50; background: var(--color-backdrop); animation: fade-in var(--transition); }

.nav-drawer {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    width: min(18rem, 85vw);
    overflow-y: auto;
    padding: var(--space-5) var(--space-4) calc(var(--space-5) + env(safe-area-inset-bottom));
    border-right: var(--border-width) solid var(--color-border);
    background: var(--color-sidebar);
    box-shadow: var(--shadow);
    animation: drawer-in 220ms ease-out;
}

.nav-drawer:focus { outline: none; }

.nav-drawer__close { position: absolute; top: var(--space-4); right: var(--space-3); display: inline-flex; padding: var(--space-2); border: none; border-radius: var(--radius); background: none; color: var(--color-muted); cursor: pointer; }
.nav-drawer__close:hover { color: var(--color-ink); background: var(--color-hover); }

@keyframes drawer-in { from { transform: translateX(-100%); } }
</style>
