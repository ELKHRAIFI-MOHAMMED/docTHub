import { useTranslation } from 'react-i18next';
import { useAppDispatch, useAppSelector } from '../../app/store';
import { useLanguagesQuery } from '../../features/catalog/catalogApi';
import { setCity, setCountry, setLocale, type Locale } from '../../features/preferences/preferencesSlice';
import { useSelectedLocation } from '../../hooks/useLocation';
import { cx } from '../common/ui';

const NATIVE_NAMES: Record<Locale, string> = { en: 'English', fr: 'Français', ar: 'العربية' };

const selectCls = 'min-h-11 rounded-lg border-0 bg-white/90 py-1.5 ps-2 pe-7 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-brand-500';

/** Country, city and language selectors. All options come from the API. */
export function LocationLanguagePicker({ compact, className }: { compact?: boolean; className?: string }) {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { countryId, cityId, countries, cities } = useSelectedLocation();
  const { data: languages = [] } = useLanguagesQuery();

  return (
    <div className={cx('flex flex-wrap items-center gap-2', className)}>
      <label className="sr-only" htmlFor="pick-country">{t('location.country')}</label>
      <select id="pick-country" className={selectCls} value={countryId ?? ''} onChange={(e) => dispatch(setCountry(e.target.value ? Number(e.target.value) : null))}>
        <option value="">{t('location.selectCountry')}</option>
        {countries.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
      </select>

      {!compact || countryId ? (
        <>
          <label className="sr-only" htmlFor="pick-city">{t('location.city')}</label>
          <select id="pick-city" className={selectCls} value={cityId ?? ''} disabled={!countryId} onChange={(e) => dispatch(setCity(e.target.value ? Number(e.target.value) : null))}>
            <option value="">{t('location.allCities')}</option>
            {cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </>
      ) : null}

      <label className="sr-only" htmlFor="pick-lang">{t('location.language')}</label>
      <select id="pick-lang" className={selectCls} value={locale} onChange={(e) => dispatch(setLocale(e.target.value as Locale))}>
        {/* Until the list is loaded, show the active language (not a default one). */}
        {(languages.length ? languages : [{ code: locale, native_name: NATIVE_NAMES[locale] }]).map((l) => (
          <option key={l.code} value={l.code}>{l.native_name}</option>
        ))}
      </select>
    </div>
  );
}
