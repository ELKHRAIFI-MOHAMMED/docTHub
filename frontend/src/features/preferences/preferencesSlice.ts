import { createSlice, type PayloadAction } from '@reduxjs/toolkit';

export type Locale = 'en' | 'fr' | 'ar';
export const SUPPORTED_LOCALES: Locale[] = ['en', 'fr', 'ar'];
const STORAGE_KEY = 'docthub.preferences';

export interface PreferencesState {
  locale: Locale;
  countryId: number | null;
  cityId: number | null;
}

function detectLocale(): Locale {
  const nav = (typeof navigator !== 'undefined' ? navigator.language : 'en').slice(0, 2) as Locale;
  return SUPPORTED_LOCALES.includes(nav) ? nav : 'en';
}

export function loadPreferences(): PreferencesState {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as Partial<PreferencesState>;
      return {
        locale: SUPPORTED_LOCALES.includes(parsed.locale as Locale) ? (parsed.locale as Locale) : detectLocale(),
        countryId: parsed.countryId ?? null,
        cityId: parsed.cityId ?? null,
      };
    }
  } catch {
    /* corrupted storage: fall back to defaults */
  }
  return { locale: detectLocale(), countryId: null, cityId: null };
}

export function savePreferences(state: PreferencesState) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

const preferencesSlice = createSlice({
  name: 'preferences',
  initialState: loadPreferences,
  reducers: {
    setLocale(state, action: PayloadAction<Locale>) {
      state.locale = action.payload;
    },
    setCountry(state, action: PayloadAction<number | null>) {
      if (state.countryId !== action.payload) state.cityId = null;
      state.countryId = action.payload;
    },
    setCity(state, action: PayloadAction<number | null>) {
      state.cityId = action.payload;
    },
  },
});

export const { setLocale, setCountry, setCity } = preferencesSlice.actions;
export default preferencesSlice.reducer;
