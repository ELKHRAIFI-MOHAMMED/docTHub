import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { mockApi, renderWithProviders } from '../../test/utils';
import LoginPage from './LoginPage';
import RegisterPage, { validateRegistration } from './RegisterPage';
import { RequireRole } from './RequireRole';

const user = { id: 1, name: 'Demo Patient', email: 'p@test.local', phone: null, preferred_language: 'fr', roles: ['patient'], patient: null };

describe('Authentication', () => {
  it('validates the login form before calling the API', async () => {
    const fetch = mockApi({});
    renderWithProviders(<LoginPage />, { path: '/login', route: '/login' });
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }));
    expect(screen.getByText('Enter a valid email address.')).toBeInTheDocument();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('logs in, stores the token and applies the preferred language', async () => {
    mockApi({ 'POST /auth/login': { data: { token: 'tok-123', user } } });
    const { store, router } = renderWithProviders(<LoginPage />, { path: '/login', route: '/login', extraRoutes: [{ path: '/', element: <p>home</p> }] });

    await userEvent.type(screen.getByLabelText(/Email/), 'p@test.local');
    await userEvent.type(screen.getByLabelText(/Password/), 'Secret1234');
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }));

    await waitFor(() => expect(store.getState().auth.token).toBe('tok-123'));
    expect(localStorage.getItem('docthub.token')).toBe('tok-123');
    expect(store.getState().preferences.locale).toBe('fr');
    expect(router.state.location.pathname).toBe('/');
  });

  it('shows server-side validation errors', async () => {
    mockApi({ 'POST /auth/login': { __status: 422, message: 'Invalid', errors: { email: ['These credentials do not match our records.'] } } });
    renderWithProviders(<LoginPage />, { path: '/login', route: '/login' });
    await userEvent.type(screen.getByLabelText(/Email/), 'p@test.local');
    await userEvent.type(screen.getByLabelText(/Password/), 'wrong');
    await userEvent.click(screen.getByRole('button', { name: 'Log in' }));
    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument();
  });

  it('requires every consent and a strong password at registration', () => {
    const t = (k: string) => k;
    const form = {
      first_name: 'A', last_name: 'B', email: 'a@b.co', phone: '', password: 'short', password_confirmation: 'other',
      date_of_birth: '', sex: '', country_id: '1', city_id: '', preferred_language: 'en',
    };
    const errs = validateRegistration(form, { terms: true, privacy: false, health_data: false }, t);
    expect(errs.password).toBe('validation.password');
    expect(errs.password_confirmation).toBe('validation.passwordMatch');
    expect(errs['consents.privacy']).toBe('validation.consent');
    expect(errs['consents.health_data']).toBe('validation.consent');
    expect(errs.terms).toBeUndefined();
  });

  it('register form blocks submission until consents are given', async () => {
    const fetch = mockApi({ 'GET /countries': { data: [] }, 'GET /languages': { data: [] } });
    renderWithProviders(<RegisterPage />, { path: '/register', route: '/register' });
    await userEvent.click(screen.getByRole('button', { name: 'Create my account' }));
    expect(screen.getAllByText('This consent is required.')).toHaveLength(3);
    expect(fetch.mock.calls.some(([r]) => (r as Request).method === 'POST')).toBe(false);
  });

  it('redirects guests to the login page on protected routes', async () => {
    const { router } = renderWithProviders(<RequireRole role="patient"><p>secret</p></RequireRole>, {
      path: '/favorites', route: '/favorites', extraRoutes: [{ path: '/login', element: <p>login page</p> }],
    });
    expect(await screen.findByText('login page')).toBeInTheDocument();
    expect(router.state.location.state).toEqual({ from: '/favorites' });
  });

  it('refuses access to a patient on admin routes', async () => {
    mockApi({ 'GET /auth/me': { data: user } });
    renderWithProviders(<RequireRole role="admin"><p>admin area</p></RequireRole>, {
      path: '/admin', route: '/admin', state: { auth: { token: 'tok', user: null } },
    });
    expect(await screen.findByText('You do not have access to this page.')).toBeInTheDocument();
    expect(screen.queryByText('admin area')).not.toBeInTheDocument();
  });
});
