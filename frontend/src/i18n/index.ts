import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from './locales/en.json';
import fr from './locales/fr.json';
import ar from './locales/ar.json';

export const RTL_LOCALES = ['ar'];

/** Sets <html lang> and <html dir> so the whole layout mirrors for Arabic. */
export function applyDocumentLocale(locale: string) {
  document.documentElement.lang = locale;
  document.documentElement.dir = RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr';
}

export function initI18n(locale: string) {
  if (!i18n.isInitialized) {
    void i18n.use(initReactI18next).init({
      resources: { en: { translation: en }, fr: { translation: fr }, ar: { translation: ar } },
      lng: locale,
      fallbackLng: 'en',
      interpolation: { escapeValue: false }, // React already escapes
      returnNull: false,
    });
  } else if (i18n.language !== locale) {
    void i18n.changeLanguage(locale);
  }
  applyDocumentLocale(locale);
  return i18n;
}

export default i18n;
