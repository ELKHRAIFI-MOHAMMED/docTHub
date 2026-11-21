import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useAppSelector } from '../../app/store';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, PageLoader, Pagination, Select, Textarea, ErrorState } from '../../components/common/ui';
import { errorMessage, fieldErrors } from '../../utils/errors';
import { useCitiesQuery, useCountriesQuery, useLanguagesQuery } from '../catalog/catalogApi';
import {
  useAdminGetQuery, useAdminListQuery, useAdminSaveMutation, useReviewDocumentMutation, useUploadDoctorFileMutation,
  useVerifyDoctorMutation, type AdminRecord,
} from './adminApi';

type Loc = { country_id?: number; city_id?: number; address?: string; label?: string; latitude?: number | null; longitude?: number | null };
const verificationTone = { verified: 'success', pending: 'warning', rejected: 'danger', suspended: 'danger' } as const;

function DoctorEditor({ id, onClose }: { id: number | null; onClose: () => void }) {
  const { t } = useTranslation();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: record, isLoading } = useAdminGetQuery({ resource: 'doctors', id: id ?? 0 }, { skip: !id });
  if (id && (isLoading || !record)) return <PageLoader />;
  return <DoctorForm key={id ?? 'new'} record={record ?? null} onClose={onClose} locale={locale} t={t} />;
}

