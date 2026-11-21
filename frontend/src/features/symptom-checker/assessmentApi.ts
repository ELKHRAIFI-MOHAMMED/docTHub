import { api } from '../../app/api';
import type { AssessmentSummary, OrientationResult, Outcome, QuestionnaireState } from '../../types/api';

export type OutcomeInput = Partial<Omit<Outcome, 'collected' | 'collected_at'>> & { consulted: boolean };

export interface AnswerInput {
  question_id: number;
  option_codes?: string[];
  value?: string;
}

const unwrap = <T,>(r: { data: T }) => r.data;

export const assessmentApi = api.injectEndpoints({
  endpoints: (b) => ({
    createAssessment: b.mutation<AssessmentSummary, { body_part_ids: number[]; country_id?: number | null; city_id?: number | null }>({
      query: (body) => ({ url: '/assessments', method: 'POST', body }),
      transformResponse: unwrap,
      invalidatesTags: ['Assessments'],
    }),
    questions: b.query<QuestionnaireState, { id: string; locale: string }>({
      query: ({ id }) => `/assessments/${id}/questions`,
      transformResponse: unwrap,
    }),
    submitAnswers: b.mutation<QuestionnaireState, { id: string; answers: AnswerInput[] }>({
      query: ({ id, answers }) => ({ url: `/assessments/${id}/answers`, method: 'POST', body: { answers } }),
      transformResponse: unwrap,
    }),
    evaluate: b.mutation<OrientationResult, string>({
      query: (id) => ({ url: `/assessments/${id}/evaluate`, method: 'POST' }),
      transformResponse: unwrap,
      invalidatesTags: ['Assessments'],
    }),
    results: b.query<OrientationResult, { id: string; locale: string }>({
      query: ({ id }) => `/assessments/${id}/results`,
      transformResponse: unwrap,
    }),
    outcome: b.query<Outcome, string>({
      query: (id) => `/assessments/${id}/outcome`,
      transformResponse: unwrap,
      providesTags: ['Assessments'],
    }),
    saveOutcome: b.mutation<Outcome, { id: string; body: OutcomeInput }>({
      query: ({ id, body }) => ({ url: `/assessments/${id}/outcome`, method: 'PUT', body }),
      transformResponse: unwrap,
      invalidatesTags: ['Assessments'],
    }),
  }),
});

export const {
  useCreateAssessmentMutation,
  useQuestionsQuery,
  useSubmitAnswersMutation,
  useEvaluateMutation,
  useResultsQuery,
  useOutcomeQuery,
  useSaveOutcomeMutation,
} = assessmentApi;
