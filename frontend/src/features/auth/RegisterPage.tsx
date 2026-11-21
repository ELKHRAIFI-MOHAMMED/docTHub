import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { useAppDispatch, useAppSelector } from '../../app/store';
import { Alert, Button, Card, Field, Input, Select } from '../../components/common/ui';
import { errorMessage, fieldErrors } from '../../utils/errors';
import { useCitiesQuery, useCountriesQuery, useLanguagesQuery } from '../catalog/catalogApi';
import { useRegisterMutation } from './authApi';
import { credentialsReceived } from './authSlice';
import { setCity, setCountry, setLocale, type Locale } from '../preferences/preferencesSlice';

export function validateRegistration(f: Record<string, string>, consents: Record<string, boolean>, t: (k: string) => string) {
  const errs: Record<string, string> = {};
  if (!f.first_name.trim()) errs.first_name = t('validation.required');
  if (!f.last_name.trim()) errs.last_name = t('validation.required');
  if (!/^\S+@\S+\.\S+$/.test(f.email)) errs.email = t('validation.email');
  if (f.phone && !/^\+?[0-9 ()\-.]{6,32}$/.test(f.phone)) errs.phone = t('validation.phone');
  if (f.password.length < 8 || !/[a-zA-Z]/.test(f.password) || !/\d/.test(f.password)) errs.password = t('validation.password');
  if (f.password !== f.password_confirmation) errs.password_confirmation = t('validation.passwordMatch');
  if (!f.country_id) errs.country_id = t('validation.required');
  if (f.date_of_birth && new Date(f.date_of_birth) >= new Date()) errs.date_of_birth = t('validation.pastDate');
  (['terms', 'privacy', 'health_data'] as const).forEach((c) => {
    if (!consents[c]) errs[`consents.${c}`] = t('validation.consent');
  });
  return errs;
}

export default function RegisterPage() {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const prefs = useAppSelector((s) => s.preferences);
  const [register, { isLoading, error }] = useRegisterMutation();

  const [f, setF] = useState({
    first_name: '', last_name: '', email: '', phone: '', password: '', password_confirmation: '',
    date_of_birth: '', sex: '', country_id: prefs.countryId ? String(prefs.countryId) : '', city_id: prefs.cityId ? String(prefs.cityId) : '',
    preferred_language: prefs.locale as string,
  });
  const [consents, setConsents] = useState({ terms: false, privacy: false, health_data: false });
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const { data: countries = [] } = useCountriesQuery(prefs.locale);
  const { data: cities = [] } = useCitiesQuery({ countryId: Number(f.country_id), locale: prefs.locale }, { skip: !f.country_id });
  const { data: languages = [] } = useLanguagesQuery();

  const errors = { ...fieldErrors(error), ...clientErrors };
  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF((p) => ({ ...p, [k]: e.target.value, ...(k === 'country_id' ? { city_id: '' } : {}) }));
  const input = (k: keyof typeof f, label: string, type = 'text', autoComplete?: string, required = false) => (
    <Field label={label} error={errors[k]} required={required}>
      {(id, d) => <Input id={id} aria-describedby={d} aria-invalid={Boolean(errors[k])} type={type} autoComplete={autoComplete} value={f[k]} onChange={set(k)} />}
    </Field>
  );

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const errs = validateRegistration(f, consents, t);
    setClientErrors(errs);
    if (Object.keys(errs).length) return;

    try {
      const res = await register({
        ...f,
        phone: f.phone || undefined,
        date_of_birth: f.date_of_birth || undefined,
        sex: f.sex || undefined,
        country_id: Number(f.country_id),
        city_id: f.city_id ? Number(f.city_id) : null,
        consents,
      }).unwrap();
      dispatch(credentialsReceived(res.data));
      dispatch(setLocale(f.preferred_language as Locale));
      dispatch(setCountry(Number(f.country_id)));
      dispatch(setCity(f.city_id ? Number(f.city_id) : null));
      navigate('/symptom-checker');
    } catch {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }

  const consent = (key: keyof typeof consents, label: string) => (
    <div>
      <label className="flex items-start gap-3 text-sm text-slate-700">
        <input type="checkbox" className="mt-0.5 h-4 w-4 accent-brand-600" checked={consents[key]} onChange={(e) => setConsents((c) => ({ ...c, [key]: e.target.checked }))} />
        <span>{label}</span>
      </label>
      {errors[`consents.${key}`] && <p className="ms-7 text-xs font-medium text-red-600">{errors[`consents.${key}`]}</p>}
    </div>
  );

  return (
    <div className="mx-auto max-w-2xl">
      <Card>
        <h1 className="text-2xl font-bold text-slate-900">{t('auth.registerTitle')}</h1>
        <p className="mt-1 text-sm text-slate-600">{t('auth.registerIntro')}</p>
        <form className="mt-6 space-y-5" onSubmit={onSubmit} noValidate>
          {error && !Object.keys(fieldErrors(error)).length && <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>}
          <div className="grid gap-4 sm:grid-cols-2">
            {input('first_name', t('profile.firstName'), 'text', 'given-name', true)}
            {input('last_name', t('profile.lastName'), 'text', 'family-name', true)}
            {input('email', t('auth.email'), 'email', 'email', true)}
            {input('phone', t('profile.phone'), 'tel', 'tel')}
            {input('password', t('auth.password'), 'password', 'new-password', true)}
            {input('password_confirmation', t('auth.passwordConfirm'), 'password', 'new-password', true)}
            {input('date_of_birth', t('profile.dateOfBirth'), 'date', 'bday')}
            <Field label={t('profile.sex')} hint={t('profile.sexHint')} error={errors.sex}>
              {(id, d) => (
                <Select id={id} aria-describedby={d} value={f.sex} onChange={set('sex')}>
                  <option value="">{t('profile.preferNotToSay')}</option>
                  <option value="female">{t('profile.female')}</option>
                  <option value="male">{t('profile.male')}</option>
                  <option value="other">{t('profile.other')}</option>
                </Select>
              )}
            </Field>
            <Field label={t('location.country')} error={errors.country_id} required>
              {(id, d) => (
                <Select id={id} aria-describedby={d} aria-invalid={Boolean(errors.country_id)} value={f.country_id} onChange={set('country_id')}>
                  <option value="">{t('location.selectCountry')}</option>
                  {countries.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('location.city')} error={errors.city_id}>
              {(id, d) => (
                <Select id={id} aria-describedby={d} value={f.city_id} onChange={set('city_id')} disabled={!f.country_id}>
                  <option value="">{t('location.selectCity')}</option>
                  {cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('location.language')} error={errors.preferred_language} required>
              {(id, d) => (
                <Select id={id} aria-describedby={d} value={f.preferred_language} onChange={set('preferred_language')}>
                  {languages.map((l) => <option key={l.code} value={l.code}>{l.native_name}</option>)}
                </Select>
              )}
            </Field>
          </div>
          <fieldset className="space-y-3 rounded-xl bg-slate-50 p-4">
            <legend className="sr-only">{t('auth.consents')}</legend>
            {consent('terms', t('auth.consentTerms'))}
            {consent('privacy', t('auth.consentPrivacy'))}
            {consent('health_data', t('auth.consentHealth'))}
          </fieldset>
          <Button type="submit" className="w-full" loading={isLoading}>{t('auth.createAccount')}</Button>
        </form>
        <p className="mt-6 text-center text-sm text-slate-600">
          {t('auth.haveAccount')} <Link to="/login" className="font-semibold text-brand-700 hover:underline">{t('nav.login')}</Link>
        </p>
      </Card>
    </div>
  );
}
