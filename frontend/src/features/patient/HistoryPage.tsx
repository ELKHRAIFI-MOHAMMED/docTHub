import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Badge, EmptyState, PageLoader, Pagination, ErrorState } from '../../components/common/ui';
import { formatDate } from '../../utils/format';
import { useHistoryQuery } from './patientApi';

export default function HistoryPage() {
  const { t, i18n } = useTranslation();
  const locale = useAppSelector((s) => s.preferences.locale);
  const [page, setPage] = useState(1);
  const { data, isLoading, error, refetch } = useHistoryQuery({ page, locale });

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-slate-900">{t('nav.history')}</h1>
      {isLoading ? <PageLoader /> : error ? <ErrorState error={error} onRetry={refetch} /> : !data?.data.length ? (
        <EmptyState icon="📋" title={t('history.empty')}><Link to="/symptom-checker" className="text-brand-700 underline">{t('nav.checkSymptoms')}</Link></EmptyState>
      ) : (
        <>
          <ul className="space-y-3">
            {data.data.map((a) => (
              <li key={a.id}>
                <Link
                  to={a.status === 'completed' ? `/results/${a.id}` : `/symptom-checker/${a.id}`}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-4 ring-1 ring-slate-200 hover:ring-brand-300"
                >
                  <div>
                    <p className="font-semibold text-slate-900">{a.body_parts.map((b) => b.name).join(', ')}</p>
                    <p className="text-xs text-slate-500">{formatDate(a.created_at, i18n.language)}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {a.has_emergency_alert && <Badge tone="danger">{t('history.emergency')}</Badge>}
                    {a.recommended_specialty && <Badge tone="info">{a.recommended_specialty.name}</Badge>}
                    {a.status !== 'completed' && <Badge tone="neutral">{t('history.inProgress')}</Badge>}
                    {a.status === 'completed' && a.follow_up_collected === false && <Badge tone="warning">{t('history.feedbackWanted')}</Badge>}
                  </div>
                </Link>
              </li>
            ))}
          </ul>
          <Pagination page={data.meta.current_page} lastPage={data.meta.last_page} onChange={setPage} />
        </>
      )}
    </div>
  );
}
