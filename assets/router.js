import { createRouter, createWebHistory } from 'vue-router';
import AchievementsPage from './vue/pages/AchievementsPage.vue';
import CreditsPage from './vue/pages/CreditsPage.vue';
import DonePage from './vue/pages/DonePage.vue';
import FriendsPage from './vue/pages/FriendsPage.vue';
import GreenhousePage from './vue/pages/GreenhousePage.vue';
import HerbariumPage from './vue/pages/HerbariumPage.vue';
import InboxPage from './vue/pages/InboxPage.vue';
import JoinFriendPage from './vue/pages/JoinFriendPage.vue';
import MatrixPage from './vue/pages/MatrixPage.vue';
import NotFoundPage from './vue/pages/NotFoundPage.vue';
import ProjectPage from './vue/pages/ProjectPage.vue';
import ProjectsPage from './vue/pages/ProjectsPage.vue';
import SettingsPage from './vue/pages/SettingsPage.vue';
import StatsPage from './vue/pages/StatsPage.vue';
import TodayPage from './vue/pages/TodayPage.vue';
import UpcomingPage from './vue/pages/UpcomingPage.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'today', component: TodayPage },
        { path: '/today', redirect: '/' },
        { path: '/upcoming', name: 'upcoming', component: UpcomingPage },
        { path: '/inbox', name: 'inbox', component: InboxPage },
        { path: '/matrix', name: 'matrix', component: MatrixPage },
        { path: '/done', name: 'done', component: DonePage },
        { path: '/projects', name: 'projects', component: ProjectsPage },
        { path: '/projects/:id', name: 'project', component: ProjectPage, props: true },
        { path: '/herbarium', name: 'herbarium', component: HerbariumPage },
        { path: '/greenhouse', name: 'greenhouse', component: GreenhousePage },
        { path: '/achievements', name: 'achievements', component: AchievementsPage },
        { path: '/friends', name: 'friends', component: FriendsPage },
        { path: '/friends/join/:token', name: 'join-friend', component: JoinFriendPage, props: true },
        { path: '/stats', name: 'stats', component: StatsPage },
        { path: '/settings', name: 'settings', component: SettingsPage },
        { path: '/credits', name: 'credits', component: CreditsPage },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
    scrollBehavior: () => ({ top: 0 }),
});
