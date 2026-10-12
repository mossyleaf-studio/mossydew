<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { CalendarDays, Grid2x2, Inbox, Menu, Sun } from '@lucide/vue';
import { ConfigProvider, TooltipProvider } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import CelebrationLayer from '../components/herbarium/CelebrationLayer.vue';
import PlayerProgress from '../components/herbarium/PlayerProgress.vue';
import NavDrawer from '../components/nav/NavDrawer.vue';
import NavMenu from '../components/nav/NavMenu.vue';
import ProjectEditor from '../components/projects/ProjectEditor.vue';
import BulkBar from '../components/tasks/BulkBar.vue';
import TaskEditor from '../components/tasks/TaskEditor.vue';
import ToastHost from '../components/ui/ToastHost.vue';
import { usePlayer } from '../composables/usePlayer.js';
import { useProjects } from '../composables/useProjects.js';
import { useSelection } from '../composables/useSelection.js';
import { useSync } from '../composables/useSync.js';
import { useTaskEditor } from '../composables/useTaskEditor.js';
import { intlLocale } from '../i18n/locale.js';

const { t } = useI18n();
const route = useRoute();
const { load: loadProjects } = useProjects();
const { load: loadPlayer } = usePlayer();
const editor = useTaskEditor();
const creatingProject = ref(false);
const menuOpen = ref(false);
const selection = useSelection();

watch(() => route.path, () => selection.clear());

loadProjects();
loadPlayer();
useSync();

const TABS = [
    { to: '/', icon: Sun, label: 'nav.today' },
    { to: '/upcoming', icon: CalendarDays, label: 'nav.upcoming' },
    { to: '/matrix', icon: Grid2x2, label: 'nav.matrix' },
    { to: '/inbox', icon: Inbox, label: 'nav.inbox' },
];

const tabActive = (tab) => (tab.to === '/' ? route.path === '/' : route.path === tab.to || route.path.startsWith(`${tab.to}/`));
const elsewhere = computed(() => !TABS.some(tabActive));

function focusComposer(event) {
    const typing = event.target.closest?.('input, textarea, [contenteditable], [role="dialog"], [role="menu"]');
    if (!typing && event.key === 'Escape' && selection.count.value) {
        selection.clear();
        return;
    }
    if (typing || event.metaKey || event.ctrlKey || event.altKey || event.key !== 'n') return;
    const composer = document.getElementById('task-composer-input');
    if (composer) {
        event.preventDefault();
        composer.focus();
    }
}

onMounted(() => document.addEventListener('keydown', focusComposer));
onBeforeUnmount(() => document.removeEventListener('keydown', focusComposer));
</script>

<template>
    <ConfigProvider :locale="intlLocale()">
        <TooltipProvider :delay-duration="400">
            <div class="shell">
                <a class="shell__skip" href="#main">{{ t('nav.skip') }}</a>
                <nav class="shell__rail" :aria-label="t('nav.label')">
                    <NavMenu @new-project="creatingProject = true"><PlayerProgress /></NavMenu>
                </nav>

                <main id="main" class="shell__main">
                    <div class="shell__strip"><PlayerProgress compact /></div>
                    <RouterView />
                </main>

                <nav class="shell__tabs" :aria-label="t('nav.label')">
                    <RouterLink v-for="tab in TABS" :key="tab.to" :to="tab.to" :class="['shell__tab', { 'shell__tab--active': tabActive(tab) }]" active-class="" exact-active-class="">
                        <component :is="tab.icon" size="1.25rem" :stroke-width="1.75" aria-hidden="true" />
                        <span>{{ t(tab.label) }}</span>
                    </RouterLink>
                    <button type="button" :class="['shell__tab', { 'shell__tab--active': elsewhere }]" aria-haspopup="dialog" :aria-expanded="menuOpen" @click="menuOpen = true">
                        <Menu size="1.25rem" :stroke-width="1.75" aria-hidden="true" />
                        <span>{{ t('nav.menu') }}</span>
                    </button>
                </nav>
            </div>

            <NavDrawer v-model:open="menuOpen" @new-project="creatingProject = true" />

            <TaskEditor v-model:open="editor.state.open" :task="editor.state.task" />
            <BulkBar />
            <ProjectEditor v-model:open="creatingProject" @saved="(project) => project && $router.push(`/projects/${project.id}`)" />
            <CelebrationLayer />
            <ToastHost />
        </TooltipProvider>
    </ConfigProvider>
</template>

<style scoped>
.shell {
    display: grid;
    grid-template-columns: var(--rail-width) minmax(0, 1fr);
    min-height: 100vh;
}

.shell__skip { position: absolute; top: -10rem; left: var(--space-4); z-index: 100; padding: var(--space-2) var(--space-4); border-radius: var(--radius); background: var(--color-ink); color: var(--color-surface); font-weight: 600; }
.shell__skip:focus { top: var(--space-4); }

.shell__rail {
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
    height: 100vh;
    overflow-y: auto;
    padding: var(--space-6) var(--space-4);
    border-right: 0.0625rem solid var(--color-border);
    background: var(--color-sidebar);
}

.shell__main { display: flex; flex-direction: column; gap: var(--space-5); min-width: 0; padding: var(--space-6) var(--space-7) var(--space-7); }
.shell__main :deep(.page) { display: flex; flex-direction: column; gap: var(--space-5); width: 100%; max-width: 46rem; }
.shell__main :deep(.page--wide) { max-width: none; }

.shell__strip { display: none; }

.shell__tabs { display: none; }

@media (max-width: 72rem) {
    .shell__main { padding-inline: var(--space-6); }
}

@media (max-width: 48rem) {
    .shell { grid-template-columns: minmax(0, 1fr); }
    .shell__rail { display: none; }
    .shell__strip { display: block; padding-bottom: var(--space-4); border-bottom: 0.0625rem solid var(--color-border); }
    .shell__main { gap: var(--space-4); padding: var(--space-4) var(--space-4) calc(6rem + env(safe-area-inset-bottom)); }
    .shell__main :deep(h1) { font-size: 1.6rem; }

    .shell__tabs {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 40;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        padding: var(--space-1) var(--space-1) calc(var(--space-1) + env(safe-area-inset-bottom));
        border-top: 0.0625rem solid var(--color-border);
        background: color-mix(in oklch, var(--color-surface) 94%, transparent);
        backdrop-filter: blur(0.75rem);
    }

    .shell__tab { display: flex; flex-direction: column; align-items: center; gap: 0.125rem; padding: var(--space-2) 0; border: none; border-top: 0.125rem solid transparent; background: none; color: var(--color-subtle); font: inherit; font-size: var(--font-size-xs); font-weight: 500; text-decoration: none; cursor: pointer; }
    .shell__tab--active { border-top-color: var(--color-accent); color: var(--color-ink); }
    .shell__tab--active svg { color: var(--color-accent); }
}
</style>
