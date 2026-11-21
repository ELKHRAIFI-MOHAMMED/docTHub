import { useTranslation } from 'react-i18next';
import { NavLink, Outlet } from 'react-router-dom';
import { cx } from '../../components/common/ui';
import { RESOURCES } from './resources';

const link = ({ isActive }: { isActive: boolean }) =>
  cx('block rounded-lg px-3 py-2 text-sm', isActive ? 'bg-brand-50 font-semibold text-brand-800' : 'text-slate-600 hover:bg-slate-100');

export default function AdminLayout() {
  const { t } = useTranslation();
  return (
    <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
      <aside>
        <nav aria-label={t('nav.admin')} className="space-y-4 rounded-2xl bg-white p-3 ring-1 ring-slate-200 lg:sticky lg:top-32">
          <div>
            <NavLink end to="/admin" className={link}>{t('admin.dashboard')}</NavLink>
            <NavLink to="/admin/doctors" className={link}>{t('admin.res.doctors')}</NavLink>
            <NavLink to="/admin/patients" className={link}>{t('admin.res.patients')}</NavLink>
            <NavLink to="/admin/analytics" className={link}>{t('analytics.title')}</NavLink>
            <NavLink to="/admin/audit" className={link}>{t('audit.title')}</NavLink>
          </div>
          <div>
            <p className="px-3 pb-1 text-xs font-semibold uppercase text-slate-400">{t('admin.catalog')}</p>
            {RESOURCES.map((r) => <NavLink key={r.key} to={`/admin/r/${r.key}`} className={link}>{t(r.title)}</NavLink>)}
          </div>
        </nav>
      </aside>
      <div className="min-w-0"><Outlet /></div>
    </div>
  );
}
