<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useWeblate } from '@/weblate';
import RegisterForm from '@/components/RegisterForm.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { RoutePaths } from '@/constants/routes';

const router = useRouter();
const { t } = useWeblate();

function handleSwitchToLogin() {
  router.push(RoutePaths.LOGIN);
}
</script>

<template>
  <div class="auth-container">
    <div class="auth-card">
      <div class="card-top-bar">
        <LanguageSwitcher />
      </div>

      <div class="auth-header">
        <h1 class="brand-title">{{ t('common.brand') }}</h1>
      </div>

      <RegisterForm @switch-to-login="handleSwitchToLogin" />
    </div>
  </div>
</template>

<style lang="scss" src="@/styles/auth-page.scss" scoped></style>
