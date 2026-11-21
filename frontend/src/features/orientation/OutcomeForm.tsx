import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useAppSelector } from '../../app/store';
import { Alert, Button, Field, Select, cx } from '../../components/common/ui';
import type { Disease } from '../../types/api';
import { errorMessage } from '../../utils/errors';
import { useSpecialtiesQuery } from '../catalog/catalogApi';
import { useOutcomeQuery, useSaveOutcomeMutation } from '../symptom-checker/assessmentApi';

type YesNo = 'yes' | 'no' | '';

/**
 * "What did the doctor conclude?" — structured answers only (no free text).
 * This ground truth is what lets docTHub measure and improve its orientation.
 */
export function OutcomeForm({ assessmentId, conditions, emergency }: { assessmentId: string; conditions: Disease[]; emergency: boolean }) {
  const { t } = useTranslation();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: specialties = [] } = useSpecialtiesQuery(locale);
  const { data: existing } = useOutcomeQuery(assessmentId);
  const [save, { isLoading, error, isSuccess }] = useSaveOutcomeMutation();

  const [consulted, setConsulted] = useState<YesNo>('');
  const [specialtyId, setSpecialtyId] = useState('');
  const [diseaseId, setDiseaseId] = useState('');
  const [emergencyVisit, setEmergencyVisit] = useState<YesNo>('');
  const [helpfulness, setHelpfulness] = useState(0);
  const [editing, setEditing] = useState(false);

  useEffect(() => {
    if (existing?.collected) {
      setConsulted(existing.consulted ? 'yes' : 'no');
      setSpecialtyId(existing.consulted_specialty_id ? String(existing.consulted_specialty_id) : '');
      setDiseaseId(existing.diagnosis_disease_id ? String(existing.diagnosis_disease_id) : '');
      setEmergencyVisit(existing.went_to_emergency === null ? '' : existing.went_to_emergency ? 'yes' : 'no');
      setHelpfulness(existing.helpfulness ?? 0);
    }
  }, [existing]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!consulted) return;
    try {
      await save({
        id: assessmentId,
        body: {
          consulted: consulted === 'yes',
          consulted_specialty_id: specialtyId ? Number(specialtyId) : null,
          diagnosis_disease_id: diseaseId ? Number(diseaseId) : null,
          went_to_emergency: emergencyVisit ? emergencyVisit === 'yes' : null,
          helpfulness: helpfulness || null,
        },
      }).unwrap();
      setEditing(false);
    } catch {
      /* shown below */
    }
  }

  const yesNo = (name: string, value: YesNo, set: (v: YesNo) => void, label: string) => (
    <fieldset>
      <legend className="text-sm font-medium text-slate-700">{label}</legend>
      <div className="mt-2 flex gap-2">
        {(['yes', 'no'] as const).map((v) => (
          <label key={v} className={cx('flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-sm ring-1', value === v ? 'bg-brand-50 text-brand-900 ring-brand-400' : 'bg-white ring-slate-200')}>
            <input type="radio" name={name} className="accent-brand-600" checked={value === v} onChange={() => set(v)} />
            {t(`common.${v}`)}
          </label>
        ))}
      </div>
    </fieldset>
  );

  const done = (existing?.collected || isSuccess) && !editing;

  return (
    <section id="follow-up" aria-labelledby="follow-up-title" className="no-print rounded-2xl bg-slate-100/70 p-5 sm:p-6">
      <h2 id="follow-up-title" className="text-lg font-semibold text-slate-900">{t('followUp.title')}</h2>
      {done ? (
        <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-slate-700" role="status">{t('followUp.thanks')}</p>
          <Button variant="ghost" onClick={() => setEditing(true)}>{t('followUp.edit')}</Button>
        </div>
      ) : (
        <form className="mt-3 space-y-4" onSubmit={onSubmit}>
          <p className="text-sm text-slate-600">{t('followUp.intro')}</p>
          {yesNo('consulted', consulted, setConsulted, t('followUp.consulted'))}
          {consulted === 'yes' && (
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label={t('followUp.specialty')}>
                {(id) => (
                  <Select id={id} value={specialtyId} onChange={(e) => setSpecialtyId(e.target.value)}>
                    <option value="">{t('followUp.dontKnow')}</option>
                    {specialties.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </Select>
                )}
              </Field>
              <Field label={t('followUp.diagnosis')}>
                {(id) => (
                  <Select id={id} value={diseaseId} onChange={(e) => setDiseaseId(e.target.value)}>
                    <option value="">{t('followUp.otherOrUnknown')}</option>
                    {conditions.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </Select>
                )}
              </Field>
            </div>
          )}
          {(emergency || consulted !== '') && yesNo('emergency-visit', emergencyVisit, setEmergencyVisit, t('followUp.wentToEmergency'))}
          <fieldset>
            <legend className="text-sm font-medium text-slate-700">{t('followUp.helpfulness')}</legend>
            <div className="mt-2 flex gap-1.5" role="radiogroup">
              {[1, 2, 3, 4, 5].map((n) => (
                <label key={n} className={cx('flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg text-sm font-semibold ring-1', helpfulness === n ? 'bg-brand-600 text-white ring-transparent' : 'bg-white text-slate-700 ring-slate-200')}>
                  <input type="radio" name="helpfulness" className="sr-only" checked={helpfulness === n} onChange={() => setHelpfulness(n)} />
                  {n}
                </label>
              ))}
            </div>
          </fieldset>
          {error && <Alert tone="danger">{errorMessage(error, t('errors.network'))}</Alert>}
          <div className="flex flex-wrap items-center gap-3">
            <Button type="submit" disabled={!consulted} loading={isLoading}>{t('followUp.send')}</Button>
            <span className="text-xs text-slate-500">{t('followUp.privacy')}</span>
          </div>
        </form>
      )}
    </section>
  );
}
