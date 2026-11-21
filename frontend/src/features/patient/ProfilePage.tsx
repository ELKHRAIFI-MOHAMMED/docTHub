import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useAppDispatch, useAppSelector } from '../../app/store';
import { api } from '../../app/api';
import { Alert, Button, Card, Field, Input, PageLoader, Select } from '../../components/common/ui';
import { errorMessage, fieldErrors } from '../../utils/errors';
import { useCitiesQuery, useCountriesQuery, useLanguagesQuery } from '../catalog/catalogApi';
import { loggedOut } from '../auth/authSlice';
import { setLocale, type Locale } from '../preferences/preferencesSlice';
import { useDeleteAccountMutation, useExportDataMutation, useProfileQuery, useUpdateProfileMutation } from './patientApi';

export default function ProfilePage() {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: profile, isLoading } = useProfileQuery(locale);
  const [update, { isLoading: saving, error, isSuccess }] = useUpdateProfileMutation();
  const [exportData, { isLoading: exporting }] = useExportDataMutation();
  const [deleteAccount, { isLoading: deleting, error: deleteError }] = useDeleteAccountMutation();
  const [f, setF] = useState<Record<string, string | boolean>>({});
  const [password, setPassword] = useState('');

  useEffect(() => {
    if (profile) {
      setF({
        first_name: profile.first_name, last_name: profile.last_name, phone: profile.phone ?? '', date_of_birth: profile.date_of_birth ?? '',
        sex: profile.sex ?? '', country_id: String(profile.country?.id ?? ''), city_id: String(profile.city?.id ?? ''),
        preferred_language: profile.preferences?.language_code ?? locale, share_location: Boolean(profile.preferences?.share_location),
      });
    }
  }, [profile, locale]);

  const { data: countries = [] } = useCountriesQuery(locale);
  const { data: cities = [] } = useCitiesQuery({ countryId: Number(f.country_id), locale }, { skip: !f.country_id });
  const { data: languages = [] } = useLanguagesQuery();
  const errors = fieldErrors(error);
  const set = (k: string) => (e: { target: { value: string } }) => setF((p) => ({ ...p, [k]: e.target.value, ...(k === 'country_id' ? { city_id: '' } : {}) }));

  if (isLoading || !profile) return <PageLoader />;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    try {
      await update({
        first_name: String(f.first_name), last_name: String(f.last_name), phone: (f.phone as string) || null,
        date_of_birth: (f.date_of_birth as string) || null, sex: (f.sex as string) || null,
        country_id: Number(f.country_id), city_id: f.city_id ? Number(f.city_id) : null,
        preferred_language: String(f.preferred_language), share_location: Boolean(f.share_location),
      }).unwrap();
      dispatch(setLocale(f.preferred_language as Locale));
    } catch {
      /* shown below */
    }
  }

  async function onExport() {
    const data = await exportData().unwrap();
    const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'docthub-my-data.json';
    a.click();
    URL.revokeObjectURL(url);
  }

  async function onDelete(e: FormEvent) {
    e.preventDefault();
    if (!window.confirm(t('profile.deleteConfirm'))) return;
    try {
      await deleteAccount({ password }).unwrap();
      dispatch(loggedOut());
      dispatch(api.util.resetApiState());
      window.location.assign('/');
    } catch {
      /* shown below */
    }
  }

  const text = (k: string, label: string, type = 'text') => (
    <Field label={label} error={errors[k]}>
      {(id, d) => <Input id={id} aria-describedby={d} aria-invalid={Boolean(errors[k])} type={type} value={String(f[k] ?? '')} onChange={set(k)} />}
    </Field>
  );

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="text-3xl font-bold text-slate-900">{t('profile.title')}</h1>
      <Card>
        <form className="space-y-5" onSubmit={onSubmit}>
          {isSuccess && <Alert tone="success">{t('profile.saved')}</Alert>}
          {error && !Object.keys(errors).length && <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>}
          <div className="grid gap-4 sm:grid-cols-2">
            {text('first_name', t('profile.firstName'))}
            {text('last_name', t('profile.lastName'))}
            {text('phone', t('profile.phone'), 'tel')}
            {text('date_of_birth', t('profile.dateOfBirth'), 'date')}
            <Field label={t('profile.sex')} hint={t('profile.sexHint')}>
              {(id, d) => (
                <Select id={id} aria-describedby={d} value={String(f.sex)} onChange={set('sex')}>
                  <option value="">{t('profile.preferNotToSay')}</option>
                  <option value="female">{t('profile.female')}</option>
                  <option value="male">{t('profile.male')}</option>
                  <option value="other">{t('profile.other')}</option>
                </Select>
              )}
            </Field>
            <Field label={t('location.language')}>
              {(id) => (
                <Select id={id} value={String(f.preferred_language)} onChange={set('preferred_language')}>
                  {languages.map((l) => <option key={l.code} value={l.code}>{l.native_name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('location.country')} error={errors.country_id}>
              {(id) => (
                <Select id={id} value={String(f.country_id)} onChange={set('country_id')}>
                  {countries.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('location.city')} error={errors.city_id}>
              {(id) => (
                <Select id={id} value={String(f.city_id)} onChange={set('city_id')}>
                  <option value="">{t('location.selectCity')}</option>
                  {cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              )}
            </Field>
          </div>
          <label className="flex items-center gap-3 text-sm text-slate-700">
            <input type="checkbox" className="h-4 w-4 accent-brand-600" checked={Boolean(f.share_location)} onChange={(e) => setF((p) => ({ ...p, share_location: e.target.checked }))} />
            {t('profile.shareLocation')}
          </label>
          <Button type="submit" loading={saving}>{t('common.save')}</Button>
        </form>
      </Card>

      <Card>
        <h2 className="text-lg font-semibold">{t('profile.privacyTitle')}</h2>
        <p className="mt-1 text-sm text-slate-600">{t('profile.exportText')}</p>
        <Button variant="secondary" className="mt-3" onClick={onExport} loading={exporting}>{t('profile.export')}</Button>

        <form onSubmit={onDelete} className="mt-6 space-y-3 border-t border-slate-100 pt-6">
          <h3 className="font-semibold text-red-700">{t('profile.deleteTitle')}</h3>
          <p className="text-sm text-slate-600">{t('profile.deleteText')}</p>
          <Field label={t('auth.password')} error={fieldErrors(deleteError).password}>
            {(id, d) => <Input id={id} aria-describedby={d} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />}
          </Field>
          <Button type="submit" variant="danger" disabled={!password} loading={deleting}>{t('profile.delete')}</Button>
        </form>
      </Card>
    </div>
  );
}
