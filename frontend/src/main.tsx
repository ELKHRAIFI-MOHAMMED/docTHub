import { StrictMode, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { RouterProvider } from 'react-router-dom';
import { I18nextProvider } from 'react-i18next';
import { store, useAppSelector } from './app/store';
import { router } from './app/router';
import i18n, { initI18n } from './i18n';
import './styles/index.css';

initI18n(store.getState().preferences.locale);

/** Keeps i18next and <html dir/lang> in sync with the locale stored in Redux. */
function LocaleSync() {
  const locale = useAppSelector((s) => s.preferences.locale);
  useEffect(() => {
    initI18n(locale);
  }, [locale]);
  return null;
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <Provider store={store}>
      <I18nextProvider i18n={i18n}>
        <LocaleSync />
        <RouterProvider router={router} />
      </I18nextProvider>
    </Provider>
  </StrictMode>,
);