function DoctorForm({ record, onClose, locale, t }: { record: AdminRecord | null; onClose: () => void; locale: string; t: (k: string, o?: Record<string, unknown>) => string }) {
  const [save, { isLoading, error }] = useAdminSaveMutation();
  const [verify, { error: verifyError }] = useVerifyDoctorMutation();
  const [upload, { isLoading: uploading, error: uploadError }] = useUploadDoctorFileMutation();
  const [review] = useReviewDocumentMutation();
  const { data: specialtyPage } = useAdminListQuery({ resource: 'specialties', extra: { per_page: '100' } });
  const { data: languages = [] } = useLanguagesQuery();
  const { data: countries = [] } = useCountriesQuery(locale);

  const r = (record ?? {}) as Record<string, unknown>;
  const recLoc = ((r.locations as Loc[]) ?? [])[0] ?? {};
  const [f, setF] = useState<Record<string, string | boolean>>({
    title: String(r.title ?? 'Dr.'), first_name: String(r.first_name ?? ''), last_name: String(r.last_name ?? ''),
    bio: String(r.bio ?? ''), qualifications: String(r.qualifications ?? ''), years_of_experience: r.years_of_experience != null ? String(r.years_of_experience) : '',
    email: String(r.email ?? ''), phone: String(r.phone ?? ''), website: String(r.website ?? ''),
    consultation_fee: r.consultation_fee != null ? String(r.consultation_fee) : '', fee_currency: String(r.fee_currency ?? ''),
    is_demo: Boolean(r.is_demo), status: String(r.status ?? 'draft'),
  });
  const [specialtyIds, setSpecialtyIds] = useState<number[]>(((r.specialties as { id: number }[]) ?? []).map((s) => s.id));
  const [languageIds, setLanguageIds] = useState<number[]>(((r.languages as { id: number }[]) ?? []).map((l) => l.id));
  const [loc, setLoc] = useState<Loc>(recLoc);
  const [translations, setTranslations] = useState<Record<string, Record<string, string>>>((r.translations as Record<string, Record<string, string>>) ?? {});
  const { data: cities = [] } = useCitiesQuery({ countryId: loc.country_id ?? 0, locale }, { skip: !loc.country_id });
  const errors = fieldErrors(error);
  const set = (k: string) => (e: { target: { value: string } }) => setF((p) => ({ ...p, [k]: e.target.value }));
  const toggle = (list: number[], setList: (v: number[]) => void, v: number) => setList(list.includes(v) ? list.filter((x) => x !== v) : [...list, v]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const n = (v: string | boolean) => (v === '' ? null : Number(v));
    try {
      await save({
        resource: 'doctors', id: record?.id,
        body: {
          ...f,
          years_of_experience: n(f.years_of_experience), consultation_fee: n(f.consultation_fee),
          fee_currency: f.fee_currency || null, email: f.email || null, phone: f.phone || null, website: f.website || null,
          specialties: specialtyIds.map((id, i) => ({ id, is_primary: i === 0 })),
          language_ids: languageIds,
          locations: [{ ...loc, latitude: loc.latitude ?? null, longitude: loc.longitude ?? null, is_primary: true }],
          translations,
        },
      }).unwrap();
      onClose();
    } catch {
      /* shown */
    }
  }

  const fileUpload = (kind: 'photo' | 'documents') => async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!record) return;
    const form = new FormData(e.currentTarget);
    await upload({ id: record.id, kind, form }).unwrap().catch(() => undefined);
    e.currentTarget?.reset();
  };

  const text = (k: string, label: string, type = 'text') => (
    <Field label={label} error={errors[k]}>
      {(id, d) => <Input id={id} aria-describedby={d} aria-invalid={Boolean(errors[k])} type={type} value={String(f[k])} onChange={set(k)} />}
    </Field>
  );
  const documents = (r.documents as { id: number; type: string; original_name: string; status: string }[]) ?? [];

  return (
    <div className="space-y-4">
      <Card>
        <form className="space-y-5" onSubmit={onSubmit}>
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold">{record ? t('admin.edit') : t('admin.create')}</h2>
            <Button type="button" variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
          </div>
          {error && <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>}
          <div className="grid gap-4 md:grid-cols-3">
            {text('title', 'Title')}{text('first_name', t('profile.firstName'))}{text('last_name', t('profile.lastName'))}
            {text('years_of_experience', 'Years of experience', 'number')}{text('consultation_fee', t('doctor.fee'), 'number')}{text('fee_currency', 'Currency (ISO)')}
            {text('email', 'Email', 'email')}{text('phone', t('profile.phone'), 'tel')}{text('website', 'Website', 'url')}
          </div>
          <div className="grid gap-4 md:grid-cols-3">
            {(['bio', 'qualifications'] as const).map((k) => (
              <div key={k} className="space-y-2 md:col-span-3">
                <Field label={`${k === 'bio' ? t('doctor.about') : t('doctor.qualifications')} (EN)`} error={errors[k]}>
                  {(id) => <Textarea id={id} value={String(f[k])} onChange={set(k)} />}
                </Field>
                <div className="grid gap-2 sm:grid-cols-2">
                  {['fr', 'ar'].map((l) => (
                    <Field key={l} label={`${l.toUpperCase()}`}>
                      {(id) => <Textarea id={id} dir={l === 'ar' ? 'rtl' : 'ltr'} className="min-h-16" value={translations[l]?.[k] ?? ''} onChange={(e) => setTranslations((p) => ({ ...p, [l]: { ...(p[l] ?? {}), [k]: e.target.value } }))} />}
                    </Field>
                  ))}
                </div>
              </div>
            ))}
          </div>

          <fieldset>
            <legend className="text-sm font-medium text-slate-700">{t('doctors.specialty')} — {t('admin.firstIsPrimary')}</legend>
            {errors.specialties && <p className="text-xs text-red-600">{errors.specialties}</p>}
            <div className="mt-2 flex flex-wrap gap-2">
              {(specialtyPage?.data ?? []).map((s) => (
                <label key={s.id} className="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm ring-1 ring-slate-200">
                  <input type="checkbox" className="accent-brand-600" checked={specialtyIds.includes(s.id)} onChange={() => toggle(specialtyIds, setSpecialtyIds, s.id)} />
                  {String(s.name)}{specialtyIds[0] === s.id && <Badge tone="info">primary</Badge>}
                </label>
              ))}
            </div>
          </fieldset>
          <fieldset>
            <legend className="text-sm font-medium text-slate-700">{t('doctor.speaks')}</legend>
            <div className="mt-2 flex flex-wrap gap-2">
              {languages.map((l) => (
                <label key={l.id} className="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm ring-1 ring-slate-200">
                  <input type="checkbox" className="accent-brand-600" checked={languageIds.includes(l.id)} onChange={() => toggle(languageIds, setLanguageIds, l.id)} />{l.native_name}
                </label>
              ))}
            </div>
          </fieldset>

          <fieldset className="grid gap-4 rounded-xl bg-slate-50 p-4 md:grid-cols-3">
            <legend className="px-1 text-sm font-medium text-slate-700">{t('doctor.location')}</legend>
            <Field label={t('location.country')} error={errors['locations.0.country_id']}>
              {(id) => (
                <Select id={id} value={loc.country_id ?? ''} onChange={(e) => setLoc({ ...loc, country_id: Number(e.target.value) || undefined, city_id: undefined })}>
                  <option value="">—</option>{countries.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              )}
            </Field>
            <Field label={t('location.city')} error={errors['locations.0.city_id']}>
              {(id) => (
                <Select id={id} value={loc.city_id ?? ''} onChange={(e) => setLoc({ ...loc, city_id: Number(e.target.value) || undefined })}>
                  <option value="">—</option>{cities.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              )}
            </Field>
            <Field label="Address">{(id) => <Input id={id} value={loc.address ?? ''} onChange={(e) => setLoc({ ...loc, address: e.target.value })} />}</Field>
            <Field label="Latitude">{(id) => <Input id={id} type="number" step="any" value={loc.latitude ?? ''} onChange={(e) => setLoc({ ...loc, latitude: e.target.value === '' ? null : Number(e.target.value) })} />}</Field>
            <Field label="Longitude">{(id) => <Input id={id} type="number" step="any" value={loc.longitude ?? ''} onChange={(e) => setLoc({ ...loc, longitude: e.target.value === '' ? null : Number(e.target.value) })} />}</Field>
          </fieldset>

          <div className="flex flex-wrap items-center gap-6">
            <Field label={t('admin.status')}>
              {(id) => <Select id={id} value={String(f.status)} onChange={set('status')}>{['draft', 'published', 'archived'].map((s) => <option key={s}>{s}</option>)}</Select>}
            </Field>
            <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" className="accent-brand-600" checked={Boolean(f.is_demo)} onChange={(e) => setF((p) => ({ ...p, is_demo: e.target.checked }))} />{t('admin.isDemo')}</label>
          </div>
          <Button type="submit" loading={isLoading}>{t('common.save')}</Button>
        </form>
      </Card>

      {record && (
        <Card className="space-y-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-lg font-semibold">{t('admin.verification')}</h2>
            <Badge tone={verificationTone[r.verification_status as keyof typeof verificationTone] ?? 'neutral'}>{String(r.verification_status)}</Badge>
          </div>
          {(verifyError || uploadError) && <Alert tone="danger">{errorMessage(verifyError ?? uploadError, t('errors.network'))}</Alert>}
          <div>
            <h3 className="text-sm font-semibold text-slate-700">{t('admin.documents')}</h3>
            {documents.length === 0 ? <p className="mt-1 text-sm text-slate-500">{t('admin.noDocuments')}</p> : (
              <ul className="mt-2 divide-y divide-slate-100 text-sm">
                {documents.map((d) => (
                  <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>{d.type} — {d.original_name} <Badge tone={d.status === 'approved' ? 'success' : d.status === 'rejected' ? 'danger' : 'warning'}>{d.status}</Badge></span>
                    <span className="flex gap-1">
                      <Button variant="ghost" className="px-2 py-1" onClick={() => review({ id: record.id, documentId: d.id, status: 'approved' })}>{t('admin.approve')}</Button>
                      <Button variant="ghost" className="px-2 py-1 text-red-600" onClick={() => review({ id: record.id, documentId: d.id, status: 'rejected' })}>{t('admin.reject')}</Button>
                    </span>
                  </li>
                ))}
              </ul>
            )}
            <form className="mt-3 flex flex-wrap items-end gap-2" onSubmit={fileUpload('documents')}>
              <Select name="type" className="w-auto" aria-label="Document type">{['license', 'diploma', 'identity', 'other'].map((x) => <option key={x}>{x}</option>)}</Select>
              <input name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required className="text-sm" aria-label={t('admin.uploadDocument')} />
              <Button type="submit" variant="secondary" loading={uploading}>{t('admin.uploadDocument')}</Button>
            </form>
          </div>
          <form className="flex flex-wrap items-end gap-2" onSubmit={fileUpload('photo')}>
            <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" required className="text-sm" aria-label={t('admin.uploadPhoto')} />
            <Button type="submit" variant="secondary" loading={uploading}>{t('admin.uploadPhoto')}</Button>
          </form>
          <div className="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            <Button onClick={() => verify({ id: record.id, verification_status: 'verified' })}>{t('admin.verify')}</Button>
            <Button variant="secondary" onClick={() => verify({ id: record.id, verification_status: 'pending' })}>{t('admin.setPending')}</Button>
            <Button variant="danger" onClick={() => verify({ id: record.id, verification_status: 'rejected' })}>{t('admin.reject')}</Button>
            <Button variant="danger" onClick={() => verify({ id: record.id, verification_status: 'suspended' })}>{t('admin.suspend')}</Button>
          </div>
          <p className="text-xs text-slate-500">{t('admin.verifyRule')}</p>
        </Card>
      )}
    </div>
  );
}

export default function DoctorsAdminPage() {
  const { t } = useTranslation();
  const [page, setPage] = useState(1);
  const [q, setQ] = useState('');
  const [search, setSearch] = useState('');
  const [verification, setVerification] = useState('');
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const { data, error, isFetching, refetch } = useAdminListQuery({ resource: 'doctors', page, q: search, extra: { verification_status: verification } });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold text-slate-900">{t('admin.res.doctors')}</h1>
        <Button onClick={() => setEditing('new')}>+ {t('admin.create')}</Button>
      </div>
      {editing !== null && <DoctorEditor id={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <Card className="space-y-4">
        <form className="flex flex-wrap gap-2" role="search" onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q); }}>
          <Input className="max-w-xs" type="search" value={q} onChange={(e) => setQ(e.target.value)} aria-label={t('admin.search')} placeholder={t('admin.search')} />
          <Select className="max-w-[180px]" value={verification} onChange={(e) => { setVerification(e.target.value); setPage(1); }} aria-label={t('admin.verification')}>
            <option value="">{t('admin.allStatuses')}</option>
            {['pending', 'verified', 'rejected', 'suspended'].map((s) => <option key={s}>{s}</option>)}
          </Select>
          <Button variant="secondary" type="submit">{t('admin.search')}</Button>
        </form>
        {error && !data ? <ErrorState error={error} onRetry={refetch} /> : !data ? <PageLoader /> : data.data.length === 0 ? <EmptyState title={t('admin.noRecords')} /> : (
          <div className={`overflow-x-auto ${isFetching ? 'opacity-60' : ''}`}>
            <table className="w-full text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase text-slate-500">
                <tr><th className="px-2 py-2 text-start">{t('admin.name')}</th><th className="px-2 py-2 text-start">{t('doctors.specialty')}</th><th className="px-2 py-2 text-start">{t('admin.verification')}</th><th className="px-2 py-2 text-start">{t('admin.status')}</th><th /></tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.data.map((d) => (
                  <tr key={d.id}>
                    <td className="px-2 py-2">{String(d.full_name)} {Boolean(d.is_demo) && <Badge tone="warning">demo</Badge>}</td>
                    <td className="px-2 py-2">{((d.specialties as { name: string }[]) ?? []).map((s) => s.name).join(', ')}</td>
                    <td className="px-2 py-2"><Badge tone={verificationTone[d.verification_status as keyof typeof verificationTone] ?? 'neutral'}>{String(d.verification_status)}</Badge></td>
                    <td className="px-2 py-2">{String(d.status)}</td>
                    <td className="px-2 py-2 text-end"><Button variant="ghost" className="px-2 py-1" onClick={() => setEditing(d.id)}>{t('admin.edit')}</Button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Pagination page={page} lastPage={data?.meta?.last_page ?? 1} onChange={setPage} />
      </Card>
    </div>
  );
}
