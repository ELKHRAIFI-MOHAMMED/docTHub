import { api } from '../../app/api';
import type { AssessmentSummary, Paginated, Patient } from '../../types/api';

export type ProfileUpdate = Partial<{
  first_name: string; last_name: string; phone: string | null; date_of_birth: string | null; sex: string | null;
  country_id: number; city_id: number | null; preferred_language: string; share_location: boolean; email_notifications: boolean;
}>;

export const patientApi = api.injectEndpoints({
  endpoints: (b) => ({
    profile: b.query<Patient, string | void>({
      query: () => '/patient/profile',
      transformResponse: (r: { data: Patient }) => r.data,
      providesTags: ['Profile'],
    }),
    updateProfile: b.mutation<Patient, ProfileUpdate>({
      query: (body) => ({ url: '/patient/profile', method: 'PUT', body }),
      transformResponse: (r: { data: Patient }) => r.data,
      invalidatesTags: ['Profile', 'Me'],
    }),
    history: b.query<Paginated<AssessmentSummary>, { page: number; locale: string }>({
      query: ({ page }) => `/patient/assessments?page=${page}`,
      providesTags: ['Assessments'],
    }),
    exportData: b.mutation<unknown, void>({
      query: () => ({ url: '/patient/export', method: 'GET' }),
    }),
    deleteAccount: b.mutation<void, { password: string }>({
      query: (body) => ({ url: '/patient/account', method: 'DELETE', body }),
    }),
  }),
});

export const { useProfileQuery, useUpdateProfileMutation, useHistoryQuery, useExportDataMutation, useDeleteAccountMutation } = patientApi;
