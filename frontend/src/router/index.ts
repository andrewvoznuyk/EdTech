import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '@/pages/LoginPage.vue';
import RegisterPage from '@/pages/RegisterPage.vue';
import HeroPage from '@/pages/HeroPage.vue';
import { useTokenStore } from '@/stores/login';
import { setOnAuthFailure } from '@/api/client';
import { RouteNames, RoutePaths } from '@/constants/routes';

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean;
    guestOnly?: boolean;
    requiresAdmin?: boolean;
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: RoutePaths.HERO,
      name: RouteNames.HERO,
      component: HeroPage,
    },
    {
      path: RoutePaths.LOGIN,
      name: RouteNames.LOGIN,
      component: LoginPage,
      meta: { guestOnly: true },
    },
    {
      path: RoutePaths.REGISTER,
      name: RouteNames.REGISTER,
      component: RegisterPage,
      meta: { guestOnly: true },
    },
  ],
});

setOnAuthFailure(() => {
  const currentPath = router.currentRoute.value.path;
  if (currentPath !== RoutePaths.LOGIN && currentPath !== RoutePaths.REGISTER) {
    router.push({
      name: RouteNames.LOGIN,
      query: { redirect: router.currentRoute.value.fullPath },
    });
  }
});

router.beforeEach(async (to) => {
  const authStore = useTokenStore();

  if (!authStore.isInitialized) {
    await authStore.checkAuth();
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      name: RouteNames.LOGIN,
      query: { redirect: to.fullPath },
    };
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    const redirectPath = (to.query.redirect as string) || RoutePaths.HERO;
    return redirectPath;
  }

  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return { name: RouteNames.HERO };
  }
});

export default router;
