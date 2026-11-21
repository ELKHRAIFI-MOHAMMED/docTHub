import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Alert, Button, Card, PageLoader } from '../../components/common/ui';
import { errorMessage, errorStatus } from '../../utils/errors';
import type { Question, QuestionnaireState } from '../../types/api';
import { useEvaluateMutation, useQuestionsQuery, useSubmitAnswersMutation } from './assessmentApi';
import { isAnswerFilled, QuestionField, type DraftAnswer } from './QuestionField';

/** Step 2: dynamic questionnaire, one question at a time. The URL holds the assessment id (refresh-safe). */
export default function QuestionnairePage() {
  const { t } = useTranslation();
  const { id = '' } = useParams();
  const navigate = useNavigate();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: fetched, isLoading, error } = useQuestionsQuery({ id, locale });
  const [submit, { isLoading: saving, error: saveError }] = useSubmitAnswersMutation();
  const [evaluate, { isLoading: evaluating, error: evalError }] = useEvaluateMutation();

  const [state, setState] = useState<QuestionnaireState | null>(null);
  const [currentId, setCurrentId] = useState<number | null>(null);
  const [draft, setDraft] = useState<DraftAnswer>({});

  useEffect(() => {
    if (fetched) {
      setState(fetched);
      setCurrentId(fetched.next_question_id);
    }
  }, [fetched]);

  // Already evaluated (e.g. back button): go to the results.
  useEffect(() => {
    if (state?.status === 'completed') navigate(`/results/${id}`, { replace: true });
  }, [state?.status, id, navigate]);

  const questions = state?.questions ?? [];
  const current: Question | undefined = questions.find((q) => q.id === currentId);
  const answeredCount = questions.filter((q) => q.answer).length;

  useEffect(() => {
    if (current) setDraft(current.answer ? { option_codes: current.answer.options.length ? current.answer.options : undefined, value: current.answer.value ?? undefined } : {});
  }, [current]);

  const previous = useMemo(() => {
    const idx = questions.findIndex((q) => q.id === currentId);
    return idx > 0 ? questions[idx - 1] : undefined;
  }, [questions, currentId]);

  async function runEvaluation() {
    try {
      await evaluate(id).unwrap();
      navigate(`/results/${id}`);
    } catch {
      /* shown below */
    }
  }

  async function saveAnswer() {
    if (!current) return;
    try {
      const next = await submit({ id, answers: [{ question_id: current.id, ...draft }] }).unwrap();
      setState(next);
      // Safety first: any warning sign interrupts the questionnaire immediately.
      if (next.emergency_detected) return runEvaluation();
      setCurrentId(next.next_question_id);
    } catch {
      /* shown below */
    }
  }

  if (isLoading || !state) {
    if (error) {
      return (
        <Alert tone="danger" title={errorStatus(error) === 403 ? t('errors.forbidden') : t('errors.notFound')}>
          <Link to="/symptom-checker" className="underline">{t('checker.startAgain')}</Link>
        </Alert>
      );
    }
    return <PageLoader />;
  }

  const progress = questions.length ? Math.round((answeredCount / questions.length) * 100) : 0;

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <header>
        <p className="text-sm font-semibold text-brand-700">{t('checker.step', { n: 2, total: 3 })}</p>
        <h1 className="text-2xl font-bold text-slate-900">{t('checker.questionsTitle')}</h1>
        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100} aria-label={t('checker.progress')}>
          <div className="h-full rounded-full bg-brand-500 transition-all" style={{ width: `${progress}%` }} />
        </div>
        <p className="mt-1 text-xs text-slate-500">{t('checker.answered', { done: answeredCount, total: questions.length })}</p>
      </header>

      {current ? (
        <Card>
          <h2 id={`q-${current.id}-label`} className="text-lg font-semibold text-slate-900">{current.text}</h2>
          {current.help_text && <p className="mt-1 text-sm text-slate-500">{current.help_text}</p>}
          <div className="mt-5">
            <QuestionField question={current} value={draft} onChange={setDraft} />
          </div>
          {(saveError || evalError) && <Alert tone="danger" className="mt-4">{errorMessage(saveError ?? evalError, t('errors.network'))}</Alert>}
          <div className="mt-6 flex items-center justify-between gap-3">
            <Button variant="ghost" disabled={!previous} onClick={() => previous && setCurrentId(previous.id)}>{t('common.back')}</Button>
            <div className="flex gap-2">
              {!current.is_required && <Button variant="secondary" onClick={() => setCurrentId(state.next_question_id === current.id ? null : state.next_question_id)}>{t('common.skip')}</Button>}
              <Button onClick={saveAnswer} disabled={!isAnswerFilled(current, draft)} loading={saving || evaluating}>{t('common.next')}</Button>
            </div>
          </div>
        </Card>
      ) : (
        <Card className="text-center">
          <div className="text-4xl" aria-hidden>✅</div>
          <h2 className="mt-3 text-xl font-semibold">{t('checker.allAnswered')}</h2>
          <p className="mt-1 text-sm text-slate-600">{t('checker.allAnsweredText')}</p>
          {evalError && <Alert tone="danger" className="mt-4">{errorMessage(evalError, t('errors.network'))}</Alert>}
          <div className="mt-6 flex flex-wrap justify-center gap-3">
            {questions.length > 0 && <Button variant="secondary" onClick={() => setCurrentId(questions[questions.length - 1].id)}>{t('checker.reviewAnswers')}</Button>}
            <Button onClick={runEvaluation} loading={evaluating} disabled={!state.complete}>{t('checker.seeResults')}</Button>
          </div>
        </Card>
      )}
    </div>
  );
}
