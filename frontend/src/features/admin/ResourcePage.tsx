import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams } from 'react-router-dom';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, PageLoader, Pagination, Select, Textarea, ErrorState } from '../../components/common/ui';
import { errorMessage, fieldErrors } from '../../utils/errors';
import { useAdminActionMutation, useAdminDeleteMutation, useAdminListQuery, useAdminSaveMutation, useAdminStatusMutation, type AdminRecord } from './adminApi';
import { RESOURCES, TRANSLATION_LOCALES, type FieldDef, type ResourceDef } from './resources';

function display(v: unknown): string {
  if (v === null || v === undefined || v === '') return '—';
  if (typeof v === 'boolean') return v ? '✓' : '✗';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}

function RelationSelect({ field, value, onChange, id }: { field: FieldDef; value: unknown; onChange: (v: unknown) => void; id: string }) {
  const { data } = useAdminListQuery({ resource: field.relation!, extra: { per_page: '100' } });
  return (
    <Select id={id} value={value === null || value === undefined ? '' : String(value)} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}>
      <option value="">—</option>
      {(data?.data ?? []).map((r) => <option key={r.id} value={r.id}>{String(r[field.relationLabel ?? 'name'] ?? r.code ?? r.id)}</option>)}
    </Select>
  );
}

function Editor({ def, record, onClose }: { def: ResourceDef; record: AdminRecord | null; onClose: () => void }) {
  const { t } = useTranslation();
  const [save, { isLoading, error }] = useAdminSaveMutation();
  const initial: Record<string, unknown> = {};
  def.fields.forEach((f) => {
    const raw = record ? (f.fromRecord ? f.fromRecord(record) : record[f.name]) : f.type === 'checkbox' ? true : undefined;
    initial[f.name] = f.type === 'json' ? (raw === undefined || raw === null ? '' : JSON.stringify(raw, null, 2)) : raw;
  });
  const [values, setValues] = useState<Record<string, unknown>>(initial);
  const [translations, setTranslations] = useState<Record<string, Record<string, string>>>((record?.translations as Record<string, Record<string, string>>) ?? {});
  const [jsonErrors, setJsonErrors] = useState<Record<string, string>>({});
  const errors = { ...fieldErrors(error), ...jsonErrors };
  const setValue = (k: string, v: unknown) => setValues((p) => ({ ...p, [k]: v }));

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const body: Record<string, unknown> = {};
    const jErr: Record<string, string> = {};
    def.fields.forEach((f) => {
      const v = values[f.name];
      if (f.type === 'json') {
        if (v === '' || v === undefined) { if (record) body[f.name] = null; return; }
        try { body[f.name] = JSON.parse(String(v)); } catch { jErr[f.name] = t('admin.invalidJson'); }
      } else if (f.type === 'number') {
        body[f.name] = v === '' || v === undefined || v === null ? null : Number(v);
      } else if (v !== undefined) {
        body[f.name] = v === '' ? null : v;
      }
    });
    setJsonErrors(jErr);
    if (Object.keys(jErr).length) return;
    if (def.fields.some((f) => f.translatable)) body.translations = translations;
    try {
      await save({ resource: def.key, id: record?.id, body }).unwrap();
      onClose();
    } catch {
      /* shown */
    }
  }

  return (
    <Card>
      <form onSubmit={onSubmit} className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">{record ? t('admin.edit') : t('admin.create')}</h2>
          <Button type="button" variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
        </div>
        {error && <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>}
        <div className="grid gap-4 md:grid-cols-2">
          {def.fields.map((f) => (
            <div key={f.name} className={f.type === 'textarea' || f.type === 'json' ? 'md:col-span-2' : ''}>
              {f.type === 'checkbox' ? (
                <label className="flex items-center gap-3 pt-6 text-sm font-medium text-slate-700">
                  <input type="checkbox" className="h-4 w-4 accent-brand-600" checked={Boolean(values[f.name])} onChange={(e) => setValue(f.name, e.target.checked)} />
                  {f.label}
                </label>
              ) : (
                <Field label={f.label} error={errors[f.name]} hint={f.hint} required={f.required}>
                  {(id, d) => {
                    const common = { id, 'aria-describedby': d, 'aria-invalid': Boolean(errors[f.name]) };
                    const val = values[f.name] === null || values[f.name] === undefined ? '' : String(values[f.name]);
                    switch (f.type) {
                      case 'textarea': return <Textarea {...common} value={val} onChange={(e) => setValue(f.name, e.target.value)} />;
                      case 'json': return <Textarea {...common} className="font-mono text-xs" value={val} onChange={(e) => setValue(f.name, e.target.value)} />;
                      case 'select': return (
                        <Select {...common} value={val} onChange={(e) => setValue(f.name, e.target.value)}>
                          <option value="">—</option>
                          {f.options!.map((o) => <option key={o} value={o}>{o}</option>)}
                        </Select>
                      );
                      case 'relation': return <RelationSelect field={f} id={id} value={values[f.name]} onChange={(v) => setValue(f.name, v)} />;
                      default: return <Input {...common} type={f.type === 'number' ? 'number' : 'text'} step="any" value={val} onChange={(e) => setValue(f.name, e.target.value)} />;
                    }
                  }}
                </Field>
              )}
              {f.translatable && (
                <div className="mt-2 grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                  {TRANSLATION_LOCALES.map((loc) => (
                    <Field key={loc} label={`${f.label.replace(/\(EN\)/, '')} (${loc.toUpperCase()})`}>
                      {(id) => {
                        const v = translations[loc]?.[f.name] ?? '';
                        const onChange = (val: string) => setTranslations((p) => ({ ...p, [loc]: { ...(p[loc] ?? {}), [f.name]: val } }));
                        return f.type === 'textarea'
                          ? <Textarea id={id} dir={loc === 'ar' ? 'rtl' : 'ltr'} value={v} onChange={(e) => onChange(e.target.value)} />
                          : <Input id={id} dir={loc === 'ar' ? 'rtl' : 'ltr'} value={v} onChange={(e) => onChange(e.target.value)} />;
                      }}
                    </Field>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
        <Button type="submit" loading={isLoading}>{t('common.save')}</Button>
      </form>
    </Card>
  );
}

export default function ResourcePage() {
  const { t } = useTranslation();
  const { resource = '' } = useParams();
  const def = RESOURCES.find((r) => r.key === resource);
  const [page, setPage] = useState(1);
  const [q, setQ] = useState('');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [editing, setEditing] = useState<AdminRecord | null | 'new'>(null);
  const { data, isFetching, error, refetch } = useAdminListQuery({ resource, page, q: search, status }, { skip: !def });
  const [changeStatus] = useAdminStatusMutation();
  const [remove, { error: removeError }] = useAdminDeleteMutation();
  const [runAction, { error: actionError, isLoading: acting }] = useAdminActionMutation();

  if (!def) return <EmptyState title={t('errors.notFound')} />;
  const lastPage = data?.meta?.last_page ?? 1;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-slate-900">{t(def.title)}</h1>
        <Button onClick={() => setEditing('new')}>+ {t('admin.create')}</Button>
      </div>

      {editing && <Editor key={editing === 'new' ? 'new' : editing.id} def={def} record={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}

      <Card className="space-y-4">
        <form className="flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q); }} role="search">
          <Input className="max-w-xs" type="search" placeholder={t('admin.search')} value={q} onChange={(e) => setQ(e.target.value)} aria-label={t('admin.search')} />
          {def.statuses && (
            <Select className="max-w-[180px]" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} aria-label={t('admin.status')}>
              <option value="">{t('admin.allStatuses')}</option>
              {def.statuses.map((s) => <option key={s} value={s}>{s}</option>)}
            </Select>
          )}
          <Button type="submit" variant="secondary">{t('admin.search')}</Button>
        </form>
        {(removeError || actionError) && <Alert tone="danger">{errorMessage(removeError ?? actionError, t('errors.network'))}</Alert>}
        {error && !data ? <ErrorState error={error} onRetry={refetch} /> : !data ? <PageLoader /> : data.data.length === 0 ? <EmptyState title={t('admin.noRecords')} /> : (
          <div className={`overflow-x-auto ${isFetching ? 'opacity-60' : ''}`}>
            <table className="w-full text-start text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase text-slate-500">
                <tr>
                  {def.columns.map((c) => <th key={c.name} className="px-2 py-2 text-start font-semibold">{c.label}</th>)}
                  <th className="px-2 py-2 text-end font-semibold">{t('admin.actions')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((r) => (
                  <tr key={r.id} className="align-top">
                    {def.columns.map((c) => (
                      <td key={c.name} className="max-w-xs truncate px-2 py-2">
                        {c.name === def.statusColumn ? <Badge tone={r[c.name] === 'published' || r[c.name] === 'approved' ? 'success' : 'neutral'}>{display(r[c.name])}</Badge> : display(r[c.name])}
                      </td>
                    ))}
                    <td className="whitespace-nowrap px-2 py-2 text-end">
                      <div className="inline-flex flex-wrap justify-end gap-1">
                        <Button variant="ghost" className="px-2 py-1" onClick={() => setEditing(r)}>{t('admin.edit')}</Button>
                        {def.actions?.map((a) => (
                          <Button key={a.label} variant="ghost" className="px-2 py-1" disabled={acting} onClick={() => runAction(a.path(r.id))}>{t(a.label)}</Button>
                        ))}
                        {def.statuses && (
                          <Select className="w-auto py-1 text-xs" value="" aria-label={t('admin.changeStatus')} onChange={(e) => e.target.value && changeStatus({ resource: def.key, id: r.id, status: e.target.value })}>
                            <option value="">{t('admin.changeStatus')}</option>
                            {def.statuses.filter((s) => s !== r[def.statusColumn!]).map((s) => <option key={s} value={s}>{s}</option>)}
                          </Select>
                        )}
                        <Button variant="ghost" className="px-2 py-1 text-red-600" onClick={() => window.confirm(t('admin.deleteConfirm')) && remove({ resource: def.key, id: r.id })}>{t('admin.delete')}</Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pagination page={page} lastPage={lastPage} onChange={setPage} />
      </Card>
    </div>
  );
}
