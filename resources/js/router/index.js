import { createRouter, createWebHistory } from 'vue-router';
import { installGuards } from './guards';
import { routes } from './routes';

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (to, from, savedPosition) => savedPosition ?? (to.path !== from.path ? { top: 0 } : false),
});

installGuards(router);

export default router;
