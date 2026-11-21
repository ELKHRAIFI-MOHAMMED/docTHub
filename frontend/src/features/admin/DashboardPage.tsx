import { useTranslation } from 'react-i18next';
import { Alert, Card, PageLoader } from '../../components/common/ui';
import { errorMessage } from '../../utils/errors';
import { useAdminDashboardQuery } from './adminApi';

interface Stats {
  patients: number;
  doctors: Record<string, number>;
  diseases_published: number;
  rules: Record<string, number>;
  guidelines_pending_review: number;
  assessments_total: number;
  assessments_last_30_days: Record<string, number>;
  emergency_alerts_last_30_days: number;
  top_recommended_specialties: { specialty: string; total: number }[];
}

function Stat({ label, value, hint }: { label: string; value: number | string; hint?: string }) {
  return (
    <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-5">
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-1 text-3xl font-bold tabular-nums text-slate-900">{value}</p>
      {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
    </div>
  );
}

export default function DashboardPage() {
  const { t } = useTranslation();
  const { data, isLoading, error } = useAdminDashboardQuery();
  if (isLoading) return <PageLoader />;
  if (error || !data) return <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>;
  const s = data as unknown as Stats;

  const days = Object.entries(s.assessments_last_30_days);
  const max = Math.max(1, ...days.map(([, v]) => Number(v)));
  const doctorsTotal = Object.values(s.doctors).reduce((a, b) => a + Number(b), 0);
  const unapprovedRules = Object.entries(s.rules).filter(([k]) => k !== 'approved').reduce((a, [, v]) => a + Number(v), 0);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-900">{t('admin.dashboard')}</h1>
      {(unapprovedRules > 0 || s.guidelines_pending_review > 0) && (
        <Alert tone="warning" title={t('admin.reviewNeededTitle')}>{t('admin.reviewNeededText', { rules: unapprovedRules, guidelines: s.guidelines_pending_review })}</Alert>
      )}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Stat label={t('admin.stats.patients')} value={s.patients} />
        <Stat label={t('admin.stats.doctors')} value={doctorsTotal} hint={Object.entries(s.doctors).map(([k, v]) => `${k}: ${v}`).join(' · ')} />
        <Stat label={t('admin.stats.assessments')} value={s.assessments_total} />
        <Stat label={t('admin.stats.emergencies')} value={s.emergency_alerts_last_30_days} hint={t('admin.stats.last30')} />
      </div>
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="font-semibold">{t('admin.stats.assessmentsPerDay')}</h2>
          {days.length === 0 ? <p className="mt-4 text-sm text-slate-500">{t('admin.noData')}</p> : (
            <ul className="mt-4 space-y-1.5" aria-label={t('admin.stats.assessmentsPerDay')}>
              {days.map(([day, v]) => (
                <li key={day} className="flex items-center gap-3 text-xs">
                  <span className="w-20 shrink-0 tabular-nums text-slate-500">{day}</span>
                  <span className="h-3 rounded bg-brand-500" style={{ width: `${(Number(v) / max) * 100}%` }} aria-hidden />
                  <span className="tabular-nums text-slate-700">{v}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <Card>
          <h2 className="font-semibold">{t('admin.stats.topSpecialties')}</h2>
          {s.top_recommended_specialties.length === 0 ? <p className="mt-4 text-sm text-slate-500">{t('admin.noData')}</p> : (
            <ol className="mt-4 space-y-2 text-sm">
              {s.top_recommended_specialties.map((x) => (
                <li key={x.specialty} className="flex justify-between"><span>{x.specialty}</span><span className="font-semibold tabular-nums">{x.total}</span></li>
              ))}
            </ol>
          )}
          <h2 className="mt-6 font-semibold">{t('admin.stats.rules')}</h2>
          <p className="mt-2 text-sm text-slate-600">{Object.entries(s.rules).map(([k, v]) => `${k}: ${v}`).join(' · ')}</p>
        </Card>
      </div>
    </div>
  );
}
