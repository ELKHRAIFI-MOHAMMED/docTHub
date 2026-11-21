import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAppDispatch } from '../../app/store';
import { Alert, Button, Card, Field, Input } from '../../components/common/ui';
import { errorMessage, fieldErrors } from '../../utils/errors';
import { useLoginMutation } from './authApi';
import { credentialsReceived } from './authSlice';
import { setLocale, type Locale } from '../preferences/preferencesSlice';

export default function LoginPage() {
  const { t } = useTranslation();
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const location = useLocation();
  const [login, { isLoading, error }] = useLoginMutation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const serverErrors = fieldErrors(error);
  const errors = { ...serverErrors, ...clientErrors };

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const errs: Record<string, string> = {};
    if (!/^\S+@\S+\.\S+$/.test(email)) errs.email = t('validation.email');
    if (!password) errs.password = t('validation.required');
    setClientErrors(errs);
    if (Object.keys(errs).length) return;

    try {
      const res = await login({ email, password }).unwrap();
      dispatch(credentialsReceived(res.data));
      if (['en', 'fr', 'ar'].includes(res.data.user.preferred_language)) dispatch(setLocale(res.data.user.preferred_language as Locale));
      const from = (location.state as { from?: string } | null)?.from;
      navigate(from ?? (res.data.user.roles.includes('admin') ? '/admin' : '/'), { replace: true });
    } catch {
      /* displayed below */
    }
  }

  return (
    <div className="mx-auto max-w-md">
      <Card>
        <h1 className="text-2xl font-bold text-slate-900">{t('auth.loginTitle')}</h1>
        <form className="mt-6 space-y-4" onSubmit={onSubmit} noValidate>
          {error && !Object.keys(serverErrors).length && <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>}
          <Field label={t('auth.email')} error={errors.email} required>
            {(id, d) => <Input id={id} aria-describedby={d} aria-invalid={Boolean(errors.email)} type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} />}
          </Field>
          <Field label={t('auth.password')} error={errors.password} required>
            {(id, d) => <Input id={id} aria-describedby={d} aria-invalid={Boolean(errors.password)} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />}
          </Field>
          <Button type="submit" className="w-full" loading={isLoading}>{t('auth.login')}</Button>
        </form>
        <p className="mt-6 text-center text-sm text-slate-600">
          {t('auth.noAccount')} <Link to="/register" className="font-semibold text-brand-700 hover:underline">{t('nav.register')}</Link>
        </p>
      </Card>
    </div>
  );
}
