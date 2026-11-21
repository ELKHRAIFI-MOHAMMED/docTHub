import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Alert, Badge, Card, EmptyState, PageLoader } from '../../components/common/ui';
import { Disclaimer } from '../../components/medical/Disclaimer';
import { EmergencyAlert } from '../../components/medical/EmergencyAlert';
import { errorMessage } from '../../utils/errors';
import { useResultsQuery } from '../symptom-checker/assessmentApi';
import { CaseSummary } from './CaseSummary';
import { OutcomeForm } from './OutcomeForm';

const severityTone = { low: 'neutral', moderate: 'warning', high: 'danger' } as const;

/** Step 3: medical orientation results. Emergency instructions always come first and replace the normal flow. */
export default function ResultsPage() {
  const { t } = useTranslation();
  const { id = '' } = useParams();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: r, isLoading, error } = useResultsQuery({ id, locale });

  if (isLoading) return <PageLoader />;
  if (error || !r) return <Alert tone="danger">{errorMessage(error, t('errors.notFound'))}</Alert>;

  if (r.emergency) {
    return (
      <div className="mx-auto max-w-3xl space-y-6">
        <EmergencyAlert emergency={r.emergency} />
        <CaseSummary summary={r.case_summary} />
        <Disclaimer text={r.disclaimer} />
        {r.follow_up.available && <OutcomeForm assessmentId={r.id} conditions={[]} emergency />}
      </div>
    );
  }

  const recommended = r.specialties[0];

  return (
    <div className="space-y-6">
      <header>
        <p className="text-sm font-semibold text-brand-700">{t('checker.step', { n: 3, total: 3 })}</p>
        <h1 className="text-3xl font-bold text-slate-900">{t('results.title')}</h1>
      </header>

      <Disclaimer text={r.disclaimer} />
      {r.unreviewed_notice && <Alert tone="warning" title={t('results.sampleContent')}>{r.unreviewed_notice}</Alert>}
      <CaseSummary summary={r.case_summary} />

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <h2 className="text-lg font-semibold">{t('results.possibleConditions')}</h2>
            <p className="mt-1 text-sm text-slate-500">{t('results.possibleConditionsNote')}</p>
            {r.conditions.length === 0 ? (
              <p className="mt-4 text-sm text-slate-600">{t('results.noConditions')}</p>
            ) : (
              <ul className="mt-4 space-y-3">
                {r.conditions.map(({ disease, match_strength }) => (
                  <li key={disease.id} className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <h3 className="font-semibold text-slate-900">{disease.name}</h3>
                      <div className="flex gap-2">
                        <Badge tone="info">{t(`results.match.${match_strength}`)}</Badge>
                        {disease.severity === 'high' && <Badge tone={severityTone[disease.severity]}>{t('results.needsPromptCare')}</Badge>}
                      </div>
                    </div>
                    {disease.description && <p className="mt-1 text-sm text-slate-600">{disease.description}</p>}
                    {disease.symptoms && disease.symptoms.length > 0 && (
                      <p className="mt-2 text-xs text-slate-500">{t('results.associatedSymptoms')}: {disease.symptoms.map((s) => s.name).join(', ')}</p>
                    )}
                    {disease.specialty && <p className="mt-1 text-xs text-slate-500">{t('results.specialty')}: {disease.specialty.name}</p>}
                  </li>
                ))}
              </ul>
            )}
          </Card>

          {r.guidelines.length > 0 && (
            <Card>
              <h2 className="text-lg font-semibold">{t('results.advice')}</h2>
              <ul className="mt-4 space-y-4">
                {r.guidelines.map((g) => (
                  <li key={g.id} className="border-s-4 border-brand-300 ps-4">
                    <h3 className="font-semibold text-slate-900">{g.title}</h3>
                    <p className="mt-1 text-sm leading-relaxed text-slate-700">{g.content}</p>
                    <p className="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                      {g.review_status === 'approved'
                        ? <Badge tone="success">{t('results.reviewed')}</Badge>
                        : <Badge tone="warning">{t('results.notReviewed')}</Badge>}
                      {g.source_name && <span>{t('results.source')}: {g.source_url ? <a href={g.source_url} target="_blank" rel="noreferrer noopener" className="underline">{g.source_name}</a> : g.source_name}</span>}
                    </p>
                  </li>
                ))}
              </ul>
            </Card>
          )}

          <Card>
            <h2 className="text-lg font-semibold">{t('results.summary')}</h2>
            <p className="mt-2 text-sm text-slate-600">{t('results.areas')}: {r.body_parts.map((b) => b.name).join(', ')}</p>
            <dl className="mt-3 divide-y divide-slate-100 text-sm">
              {r.summary.map((s) => (
                <div key={s.question} className="grid gap-1 py-2 sm:grid-cols-2">
                  <dt className="text-slate-500">{s.question}</dt>
                  <dd className="font-medium text-slate-800">{s.answer}</dd>
                </div>
              ))}
            </dl>
          </Card>
          {r.follow_up.available && <OutcomeForm assessmentId={r.id} conditions={r.conditions.map((c) => c.disease)} emergency={false} />}
        </div>

        <aside className="space-y-6">
          {recommended ? (
            <section className="rounded-2xl bg-brand-700 p-5 text-white shadow-sm sm:p-6">
              <p className="text-sm text-brand-100">{t('results.recommendedSpecialty')}</p>
              <p className="mt-1 text-2xl font-bold">{recommended.name}</p>
              <Link to={`/doctors?specialty_id=${recommended.id}`} className="mt-5 inline-block w-full rounded-xl bg-white px-4 py-3 text-center font-semibold text-brand-800 hover:bg-brand-50">
                {t('results.findDoctors')}
              </Link>
              {r.specialties.length > 1 && (
                <p className="mt-4 text-xs text-brand-100">
                  {t('results.otherSpecialties')}:{' '}
                  {r.specialties.slice(1).map((s, i) => (
                    <span key={s.id}>{i > 0 && ', '}<Link className="underline" to={`/doctors?specialty_id=${s.id}`}>{s.name}</Link></span>
                  ))}
                </p>
              )}
            </section>
          ) : (
            <EmptyState title={t('results.noSpecialty')} />
          )}
          <Alert tone="warning" title={t('results.worseTitle')}>{t('results.worseText')}</Alert>
          <Link to="/symptom-checker" className="block text-center text-sm font-medium text-brand-700 hover:underline">{t('checker.startAgain')}</Link>
        </aside>
      </div>
    </div>
  );
}
