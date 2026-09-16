<script setup lang="ts">
import { useWeblate } from '@/weblate';
import { type SupportedLocale } from '@/weblate/types';
import { useTokenStore } from '@/stores/login';

const { locale, setLocale, localeOptions } = useWeblate();
const authStore = useTokenStore();

async function handleSelectLocale(code: SupportedLocale) {
  if (locale.value === code) return;
  await setLocale(code, {
    persist: true,
    syncBackend: authStore.isAuthenticated,
    userId: authStore.user?.id,
  });
}
</script>

<template>
  <div class="lang-switcher" role="group" aria-label="Language selector">
    <button
      v-for="opt in localeOptions"
      :key="opt.code"
      type="button"
      class="lang-btn"
      :class="{ active: locale === opt.code }"
      @click="handleSelectLocale(opt.code)"
    >
      {{ opt.shortLabel }}
    </button>
  </div>
</template>

<style lang="scss" src="@/styles/language-switcher.scss" scoped></style>
