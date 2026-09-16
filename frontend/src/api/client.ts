import axios from 'axios';
import type { InternalAxiosRequestConfig } from 'axios';
import { clearAccessToken, getAccessToken, hasRefreshTokenCookie, setAccessToken } from './token';
import type { RefreshTokenResponse } from '@/types/auth';
import { ApiEndpoints } from '@/constants/api';
import { RoutePaths } from '@/constants/routes';

export interface CustomAxiosRequestConfig extends InternalAxiosRequestConfig {
  _isRetry?: boolean;
  skipAuthRefresh?: boolean;
}

declare module 'axios' {
  export interface AxiosRequestConfig {
    _isRetry?: boolean;
    skipAuthRefresh?: boolean;
  }
  export interface InternalAxiosRequestConfig {
    _isRetry?: boolean;
    skipAuthRefresh?: boolean;
  }
}

const BASE_URL = import.meta.env.VITE_API_URL || '/api';

export const rawClient = axios.create({
  baseURL: BASE_URL,
  timeout: 10000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json, application/ld+json',
  },
});

export const apiClient = axios.create({
  baseURL: BASE_URL,
  timeout: 10000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json, application/ld+json',
  },
});

apiClient.interceptors.request.use((config) => {
  const token = getAccessToken();
  if (token && !config.headers.Authorization) {
    if (typeof config.headers.set === 'function') {
      config.headers.set('Authorization', `Bearer ${token}`);
    } else {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
  }
  return config;
});

let refreshPromise: Promise<string> | null = null;

export async function refreshAuthToken(): Promise<string> {
  if (!hasRefreshTokenCookie()) {
    clearAccessToken();
    throw new Error('Missing refresh token');
  }

  if (refreshPromise) {
    return refreshPromise;
  }

  refreshPromise = (async () => {
    try {
      const response = await rawClient.post<RefreshTokenResponse>(ApiEndpoints.TOKEN_REFRESH);
      const newToken = response.data?.token;

      if (!newToken) {
        throw new Error('Refresh response missing access token');
      }

      setAccessToken(newToken);
      return newToken;
    } catch (error) {
      clearAccessToken();
      throw error;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

interface QueueItem {
  resolve: (token: string) => void;
  reject: (error: unknown) => void;
}

let isRefreshing = false;
let failedQueue: QueueItem[] = [];

const processQueue = (error: unknown, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else if (token) {
      prom.resolve(token);
    }
  });
  failedQueue = [];
};

const AUTH_ENDPOINTS = [
  ApiEndpoints.LOGIN_CHECK,
  ApiEndpoints.TOKEN_REFRESH,
  ApiEndpoints.TOKEN_LOGOUT,
  ApiEndpoints.TWO_FACTOR_CHECK,
];

function isAuthEndpoint(url?: string): boolean {
  if (!url) return false;
  return AUTH_ENDPOINTS.some((endpoint) => url.includes(endpoint));
}

type AuthFailureCallback = () => void;
let authFailureHandler: AuthFailureCallback | null = null;

export function setOnAuthFailure(handler: AuthFailureCallback): void {
  authFailureHandler = handler;
}

function handleAuthFailure(): void {
  if (authFailureHandler) {
    authFailureHandler();
  } else if (typeof window !== 'undefined' && window.location.pathname !== RoutePaths.LOGIN) {
    const redirect = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = `${RoutePaths.LOGIN}?redirect=${redirect}`;
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config as CustomAxiosRequestConfig | undefined;

    if (!originalRequest) {
      return Promise.reject(error);
    }

    const is401 = error.response?.status === 401;
    const canRetry =
      is401 &&
      !originalRequest._isRetry &&
      !originalRequest.skipAuthRefresh &&
      !isAuthEndpoint(originalRequest.url) &&
      hasRefreshTokenCookie();

    if (canRetry) {
      if (isRefreshing) {
        return new Promise<string>((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((newToken) => {
            originalRequest._isRetry = true;
            if (originalRequest.headers) {
              if (typeof originalRequest.headers.set === 'function') {
                originalRequest.headers.set('Authorization', `Bearer ${newToken}`);
              } else {
                originalRequest.headers['Authorization'] = `Bearer ${newToken}`;
              }
            }
            return apiClient(originalRequest);
          })
          .catch((queueError) => Promise.reject(queueError));
      }

      originalRequest._isRetry = true;
      isRefreshing = true;

      try {
        const newToken = await refreshAuthToken();
        processQueue(null, newToken);

        if (originalRequest.headers) {
          if (typeof originalRequest.headers.set === 'function') {
            originalRequest.headers.set('Authorization', `Bearer ${newToken}`);
          } else {
            originalRequest.headers['Authorization'] = `Bearer ${newToken}`;
          }
        }
        return apiClient(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError, null);
        handleAuthFailure();
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);