import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAppDispatch } from '../../app/store';
import { useLogoutMutation } from '../../features/auth/authApi';
import { loggedOut } from '../../features/auth/authSlice';
import { api } from '../../app/api';
import { useSession } from '../../hooks/useSession';
import { cx } from '../common/ui';
import { LocationLanguagePicker } from './LocationLanguagePicker';

export function Logo() {
  return (
    <Link to="/" className="flex items-center gap-2" aria-label="docTHub">
      <img src="/logo.svg" alt="" className="h-9 w-9" />
      <span className="text-xl font-bold tracking-tight text-slate-900">doc<span className="text-brand-600">TH</span>ub</span>
    </Link>
  );
}

const navCls = ({ isActive }: { isActive: boolean }) =>
  cx('rounded-lg px-3 py-2 text-sm font-medium', isActive ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:text-slate-900');

export function useLogout() {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const [logout] = useLogoutMutation();
  return async () => {
    try {
      await logout().unwrap();
    } catch {
      /* token may already be invalid */
    }
    dispatch(loggedOut());
    dispatch(api.util.resetApiState());
    navigate('/');
  };
}

function Header() {
  const { t } = useTranslation();
  const { isAuthenticated, isAdmin, isPatient, user } = useSession();
  const logout = useLogout();
  const [open, setOpen] = useState(false);

  const links = (
    <>
      <NavLink to="/symptom-checker" className={navCls} onClick={() => setOpen(false)}>{t('nav.checkSymptoms')}</NavLink>
      <NavLink to="/doctors" className={navCls} onClick={() => setOpen(false)}>{t('nav.findDoctor')}</NavLink>
      {isPatient && <NavLink to="/favorites" className={navCls} onClick={() => setOpen(false)}>{t('nav.favorites')}</NavLink>}
      {isPatient && <NavLink to="/history" className={navCls} onClick={() => setOpen(false)}>{t('nav.history')}</NavLink>}
      {isAdmin && <NavLink to="/admin" className={navCls} onClick={() => setOpen(false)}>{t('nav.admin')}</NavLink>}
    </>
  );

  return (
    <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <Logo />
        <nav className="hidden items-center gap-1 lg:flex" aria-label={t('nav.main')}>{links}</nav>
        <div className="hidden items-center gap-2 lg:flex">
          {isAuthenticated ? (
            <>
              {isPatient && <Link to="/profile" className="text-sm font-medium text-slate-700 hover:text-brand-700">{user?.patient?.first_name ?? user?.name}</Link>}
              <button onClick={logout} className="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">{t('nav.logout')}</button>
            </>
          ) : (
            <>
              <Link to="/login" className="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{t('nav.login')}</Link>
              <Link to="/register" className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{t('nav.register')}</Link>
            </>
          )}
        </div>
        <button className="rounded-lg p-2 text-slate-700 lg:hidden" aria-expanded={open} aria-controls="mobile-menu" onClick={() => setOpen((o) => !o)}>
          <span className="sr-only">{t('nav.menu')}</span>
          <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
        </button>
      </div>
      <div className="border-t border-slate-100 bg-slate-50/80">
        <div className="mx-auto max-w-6xl px-4 py-2"><LocationLanguagePicker compact /></div>
      </div>
      {open && (
        <div id="mobile-menu" className="space-y-1 border-t border-slate-200 bg-white px-4 py-3 lg:hidden">
          <nav className="flex flex-col" aria-label={t('nav.main')}>{links}</nav>
          <div className="flex gap-2 border-t border-slate-100 pt-3">
            {isAuthenticated ? (
              <>
                {isPatient && <Link to="/profile" onClick={() => setOpen(false)} className={navCls({ isActive: false })}>{t('nav.profile')}</Link>}
                <button onClick={logout} className={navCls({ isActive: false })}>{t('nav.logout')}</button>
              </>
            ) : (
              <>
                <Link to="/login" onClick={() => setOpen(false)} className={navCls({ isActive: false })}>{t('nav.login')}</Link>
                <Link to="/register" onClick={() => setOpen(false)} className={navCls({ isActive: false })}>{t('nav.register')}</Link>
              </>
            )}
          </div>
        </div>
      )}
    </header>
  );
}

function Footer() {
  const { t } = useTranslation();
  return (
    <footer className="mt-16 border-t border-slate-200 bg-white">
      <div className="mx-auto grid max-w-6xl gap-6 px-4 py-10 text-sm text-slate-600 md:grid-cols-3">
        <div className="space-y-2">
          <Logo />
          <p>{t('brand.slogan')}</p>
        </div>
        <div className="space-y-1">
          <p className="font-semibold text-slate-800">{t('footer.platform')}</p>
          <Link className="block hover:text-brand-700" to="/symptom-checker">{t('nav.checkSymptoms')}</Link>
          <Link className="block hover:text-brand-700" to="/doctors">{t('nav.findDoctor')}</Link>
        </div>
        <p className="text-xs leading-relaxed text-slate-500">{t('disclaimer.footer')}</p>
      </div>
      <p className="border-t border-slate-100 py-4 text-center text-xs text-slate-400">© {new Date().getFullYear()} docTHub</p>
    </footer>
  );
}

export function AppLayout() {
  const { t } = useTranslation();
  return (
    <div className="flex min-h-screen flex-col">
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:p-2">{t('common.skipToContent')}</a>
      <Header />
      <main id="main" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
