import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge, Button, Card, EmptyState, Input, PageLoader, Pagination, ErrorState } from '../../components/common/ui';
import { useAdminListQuery, useSetPatientActiveMutation } from './adminApi';

/** Patients: account data and counts only (no assessment content: data minimisation). */
export default function PatientsAdminPage() {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const [q, setQ] = useState('');
  const [search, setSearch] = useState('');
  const { data, error, refetch } = useAdminListQuery({ resource: 'patients', page, q: search });
  const [setActive] = useSetPatientActiveMutation();

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-slate-900">{t('admin.res.patients')}</h1>
      <Card className="space-y-4">
        <form className="flex gap-2" role="search" onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q); }}>
          <Input className="max-w-xs" type="search" value={q} onChange={(e) => setQ(e.target.value)} aria-label={t('admin.search')} placeholder={t('admin.search')} />
          <Button variant="secondary" type="submit">{t('admin.search')}</Button>
        </form>
        {error && !data ? <ErrorState error={error} onRetry={refetch} /> : !data ? <PageLoader /> : data.data.length === 0 ? <EmptyState title={t('admin.noRecords')} /> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase text-slate-500">
                <tr>
                  <th className="px-2 py-2 text-start">{t('admin.name')}</th><th className="px-2 py-2 text-start">Email</th>
                  <th className="px-2 py-2 text-start">{t('location.city')}</th><th className="px-2 py-2 text-start">{t('admin.stats.assessments')}</th>
                  <th className="px-2 py-2 text-start">{t('admin.status')}</th><th className="px-2 py-2" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((p) => (
                  <tr key={p.id}>
                    <td className="px-2 py-2">{String(p.first_name)} {String(p.last_name)}</td>
                    <td className="px-2 py-2">{String(p.email)}</td>
                    <td className="px-2 py-2">{[p.city, p.country].filter(Boolean).join(', ')}</td>
                    <td className="px-2 py-2 tabular-nums">{String(p.assessments_count)}</td>
                    <td className="px-2 py-2">{p.is_active ? <Badge tone="success">{t('admin.active')}</Badge> : <Badge tone="danger">{t('admin.disabled')}</Badge>}</td>
                    <td className="px-2 py-2 text-end">
                      <Button variant="ghost" className="px-2 py-1" onClick={() => setActive({ id: p.id, is_active: !p.is_active })}>
                        {p.is_active ? t('admin.disable') : t('admin.enable')}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pagination page={page} lastPage={data?.last_page ?? 1} onChange={setPage} />
      </Card>
    </div>
  );
}
