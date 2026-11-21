import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { API_BASE_URL } from '../../app/api';
import { useAppSelector } from '../../app/store';
import { Alert, Button, Card, PageLoader, Select } from '../../components/common/ui';
import { errorMessage } from '../../utils/errors';
import { useAnalyticsQuery } from './adminApi';

interface Indicators {
  since: string;
  assessments: { total: number; completed: number; completion_rate: number | null };
  emergency: { count: number; rate: number | null; by_rule: Record<string, number>; followed_to_emergency: number | null };
  outcomes: { collected: number; response_rate: number | null; helpfulness_avg: number | null };
  concordance: {
    specialty: number | null; sample_size: number; diagnosis_in_conditions: number | null; diagnosis_sample_size: number;
    by_recommended_specialty: { specialty: string; outcomes: number; confirmed: number; rate: number | null }[];
  };
  content: Record<string, { total: number; approved: number; approved_with_source_document: number }>;
  versions: Record<string, number>;
}

/** Minimum number of outcomes before a rate is considered meaningful. */
const MIN_SAMPLE = 30;
const pct = (v: number | null) => (v === null ? '—' : `${Math.round(v * 100)} %`);

function Kpi({ label, value, hint, warn }: { label: string; value: string; hint?: string; warn?: string }) {
  return (
    <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-5">
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-1 text-3xl font-bold tabular-nums text-slate-900">{value}</p>
      {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
      {warn && <p className="mt-1 text-xs font-medium text-amber-700">{warn}</p>}
    </div>
  );
}

export default function AnalyticsPage() {
  const { t } = useTranslation();
  const [days, setDays] = useState(90);
  const token = useAppSelector((s) => s.auth.token);
  const { data, isLoading, error } = useAnalyticsQuery(days);
  const [exporting, setExporting] = useState(false);
  const [exportError, setExportError] = useState<string | null>(null);

  async function exportData() {
    setExporting(true);
    setExportError(null);
    try {
      const res = await fetch(`${API_BASE_URL}/admin/analytics/export?days=${days}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/x-ndjson' } });
      if (!res.ok) throw new Error(String(res.status));
      const url = URL.createObjectURL(await res.blob());
      const a = document.createElement('a');
      a.href = url;
      a.download = `docthub-pseudonymised-${new Date().toISOString().slice(0, 10)}.jsonl`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setExportError(t('errors.network'));
    } finally {
      setExporting(false);
    }
  }

  if (isLoading) return <PageLoader />;
  if (error || !data) return <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>;
  const s = data as unknown as Indicators;
  const small = (n: number) => (n < MIN_SAMPLE ? t('analytics.smallSample', { n, min: MIN_SAMPLE }) : undefined);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{t('analytics.title')}</h1>
          <p className="text-sm text-slate-500">{t('analytics.subtitle')}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <div className="w-44">
            <Select value={days} onChange={(e) => setDays(Number(e.target.value))} aria-label={t('analytics.period')}>
              {[30, 90, 365].map((d) => <option key={d} value={d}>{t('analytics.lastDays', { count: d })}</option>)}
            </Select>
          </div>
          <Button variant="secondary" onClick={exportData} loading={exporting}>{t('analytics.export')}</Button>
        </div>
      </div>
      {exportError && <Alert tone="danger">{exportError}</Alert>}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Kpi label={t('analytics.concordance')} value={pct(s.concordance.specialty)} hint={t('analytics.basedOn', { n: s.concordance.sample_size })} warn={small(s.concordance.sample_size)} />
        <Kpi label={t('analytics.diagnosisInConditions')} value={pct(s.concordance.diagnosis_in_conditions)} hint={t('analytics.basedOn', { n: s.concordance.diagnosis_sample_size })} warn={small(s.concordance.diagnosis_sample_size)} />
        <Kpi label={t('analytics.responseRate')} value={pct(s.outcomes.response_rate)} hint={t('analytics.collected', { n: s.outcomes.collected })} />
        <Kpi label={t('analytics.helpfulness')} value={s.outcomes.helpfulness_avg === null ? '—' : `${s.outcomes.helpfulness_avg} / 5`} />
        <Kpi label={t('analytics.completion')} value={pct(s.assessments.completion_rate)} hint={`${s.assessments.completed} / ${s.assessments.total}`} />
        <Kpi label={t('analytics.emergencyRate')} value={pct(s.emergency.rate)} hint={t('analytics.alerts', { n: s.emergency.count })} />
        <Kpi label={t('analytics.followedEmergency')} value={pct(s.emergency.followed_to_emergency)} />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <h2 className="font-semibold">{t('analytics.bySpecialty')}</h2>
          {s.concordance.by_recommended_specialty.length === 0 ? <p className="mt-3 text-sm text-slate-500">{t('admin.noData')}</p> : (
            <table className="mt-3 w-full text-sm">
              <thead className="text-xs text-slate-500"><tr><th className="py-1 text-start">{t('analytics.recommended')}</th><th className="py-1 text-end">{t('analytics.outcomes')}</th><th className="py-1 text-end">{t('analytics.confirmed')}</th></tr></thead>
              <tbody className="divide-y divide-slate-100">
                {s.concordance.by_recommended_specialty.map((r) => (
                  <tr key={r.specialty}><td className="py-2">{r.specialty}</td><td className="py-2 text-end tabular-nums">{r.outcomes}</td><td className="py-2 text-end tabular-nums">{pct(r.rate)}</td></tr>
                ))}
              </tbody>
            </table>
          )}
          <h2 className="mt-6 font-semibold">{t('analytics.emergencyByRule')}</h2>
          {Object.keys(s.emergency.by_rule).length === 0 ? <p className="mt-3 text-sm text-slate-500">{t('admin.noData')}</p> : (
            <ul className="mt-3 space-y-1 text-sm">
              {Object.entries(s.emergency.by_rule).map(([code, n]) => <li key={code} className="flex justify-between"><code>{code}</code><span className="tabular-nums">{n}</span></li>)}
            </ul>
          )}
        </Card>
        <Card>
          <h2 className="font-semibold">{t('analytics.contentCoverage')}</h2>
          <table className="mt-3 w-full text-sm">
            <thead className="text-xs text-slate-500"><tr><th className="py-1 text-start" /><th className="py-1 text-end">{t('analytics.total')}</th><th className="py-1 text-end">{t('analytics.approved')}</th><th className="py-1 text-end">{t('analytics.traced')}</th></tr></thead>
            <tbody className="divide-y divide-slate-100">
              {Object.entries(s.content).map(([k, v]) => (
                <tr key={k}><td className="py-2">{t(`analytics.kind.${k}`)}</td><td className="py-2 text-end tabular-nums">{v.total}</td><td className="py-2 text-end tabular-nums">{v.approved}</td><td className="py-2 text-end tabular-nums">{v.approved_with_source_document}</td></tr>
              ))}
            </tbody>
          </table>
          <h2 className="mt-6 font-semibold">{t('analytics.versions')}</h2>
          <p className="mt-1 text-xs text-slate-500">{t('analytics.versionsHint')}</p>
          <ul className="mt-2 space-y-1 text-sm">
            {Object.entries(s.versions).map(([v, n]) => <li key={v} className="flex justify-between"><code>{v || '—'}</code><span className="tabular-nums">{n}</span></li>)}
          </ul>
        </Card>
      </div>
      <p className="text-xs text-slate-500">{t('analytics.privacy')}</p>
    </div>
  );
}
