<script setup>
import { RouterLink } from 'vue-router';
import { CalendarDays, ChartColumn, CircleCheckBig, Grid2x2, Inbox, Leaf, Plus, Settings, Sun, Trophy, Users, Warehouse } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import IconButton from '../ui/IconButton.vue';
import { useProjects } from '../../composables/useProjects.js';

const emit = defineEmits(['new-project']);

const { t } = useI18n();
const { projects } = useProjects();

const MAIN = [
    { to: '/', icon: Sun, label: 'nav.today' },
    { to: '/upcoming', icon: CalendarDays, label: 'nav.upcoming' },
    { to: '/inbox', icon: Inbox, label: 'nav.inbox' },
    { to: '/matrix', icon: Grid2x2, label: 'nav.matrix' },
    { to: '/done', icon: CircleCheckBig, label: 'nav.done' },
];

const SECONDARY = [
    { to: '/herbarium', icon: Leaf, label: 'nav.herbarium' },
    { to: '/greenhouse', icon: Warehouse, label: 'nav.greenhouse' },
    { to: '/achievements', icon: Trophy, label: 'nav.achievements' },
    { to: '/friends', icon: Users, label: 'nav.friends' },
    { to: '/stats', icon: ChartColumn, label: 'nav.stats' },
    { to: '/settings', icon: Settings, label: 'nav.settings' },
];
</script>

<template>
    <div class="nav-menu">
        <RouterLink to="/" class="nav-menu__brand">mossydew</RouterLink>
        <slot />
        <ul class="nav-menu__links">
            <li v-for="link in MAIN" :key="link.to">
                <RouterLink :to="link.to" class="nav-menu__link" exact-active-class="nav-menu__link--active">
                    <component :is="link.icon" class="nav-menu__icon" size="1.125rem" :stroke-width="1.75" aria-hidden="true" />{{ t(link.label) }}
                </RouterLink>
            </li>
        </ul>
        <div class="nav-menu__projects">
            <div class="nav-menu__projects-header">
                <RouterLink to="/projects" class="nav-menu__projects-title">{{ t('nav.projects') }}</RouterLink>
                <IconButton :icon="Plus" :label="t('projects.new')" @click="emit('new-project')" />
            </div>
            <ul class="nav-menu__links">
                <li v-for="project in projects" :key="project.id">
                    <RouterLink :to="`/projects/${project.id}`" class="nav-menu__link nav-menu__link--project" active-class="nav-menu__link--active" :style="{ '--project': `var(--project-${project.color})` }">
                        <span class="nav-menu__project-name">{{ project.name }}</span>
                        <span v-if="project.openTasks" class="nav-menu__count tabular">{{ project.openTasks }}</span>
                    </RouterLink>
                </li>
            </ul>
            <p v-if="!projects.length" class="nav-menu__hint">{{ t('nav.noProjects') }}</p>
        </div>
        <ul class="nav-menu__links nav-menu__links--secondary">
            <li v-for="link in SECONDARY" :key="link.to">
                <RouterLink :to="link.to" class="nav-menu__link" active-class="nav-menu__link--active">
                    <component :is="link.icon" class="nav-menu__icon" size="1.125rem" :stroke-width="1.75" aria-hidden="true" />{{ t(link.label) }}
                </RouterLink>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.nav-menu { display: flex; flex: 1; flex-direction: column; gap: var(--space-6); min-height: 100%; }
.nav-menu__brand { padding: 0 var(--space-3); color: var(--color-ink); font-family: var(--font-display); font-size: 1.6rem; line-height: 1; text-decoration: none; }
.nav-menu__links { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; padding: 0; list-style: none; }
.nav-menu__links--secondary { margin-top: auto; }

.nav-menu__link {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-2) var(--space-3);
    border-left: 0.125rem solid transparent;
    color: var(--color-muted);
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    transition: color var(--transition), background var(--transition), border-color var(--transition);
}

.nav-menu__link:hover { color: var(--color-ink); background: var(--color-bg); }
.nav-menu__link--active { color: var(--color-ink); background: var(--color-accent-soft); border-left-color: var(--color-accent); }
.nav-menu__icon { flex-shrink: 0; color: var(--color-subtle); transition: color var(--transition); }
.nav-menu__link:hover .nav-menu__icon { color: var(--color-ink); }
.nav-menu__link--active .nav-menu__icon { color: var(--color-accent); }

.nav-menu__link--project { --project: var(--color-border-strong); }
.nav-menu__link--project::before { content: ""; flex-shrink: 0; width: 0.5rem; height: 0.5rem; margin: 0 0.3125rem; border-radius: 50%; background: var(--project); }
.nav-menu__project-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nav-menu__count { color: var(--color-subtle); font-size: var(--font-size-sm); }

.nav-menu__projects { display: flex; flex-direction: column; gap: var(--space-1); }
.nav-menu__projects-header { display: flex; align-items: center; justify-content: space-between; padding-left: var(--space-3); }
.nav-menu__projects-title { color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 600; text-decoration: none; }
.nav-menu__projects-title:hover { color: var(--color-ink); }
.nav-menu__hint { padding: 0 var(--space-3); color: var(--color-subtle); font-size: var(--font-size-sm); }
</style>
