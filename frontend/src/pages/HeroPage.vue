<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useWeblate } from '@/weblate';
import { useTokenStore } from '@/stores/login';
import { RoutePaths } from '@/constants/routes';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const router = useRouter();
const authStore = useTokenStore();
const { t } = useWeblate();

async function handleLogout() {
  await authStore.logout();
  router.push(RoutePaths.LOGIN);
}
</script>

<template>
  <div class="hero-container">
    <div class="hero-card">
      <div class="card-top-bar">
        <LanguageSwitcher />
      </div>

      <h1 class="brand-title">{{ t('common.platform') }}</h1>

      <div v-if="authStore.isAuthenticated" class="user-profile-section">
        <div class="user-avatar">
          {{ authStore.user?.email?.charAt(0).toUpperCase() || 'U' }}
        </div>
        <h2 class="user-greeting">
          {{ t('hero.welcome') }} <span>{{ authStore.user?.email }}</span>!
        </h2>

        <div class="user-details">
          <div class="detail-row">
            <span class="detail-label">{{ t('hero.user_id') }}</span>
            <span class="detail-value font-mono">{{ authStore.user?.id || '—' }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">{{ t('hero.roles') }}</span>
            <div class="roles-container">
              <span
                v-for="role in authStore.user?.roles"
                :key="role"
                class="role-badge"
              >
                {{ role }}
              </span>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="btn btn-logout"
          :disabled="authStore.isLoading"
          @click="handleLogout"
        >
          {{ t('common.logout') }}
        </button>
      </div>

      <div v-else class="guest-section">
        <p class="guest-desc">
          {{ t('hero.guest_desc') }}
        </p>

        <div class="guest-actions">
          <router-link :to="RoutePaths.LOGIN" class="btn btn-primary">
            {{ t('common.sign_in') }}
          </router-link>
          <router-link :to="RoutePaths.REGISTER" class="btn btn-secondary">
            {{ t('common.register') }}
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" src="@/styles/hero-page.scss" scoped></style>