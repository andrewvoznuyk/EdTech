import { jwtDecode } from 'jwt-decode';
import type { AuthUser, JWTPayload } from '@/types/auth';

let inMemoryToken: string | null = null;
const listeners = new Set<(token: string | null) => void>();

export function getAccessToken(): string | null {
  return inMemoryToken;
}

export function hasRefreshTokenCookie(): boolean {
  if (typeof document === 'undefined') return false;
  return /(?:^|;\s*)(?:has_refresh_token|refresh_token|token|jwt|REFRESH_TOKEN)\s*=\s*([^;]+)/i.test(document.cookie);
}

export function setRefreshTokenCookie(present: boolean): void {
  if (typeof document === 'undefined') return;
  if (present) {
    document.cookie = 'has_refresh_token=1; path=/; max-age=2592000; SameSite=Lax';
  } else {
    document.cookie = 'has_refresh_token=; path=/; max-age=0; SameSite=Lax';
  }
}

export function setAccessToken(token: string | null): void {
  inMemoryToken = token;
  setRefreshTokenCookie(!!token);
  listeners.forEach((listener) => {
    try {
      listener(token);
    } catch {}
  });
}

export function clearAccessToken(): void {
  setAccessToken(null);
}

export function onTokenChange(listener: (token: string | null) => void): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function decodeJwt<T = JWTPayload>(token: string): T | null {
  try {
    return jwtDecode<T>(token);
  } catch {
    return null;
  }
}

export function getUserFromToken(token: string | null): AuthUser | null {
  if (!token) return null;
  const payload = decodeJwt<JWTPayload>(token);
  if (!payload) return null;

  return {
    id: payload.id || '',
    email: (payload.username as string) || (payload.email as string) || '',
    roles: Array.isArray(payload.roles) ? payload.roles : [],
    locale: typeof payload.locale === 'string' ? payload.locale : undefined,
    exp: payload.exp,
  };
}

export function isTokenExpired(token: string, offsetSeconds = 30): boolean {
  const payload = decodeJwt<JWTPayload>(token);
  if (!payload || !payload.exp) return true;
  const now = Math.floor(Date.now() / 1000);
  return payload.exp - offsetSeconds <= now;
}
