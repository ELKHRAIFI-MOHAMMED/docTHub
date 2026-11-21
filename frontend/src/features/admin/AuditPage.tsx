import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Button, Card, EmptyState, Input, PageLoader, Pagination, Select } from '../../components/common/ui';
import { errorMessage } from '../../utils/errors';
import { formatDate } from '../../utils/format';
import { useAdminListQuery } from './adminApi';

type Values = Record<string, unknown> | null;

function show(value: unknown): string {
  if (value === null || value === undefined) return '—';
  const text = typeof value === 'string' ? value : JSON.stringify(value);
  return text.length > 160 ? `${text.slice(0, 160)}…` : text;
}

/** Audit trail: who changed what, when, with old and new values (admin and command-line changes). */
export default function AuditPage() {
  const { t, i18n } = useTranslation();
  const [page, setPage] = useState(1);
  const [action, setAction] = useState('');
  const [type, setType] = useState('');
  const [typeInput, setTypeInput] = useState('');
  const { data, error, isFetching, refetch } = useAdminListQuery({ resource: 'audit-logs', page, extra: { action, auditable_type: type } });

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">{t('audit.title')}</h1>
        <p className="text-sm text-slate-600">{t('audit.subtitle')}</p>
      </div>
      <Card className="space-y-4">
        <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); setPage(1); setType(typeInput.trim()); }}>
          <label className="text-sm">
            <span className="mb-1 block text-slate-600">{t('audit.action')}</span>
            <Select value={action} onChange={(e) => { setPage(1); setAction(e.target.value); }}>
              <option value="">{t('audit.all')}</option>
              <option value="admin.">admin.*</option>
              <option value="knowledge.">knowledge.*</option>
              <option value="auth.">auth.*</option>
            </Select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-slate-600">{t('audit.itemType')}</span>
            <Input value={typeInput} onChange={(e) => setTypeInput(e.target.value)} placeholder="disease, rule, guideline…" />
          </label>
          <Button variant="secondary" type="submit">{t('admin.search')}</Button>
        </form>

        {error && (
          <Alert tone="danger">
            {errorMessage(error, t('errors.network'))}{' '}
            <Button variant="ghost" className="px-2 py-1" onClick={() => refetch()}>{t('common.retry')}</Button>
          </Alert>
        )}
        {!data ? (!error && <PageLoader />) : data.data.length === 0 ? <EmptyState title={t('admin.noRecords')} /> : (
          <ul className={`divide-y divide-slate-100 ${isFetching ? 'opacity-60' : ''}`}>
            {data.data.map((log) => {
              const oldValues = (log.old_values ?? null) as Values;
              const newValues = (log.new_values ?? null) as Values;
              const fields = Array.from(new Set([...Object.keys(oldValues ?? {}), ...Object.keys(newValues ?? {})]));
              const user = log.user as { name?: string; email?: string } | null;
              const agent = String(log.user_agent ?? '');
              const who = user?.name ? `${user.name} (${user.email})` : agent.startsWith('artisan ') ? agent : t('audit.system');
              return (
                <li key={log.id} className="py-3 text-sm">
                  <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <time className="tabular-nums text-slate-500" dateTime={String(log.created_at)}>{formatDate(String(log.created_at), i18n.language)}</time>
                    <span className="font-medium text-slate-900">{who}</span>
                    <code className="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{String(log.action)}</code>
                    {log.auditable_type ? <span className="text-slate-600">{String(log.auditable_type)} #{String(log.auditable_id)}</span> : null}
                  </div>
                  {fields.length > 0 ? (
                    <details className="mt-1">
                      <summary className="cursor-pointer text-brand-700">{t('audit.changes', { count: fields.length })}</summary>
                      <table className="mt-2 w-full text-xs">
                        <thead className="text-slate-500">
                          <tr><th className="py-1 text-start">{t('audit.field')}</th><th className="py-1 text-start">{t('audit.old')}</th><th className="py-1 text-start">{t('audit.new')}</th></tr>
                        </thead>
                        <tbody>
                          {fields.map((f) => (
                            <tr key={f} className="align-top">
                              <td className="py-1 pe-2 font-mono">{f}</td>
                              <td className="break-all py-1 pe-2 text-red-700">{show(oldValues?.[f])}</td>
                              <td className="break-all py-1 text-green-700">{show(newValues?.[f])}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </details>
                  ) : null}
                </li>
              );
            })}
          </ul>
        )}
        <Pagination page={page} lastPage={data?.last_page ?? 1} onChange={setPage} />
      </Card>
    </div>
  );
}
