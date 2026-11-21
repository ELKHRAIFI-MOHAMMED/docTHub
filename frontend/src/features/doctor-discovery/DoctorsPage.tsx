import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Alert, Button, Card, EmptyState, Field, Input, PageLoader, Pagination, Select } from '../../components/common/ui';
import { DoctorCard } from '../../components/doctors/DoctorCard';
import { LocationLanguagePicker } from '../../components/layout/LocationLanguagePicker';
import { useSelectedLocation } from '../../hooks/useLocation';
import { errorMessage } from '../../utils/errors';
import { useLanguagesQuery, useSpecialtiesQuery } from '../catalog/catalogApi';
import { useSearchDoctorsQuery, type DoctorFilters } from './doctorsApi';

type Coords = { lat: number; lng: number };

export default function DoctorsPage() {
  const { t } = useTranslation();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { countryId, cityId, country, city } = useSelectedLocation();
  const [params, setParams] = useSearchParams();
  const { data: specialties = [] } = useSpecialtiesQuery(locale);
  const { data: languages = [] } = useLanguagesQuery();

  // Geolocation is only requested after an explicit click, and coordinates stay in memory.
  const [coords, setCoords] = useState<Coords | null>(null);
  const [geoState, setGeoState] = useState<'idle' | 'asking' | 'denied' | 'unavailable'>('idle');
  const [q, setQ] = useState(params.get('q') ?? '');

  const num = (k: string) => (params.get(k) ? Number(params.get(k)) : undefined);
  const filters: DoctorFilters = {
    specialty_id: num('specialty_id'),
    language: params.get('language') ?? undefined,
    max_fee: num('max_fee'),
    q: params.get('q') ?? undefined,
    sort: (params.get('sort') as DoctorFilters['sort']) ?? undefined,
    page: num('page') ?? 1,
    radius_km: coords ? num('radius_km') ?? 25 : undefined,
    locale,
    ...(coords ? { lat: coords.lat, lng: coords.lng } : cityId ? { city_id: cityId } : countryId ? { country_id: countryId } : {}),
  };
  const hasLocation = Boolean(coords || cityId || countryId);
  const { data, isFetching, error } = useSearchDoctorsQuery(filters, { skip: !hasLocation });

  const update = (patch: Record<string, string | undefined>) => {
    const next = new URLSearchParams(params);
    Object.entries(patch).forEach(([k, v]) => (v ? next.set(k, v) : next.delete(k)));
    if (!('page' in patch)) next.delete('page');
    setParams(next, { replace: true });
  };

  function useMyLocation() {
    if (!('geolocation' in navigator)) return setGeoState('unavailable');
    setGeoState('asking');
    navigator.geolocation.getCurrentPosition(
      (pos) => { setCoords({ lat: pos.coords.latitude, lng: pos.coords.longitude }); setGeoState('idle'); update({ sort: 'distance' }); },
      () => setGeoState('denied'),
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
    );
  }

  const where = coords ? t('doctors.nearYou') : [city?.name, country?.name].filter(Boolean).join(', ');

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-3xl font-bold text-slate-900">{t('doctors.title')}</h1>
        {where && <p className="mt-1 text-slate-600">{t('doctors.searchingIn', { where })}</p>}
      </header>

      <div className="grid gap-6 lg:grid-cols-[300px_1fr]">
        <aside>
          <Card className="space-y-4">
            <form onSubmit={(e) => { e.preventDefault(); update({ q: q.trim() || undefined }); }} role="search">
              <Field label={t('doctors.searchName')}>
                {(id) => <Input id={id} type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('doctors.searchPlaceholder')} />}
              </Field>
            </form>
            <Field label={t('doctors.specialty')}>
              {(id) => (
                <Select id={id} value={filters.specialty_id ?? ''} onChange={(e) => update({ specialty_id: e.target.value || undefined })}>
                  <option value="">{t('doctors.allSpecialties')}</option>
                  {specialties.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('doctors.language')}>
              {(id) => (
                <Select id={id} value={filters.language ?? ''} onChange={(e) => update({ language: e.target.value || undefined })}>
                  <option value="">{t('doctors.anyLanguage')}</option>
                  {languages.map((l) => <option key={l.code} value={l.code}>{l.native_name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('doctors.maxFee')} hint={country?.currency_code ?? undefined}>
              {(id, d) => <Input id={id} aria-describedby={d} type="number" min={0} inputMode="numeric" defaultValue={filters.max_fee ?? ''} onBlur={(e) => update({ max_fee: e.target.value || undefined })} />}
            </Field>
            <Field label={t('doctors.sortBy')}>
              {(id) => (
                <Select id={id} value={filters.sort ?? (coords ? 'distance' : 'relevance')} onChange={(e) => update({ sort: e.target.value })}>
                  <option value="relevance">{t('doctors.sort.relevance')}</option>
                  {coords && <option value="distance">{t('doctors.sort.distance')}</option>}
                  <option value="experience">{t('doctors.sort.experience')}</option>
                  <option value="fee">{t('doctors.sort.fee')}</option>
                  <option value="name">{t('doctors.sort.name')}</option>
                </Select>
              )}
            </Field>
            {coords && (
              <Field label={t('doctors.radius')}>
                {(id) => (
                  <Select id={id} value={filters.radius_km} onChange={(e) => update({ radius_km: e.target.value })}>
                    {[5, 10, 25, 50, 100].map((r) => <option key={r} value={r}>{r} km</option>)}
                  </Select>
                )}
              </Field>
            )}
            <div className="space-y-2 border-t border-slate-100 pt-4">
              {coords ? (
                <Button variant="secondary" className="w-full" onClick={() => { setCoords(null); update({ sort: undefined, radius_km: undefined }); }}>{t('doctors.stopUsingLocation')}</Button>
              ) : (
                <Button variant="secondary" className="w-full" onClick={useMyLocation} loading={geoState === 'asking'}>📍 {t('doctors.useMyLocation')}</Button>
              )}
              <p className="text-xs text-slate-500">{t('doctors.locationPrivacy')}</p>
              {geoState === 'denied' && <Alert tone="warning">{t('doctors.locationDenied')}</Alert>}
              {geoState === 'unavailable' && <Alert tone="warning">{t('doctors.locationUnavailable')}</Alert>}
            </div>
          </Card>
        </aside>

        <section aria-live="polite" aria-busy={isFetching}>
          {!hasLocation ? (
            <EmptyState icon="🌍" title={t('doctors.chooseLocation')}>
              <div className="mt-4 flex justify-center"><LocationLanguagePicker /></div>
            </EmptyState>
          ) : error ? (
            <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>
          ) : !data ? (
            <PageLoader />
          ) : data.data.length === 0 ? (
            <EmptyState title={t('doctors.noResults')}>{t('doctors.noResultsHint')}</EmptyState>
          ) : (
            <>
              <p className="mb-3 text-sm text-slate-600">
                {t('doctors.resultCount', { count: data.meta.total })}
                {data.meta.distance_basis === 'none' && ` · ${t('doctors.noDistance')}`}
              </p>
              <div className={isFetching ? 'space-y-3 opacity-60' : 'space-y-3'}>
                {data.data.map((d) => <DoctorCard key={d.id} doctor={d} />)}
              </div>
              <Pagination page={data.meta.current_page} lastPage={data.meta.last_page} onChange={(p) => update({ page: String(p) })} />
            </>
          )}
        </section>
      </div>
    </div>
  );
}
