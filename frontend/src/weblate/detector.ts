import {
  DEFAULT_LOCALE,
  isSupportedLocale,
  type SupportedLocale,
} from './types';

const STORAGE_KEY = 'user_locale';

export function getStoredLocale(): SupportedLocale | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY);
    if (value && isSupportedLocale(value)) {
      return value;
    }
  } catch {}
  return null;
}

export function saveStoredLocale(locale: SupportedLocale): void {
  try {
    localStorage.setItem(STORAGE_KEY, locale);
  } catch {}
}

export function normalizeLocale(raw: string | null | undefined): SupportedLocale | null {
  if (!raw) return null;
  const cleaned = raw.toLowerCase().replace('_', '-').trim();
  if (cleaned.startsWith('en')) {
    return 'en';
  }
  return null;
}

export async function detectFromGoogle(): Promise<SupportedLocale | null> {
  const googleApiKey = import.meta.env.VITE_GOOGLE_API_KEY;

  if (googleApiKey) {
    try {
      const geoResponse = await fetch(
        `https://www.googleapis.com/geolocation/v1/geolocate?key=${googleApiKey}`,
        { method: 'POST' }
      );
      if (geoResponse.ok) {
        const geoData = await geoResponse.json();
        if (geoData?.location) {
          const revResponse = await fetch(
            `https://maps.googleapis.com/maps/api/geocode/json?latlng=${geoData.location.lat},${geoData.location.lng}&key=${googleApiKey}`
          );
          if (revResponse.ok) {
            const revData = await revResponse.json();
            const countryComp = revData?.results?.[0]?.address_components?.find(
              (c: { types: string[]; short_name: string }) => c.types.includes('country')
            );
            const matched = normalizeLocale(countryComp?.short_name);
            if (matched) return matched;
          }
        }
      }
    } catch {}
  }

  if (typeof navigator !== 'undefined') {
    const list = navigator.languages?.length ? navigator.languages : [navigator.language];
    for (const lang of list) {
      const matched = normalizeLocale(lang);
      if (matched) return matched;
    }
  }

  return null;
}

export async function resolveLocale(userProfileLocale?: string | null): Promise<SupportedLocale> {
  if (userProfileLocale && isSupportedLocale(userProfileLocale)) {
    return userProfileLocale;
  }

  const stored = getStoredLocale();
  if (stored) {
    return stored;
  }

  const fromGoogle = await detectFromGoogle();
  if (fromGoogle) {
    return fromGoogle;
  }

  return DEFAULT_LOCALE;
}
