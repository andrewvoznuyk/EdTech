import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { authApi } from '@/api/modules/login.api';
import {
  clearAccessToken,
  getAccessToken,
  getUserFromToken,
  hasRefreshTokenCookie,
  isTokenExpired,
  onTokenChange,
  setAccessToken,
} from '@/api/token';
import {
  isTwoFactorChallenge,
  type AuthResponse,
  type AuthUser,
  type RegisterPayload,
  type RegisterResponse,
  type TwoFactorChallengeResponse,
} from '@/types/auth';
import { isSupportedLocale } from '@/weblate/types';
import { setWeblateLocale } from '@/weblate';

export const useTokenStore = defineStore('token', () => {
  const token = ref<string | null>(getAccessToken());
  const isLoading = ref(false);
  const isInitialized = ref(false);

  const requires2FA = ref(false);
  const twoFactorChallenge = ref<TwoFactorChallengeResponse | null>(null);

  onTokenChange((newToken) => {
    token.value = newToken;
  });

  const isAuthenticated = computed(() => !!token.value && !isTokenExpired(token.value));

  const user = computed<AuthUser | null>(() => getUserFromToken(token.value));

  const isAdmin = computed(() => user.value?.roles.includes('ROLE_ADMIN') ?? false);

  const isCompany = computed(() => user.value?.roles.includes('ROLE_COMPANY') ?? false);

  function setToken(newToken: string | null): void {
    token.value = newToken;
    setAccessToken(newToken);
    if (newToken) {
      const authUser = getUserFromToken(newToken);
      if (authUser?.locale && isSupportedLocale(authUser.locale)) {
        setWeblateLocale(authUser.locale, { persist: false });
      }
    }
  }

  async function login(email: string, password: string): Promise<AuthResponse> {
    isLoading.value = true;
    requires2FA.value = false;
    twoFactorChallenge.value = null;

    try {
      const response = await authApi.login({ email, password });
      const data = response.data;

      if (isTwoFactorChallenge(data)) {
        requires2FA.value = true;
        twoFactorChallenge.value = data;
        return data;
      }

      setToken(data.token);
      return data;
    } finally {
      isLoading.value = false;
    }
  }

  async function register(payload: RegisterPayload): Promise<RegisterResponse> {
    isLoading.value = true;
    try {
      const response = await authApi.register(payload);
      return response.data;
    } finally {
      isLoading.value = false;
    }
  }

  async function verify2FA(code: string, method?: string): Promise<string> {
    if (!twoFactorChallenge.value?.email) {
      throw new Error('No pending 2FA challenge found.');
    }

    isLoading.value = true;
    try {
      const response = await authApi.verify2FA({
        email: twoFactorChallenge.value.email,
        code,
        method: method || twoFactorChallenge.value.method,
      });

      const newToken = response.data.token;
      setToken(newToken);
      requires2FA.value = false;
      twoFactorChallenge.value = null;
      return newToken;
    } finally {
      isLoading.value = false;
    }
  }

  async function loginWithGoogle(idToken: string): Promise<string> {
    isLoading.value = true;
    try {
      const response = await authApi.googleLogin(idToken);
      const newToken = response.data.token;
      setToken(newToken);
      return newToken;
    } finally {
      isLoading.value = false;
    }
  }

  async function refreshTokens(): Promise<string | null> {
    try {
      const response = await authApi.refresh();
      const newToken = response.data.token;
      setToken(newToken);
      return newToken;
    } catch (error) {
      setToken(null);
      throw error;
    }
  }

  async function checkAuth(): Promise<boolean> {
    if (isAuthenticated.value) {
      isInitialized.value = true;
      return true;
    }

    if (!hasRefreshTokenCookie()) {
      isInitialized.value = true;
      return false;
    }

    isLoading.value = true;
    try {
      await refreshTokens();
      return true;
    } catch {
      setToken(null);
      return false;
    } finally {
      isLoading.value = false;
      isInitialized.value = true;
    }
  }

  async function logout(): Promise<void> {
    isLoading.value = true;
    try {
      await authApi.logout();
    } catch {} finally {
      clearAccessToken();
      setToken(null);
      requires2FA.value = false;
      twoFactorChallenge.value = null;
      isLoading.value = false;
    }
  }

  return {
    token,
    user,
    isAuthenticated,
    isAdmin,
    isCompany,
    isLoading,
    isInitialized,
    requires2FA,
    twoFactorChallenge,
    setToken,
    login,
    register,
    verify2FA,
    loginWithGoogle,
    refreshTokens,
    checkAuth,
    logout,
  };
});

export const useAuthStore = useTokenStore;