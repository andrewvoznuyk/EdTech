<script setup lang="ts">
import { ref } from 'vue';
import { useWeblate } from '@/weblate';
import { useTokenStore } from '@/stores/login';
import { RoutePaths } from '@/constants/routes';

const emit = defineEmits<{
  (e: 'switchToLogin'): void;
  (e: 'success'): void;
}>();

const authStore = useTokenStore();
const { t } = useWeblate();

const firstName = ref('');
const lastName = ref('');
const email = ref('');
const password = ref('');
const passwordConfirm = ref('');
const showPassword = ref(false);

const registrationSuccess = ref(false);
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

async function handleRegister() {
  errorMessage.value = '';
  registrationSuccess.value = false;

  if (!email.value.trim() || !password.value) {
    errorMessage.value = t('validators.user.email_code_required');
    return;
  }

  if (password.value.length < 8) {
    errorMessage.value = t('validators.user.password_too_short');
    return;
  }

  if (password.value !== passwordConfirm.value) {
    errorMessage.value = t('validators.user.password_mismatch');
    return;
  }

  try {
    const payload = {
      email: email.value.trim(),
      password: password.value,
      userProfile:
        firstName.value.trim() || lastName.value.trim()
          ? {
              firstName: firstName.value.trim() || undefined,
              lastName: lastName.value.trim() || undefined,
            }
          : undefined,
    };

    await authStore.register(payload);
    registrationSuccess.value = true;
    password.value = '';
    passwordConfirm.value = '';
    emit('success');
  } catch (err) {
    errorMessage.value = extractError(err, t('validators.user.email_unique'));
  }
}
</script>

<template>
  <div class="register-form-container">
    <div v-if="registrationSuccess" class="success-section">
      <div class="success-icon">✓</div>
      <h2 class="form-title">{{ t('auth.register_success_title') }}</h2>
      <p class="form-desc">
        {{ t('auth.register_success_desc') }}
      </p>
      <router-link :to="RoutePaths.LOGIN" class="btn btn-primary">
        {{ t('auth.proceed_to_login') }}
      </router-link>
    </div>

    <div v-else>
      <div v-if="errorMessage" class="alert alert-error">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleRegister" class="form-body">
        <div class="form-row">
          <div class="form-group">
            <label for="regFirstName" class="form-label">{{ t('auth.first_name_label') }}</label>
            <input
              id="regFirstName"
              v-model="firstName"
              type="text"
              placeholder="John"
              class="form-input"
            />
          </div>
          <div class="form-group">
            <label for="regLastName" class="form-label">{{ t('auth.last_name_label') }}</label>
            <input
              id="regLastName"
              v-model="lastName"
              type="text"
              placeholder="Doe"
              class="form-input"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="regEmail" class="form-label">{{ t('auth.email_label') }}</label>
          <input
            id="regEmail"
            v-model="email"
            type="email"
            required
            autocomplete="email"
            placeholder="name@example.com"
            class="form-input"
          />
        </div>

        <div class="form-group">
          <label for="regPassword" class="form-label">{{ t('auth.password_label') }}</label>
          <div class="password-wrapper">
            <input
              id="regPassword"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              minlength="8"
              autocomplete="new-password"
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

        <div class="form-group">
          <label for="regPasswordConfirm" class="form-label">{{ t('auth.confirm_password_label') }}</label>
          <input
            id="regPasswordConfirm"
            v-model="passwordConfirm"
            :type="showPassword ? 'text' : 'password'"
            required
            minlength="8"
            autocomplete="new-password"
            placeholder="••••••••"
            class="form-input"
          />
        </div>

        <button type="submit" class="btn btn-primary" :disabled="authStore.isLoading">
          <span v-if="authStore.isLoading" class="spinner"></span>
          <span v-else>{{ t('common.register') }}</span>
        </button>

        <div class="form-footer">
          <span>{{ t('auth.have_account') }}</span>
          <router-link :to="RoutePaths.LOGIN" class="btn-link">
            {{ t('common.sign_in') }}
          </router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<style lang="scss" src="@/styles/register-form.scss" scoped></style>
