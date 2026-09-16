export type SupportedLocale = 'en';

export const SUPPORTED_LOCALES: readonly SupportedLocale[] = ['en'] as const;

export const DEFAULT_LOCALE: SupportedLocale = 'en';

export interface LocaleOption {
  code: SupportedLocale;
  label: string;
  shortLabel: string;
}

export const LOCALE_OPTIONS: readonly LocaleOption[] = [
  { code: 'en', label: 'English', shortLabel: 'EN' },
] as const;

export function isSupportedLocale(value: unknown): value is SupportedLocale {
  return value === 'en';
}
