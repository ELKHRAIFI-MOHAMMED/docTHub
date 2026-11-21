import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Alert, Button, Card, EmptyState, ErrorState, PageLoader } from '../../components/common/ui';
import { BodyMap } from '../../components/medical/BodyMap';
import { Disclaimer } from '../../components/medical/Disclaimer';
import { errorMessage } from '../../utils/errors';
import { useBodyPartsQuery } from '../catalog/catalogApi';
import { useCreateAssessmentMutation } from './assessmentApi';
import { clearInProgress, getInProgress } from './inProgress';

/** Step 1: choose the painful areas, then start an assessment. */
export default function SymptomCheckerPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { locale, countryId, cityId } = useAppSelector((s) => s.preferences);
  const { data: bodyParts = [], isLoading, error, refetch } = useBodyPartsQuery(locale);
  const [create, { isLoading: creating, error: createError }] = useCreateAssessmentMutation();
  const [selected, setSelected] = useState<number[]>([]);
  const [resume, setResume] = useState(getInProgress);

  const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

  async function start() {
    try {
      const assessment = await create({ body_part_ids: selected, country_id: countryId, city_id: cityId }).unwrap();
      navigate(`/symptom-checker/${assessment.id}`);
    } catch {
      /* shown below */
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <p className="text-sm font-semibold text-brand-700">{t('checker.step', { n: 1, total: 3 })}</p>
        <h1 className="text-3xl font-bold text-slate-900">{t('checker.whereTitle')}</h1>
        <p className="mt-1 text-slate-600">{t('checker.whereIntro')}</p>
      </header>

      <Alert tone="warning" title={t('checker.emergencyFirstTitle')}>{t('checker.emergencyFirstText')}</Alert>

      {resume && (
        <Card className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="font-semibold text-slate-900">{t('checker.resumeTitle')}</h2>
            <p className="text-sm text-slate-600">{t('checker.resumeText')}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" onClick={() => { clearInProgress(); setResume(null); }}>{t('checker.resumeDiscard')}</Button>
            <Button onClick={() => navigate(`/symptom-checker/${resume.id}`)}>{t('checker.resume')}</Button>
          </div>
        </Card>
      )}

      <Card>
        {isLoading ? <PageLoader rows={2} /> : error ? <ErrorState error={error} onRetry={refetch} /> : bodyParts.length === 0 ? <EmptyState title={t('checker.noBodyParts')} /> : (
          <BodyMap bodyParts={bodyParts} selected={selected} onToggle={toggle} />
        )}
      </Card>

      {createError && <Alert tone="danger">{errorMessage(createError, t('errors.network'))}</Alert>}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-600" aria-live="polite">
          {selected.length ? t('checker.selectedCount', { count: selected.length }) : t('checker.selectAtLeastOne')}
        </p>
        <Button onClick={start} disabled={!selected.length} loading={creating}>{t('common.continue')}</Button>
      </div>

      <Disclaimer />
    </div>
  );
}
