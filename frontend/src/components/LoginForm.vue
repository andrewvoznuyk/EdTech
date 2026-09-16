<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useWeblate } from '@/weblate';
import { useTokenStore } from '@/stores/login';
import { authApi } from '@/api/modules/login.api';
import { RoutePaths } from '@/constants/routes';

const emit = defineEmits<{
  (e: 'switchToRegister'): void;
  (e: 'success'): void;
}>();

const route = useRoute();
const router = useRouter();
const authStore = useTokenStore();
const { t } = useWeblate();

const email = ref('');
const password = ref('');
const showPassword = ref(false);

const twoFactorCode = ref('');
const resendSuccess = ref(false);
const errorMessage = ref('');

function extractError(err: unknown, fallback: string): string {
  if (typeof err === 'object' && err !== null && 'response' in err) {
    const res = (err as { response?: { data?: Record<string, unknown> } }).response;
    if (res?.data) {
      if (typeof res.data.message === 'string') return res.data.message;
      if (typeof res.data.detail === 'string') return res.data.detail;
      if (typeof res.data['hydra:description'] === 'string') return res.data['hydra:description'] as string;
      if (Array.isArray(res.data.violations) && res.data.violations.length > 0) {
        const first = res.data.violations[0];
        if (first && typeof first.message === 'string') return first.message;
      }
    }
  }
  if (err instanceof Error) {
    return err.message;
  }
  return fallback;
}

async function handleLogin() {
  errorMessage.value = '';
  if (!email.value.trim() || !password.value) {
    errorMessage.value = t('validators.user.email_code_required');
    return;
  }

  try {
    const res = await authStore.login(email.value.trim(), password.value);
    if ('require_2fa' in res && res.require_2fa) {
      twoFactorCode.value = '';
      return;
    }
    emit('success');
    const redirect = (route.query.redirect as string) || RoutePaths.HERO;
    router.push(redirect);
  } catch (err) {
    errorMessage.value = extractError(err, t('validators.user.invalid_code'));
  }
}

async function handleVerify2FA() {
  errorMessage.value = '';
  if (!twoFactorCode.value.trim()) {
    errorMessage.value = t('validators.user.code_required');
    return;
  }

  try {
    await authStore.verify2FA(twoFactorCode.value.trim());
    emit('success');
    const redirect = (route.query.redirect as string) || RoutePaths.HERO;
    router.push(redirect);
  } catch (err) {
    errorMessage.value = extractError(err, t('validators.user.invalid_code'));
  }
}

async function handleResend2FA() {
  if (!authStore.twoFactorChallenge?.email) return;
  errorMessage.value = '';
  resendSuccess.value = false;

  try {
    await authApi.resend2FA(authStore.twoFactorChallenge.email);
    resendSuccess.value = true;
  } catch (err) {
    errorMessage.value = extractError(err, t('validators.user.invalid_code'));
  }
}

function cancel2FA() {
  authStore.requires2FA = false;
  authStore.twoFactorChallenge = null;
  twoFactorCode.value = '';
  errorMessage.value = '';
}
</script>

<template>
  <div class="login-form-container">
    <div v-if="authStore.requires2FA" class="two-fa-section">
      <h2 class="form-title">{{ t('auth.two_factor_title') }}</h2>
      <p class="form-desc">
        {{ t('auth.two_factor_desc') }} <strong>{{ authStore.twoFactorChallenge?.email }}</strong>
      </p>

      <div v-if="errorMessage" class="alert alert-error">
        {{ errorMessage }}
      </div>

      <div v-if="resendSuccess" class="alert alert-success">
        {{ t('validators.user.code_sent') }}
      </div>

      <form @submit.prevent="handleVerify2FA" class="form-body">
        <div class="form-group">
          <label for="twoFaCode" class="form-label">{{ t('auth.two_factor_title') }}</label>
          <input
            id="twoFaCode"
            v-model="twoFactorCode"
            type="text"
            maxlength="6"
            placeholder="000000"
            class="form-input code-input"
            autofocus
          />
        </div>

        <button type="submit" class="btn btn-primary" :disabled="authStore.isLoading">
          <span v-if="authStore.isLoading" class="spinner"></span>
          <span v-else>{{ t('auth.verify_code') }}</span>
        </button>

        <div class="two-fa-actions">
          <button
            type="button"
            class="btn-link"
            :disabled="authStore.isLoading"
            @click="handleResend2FA"
          >
            {{ t('auth.resend_code') }}
          </button>
          <button
            type="button"
            class="btn-link text-muted"
            @click="cancel2FA"
          >
            {{ t('auth.back_to_login') }}
          </button>
        </div>
      </form>
    </div>

    <div v-else>
      <div v-if="errorMessage" class="alert alert-error">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleLogin" class="form-body">
        <div class="form-group">
          <label for="loginEmail" class="form-label">{{ t('auth.email_label') }}</label>
          <input
            id="loginEmail"
            v-model="email"
            type="email"
            required
            autocomplete="email"
            placeholder="name@example.com"
            class="form-input"
          />
        </div>

        <div class="form-group">
          <label for="loginPassword" class="form-label">{{ t('auth.password_label') }}</label>
          <div class="password-wrapper">
            <input
              id="loginPassword"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="form-input"
            />
            <button
              type="button"
              class="toggle-pwd-btn"
              @click="showPassword = !showPassword"
              tabindex="-1"
            >
              {{ showPassword ? t('common.hide') : t('common.show') }}
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" :disabled="authStore.isLoading">
          <span v-if="authStore.isLoading" class="spinner"></span>
          <span v-else>{{ t('common.sign_in') }}</span>
        </button>

        <div class="form-footer">
          <span>{{ t('auth.no_account') }}</span>
          <router-link :to="RoutePaths.REGISTER" class="btn-link">
            {{ t('common.register') }}
          </router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" src="@/styles/login-form.scss" scoped></style>
