import { ref, computed, type App, type Plugin } from 'vue';
import en from './translations/en.json';
import {
  DEFAULT_LOCALE,
  isSupportedLocale,
  LOCALE_OPTIONS,
  SUPPORTED_LOCALES,
  type SupportedLocale,
} from './types';
import { resolveLocale, saveStoredLocale } from './detector';
import { authApi } from '@/api/modules/login.api';

function getNestedValue(obj: Record<string, unknown> | undefined, path: string): string | undefined {
  if (!obj) return undefined;
  const parts = path.split('.');
  let current: unknown = obj;
  for (const part of parts) {
    if (current === null || current === undefined || typeof current !== 'object') {
      return undefined;
    }
    current = (current as Record<string, unknown>)[part];
  }
  return typeof current === 'string' ? current : undefined;
}

const currentLocale = ref<SupportedLocale>(DEFAULT_LOCALE);

const translationFiles = import.meta.glob<Record<string, unknown>>('./translations/*.json', {
  eager: true,
  import: 'default',
});

const loadedDictionaries: Record<string, Record<string, unknown>> = { en };
for (const [filePath, content] of Object.entries(translationFiles)) {
  const code = filePath.replace(/^.*[\\/]/, '').replace(/\.json$/, '');
  if (code && content) {
    loadedDictionaries[code] = content;
  }
}

const dictionaries = ref<Record<string, Record<string, unknown>>>(loadedDictionaries);

export function t(key: string, params?: Record<string, string | number>): string {
  const currentDict = dictionaries.value[currentLocale.value];
  const fallbackDict = dictionaries.value.en;

  let text = getNestedValue(currentDict, key) ?? getNestedValue(fallbackDict, key) ?? key;

  if (params && typeof text === 'string') {
    for (const [paramKey, paramValue] of Object.entries(params)) {
      text = text.replace(new RegExp(`\\{${paramKey}\\}`, 'g'), String(paramValue));
    }
  }

  return text;
}

export async function setWeblateLocale(
  locale: SupportedLocale,
  options: {
    persist?: boolean;
    syncBackend?: boolean;
    userId?: string;
  } = {}
): Promise<void> {
  if (!isSupportedLocale(locale)) {
    return;
  }

  currentLocale.value = locale;

  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale;
  }

  if (options.persist !== false) {
    saveStoredLocale(locale);
  }

  if (options.syncBackend && options.userId) {
    try {
      await authApi.updateUserLocale(options.userId, locale);
    } catch {}
  }
}

export async function initWeblate(userLocale?: string | null): Promise<SupportedLocale> {
  const resolved = await resolveLocale(userLocale);
  await setWeblateLocale(resolved, { persist: false });
  return resolved;
}

export function useWeblate() {
  return {
    locale: computed(() => currentLocale.value),
    t,
    setLocale: setWeblateLocale,
    locales: SUPPORTED_LOCALES,
    localeOptions: LOCALE_OPTIONS,
  };
}

export const weblatePlugin: Plugin = {
  install(app: App) {
    app.config.globalProperties.$t = t;
    app.provide('weblate', {
      locale: currentLocale,
      t,
      setLocale: setWeblateLocale,
    });
  },
};

declare module 'vue' {
  interface ComponentCustomProperties {
    $t: (key: string, params?: Record<string, string | number>) => string;
  }
}
