import { api } from '../../app/api';

export type AdminRecord = Record<string, unknown> & { id: number };
export interface AdminPage {
  data: AdminRecord[];
  meta?: { current_page: number; last_page: number; total: number };
  // Laravel's raw paginator (patients / audit logs) puts these at the root.
  current_page?: number;
  last_page?: number;
  total?: number;
}

export interface ListArgs {
  resource: string;
  page?: number;
  q?: string;
  status?: string;
  extra?: Record<string, string>;
}

function qs(args: ListArgs) {
  const p = new URLSearchParams({ page: String(args.page ?? 1) });
  if (args.q) p.set('q', args.q);
  if (args.status) p.set('status', args.status);
  Object.entries(args.extra ?? {}).forEach(([k, v]) => v && p.set(k, v));
  return p.toString();
}

/** Generic admin endpoints: /admin/<resource> CRUD + status, plus doctor verification and patients. */
export const adminApi = api.injectEndpoints({
  endpoints: (b) => ({
    adminDashboard: b.query<Record<string, unknown>, void>({
      query: () => '/admin/dashboard',
      transformResponse: (r: { data: Record<string, unknown> }) => r.data,
      providesTags: ['Admin'],
    }),
    adminList: b.query<AdminPage, ListArgs>({
      query: (args) => `/admin/${args.resource}?${qs(args)}`,
      providesTags: ['Admin'],
    }),
    adminGet: b.query<AdminRecord, { resource: string; id: number }>({
      query: ({ resource, id }) => `/admin/${resource}/${id}`,
      transformResponse: (r: { data: AdminRecord }) => r.data,
      providesTags: ['Admin'],
    }),
    adminSave: b.mutation<AdminRecord, { resource: string; id?: number; body: Record<string, unknown> }>({
      query: ({ resource, id, body }) => ({ url: id ? `/admin/${resource}/${id}` : `/admin/${resource}`, method: id ? 'PUT' : 'POST', body }),
      transformResponse: (r: { data: AdminRecord }) => r.data,
      invalidatesTags: ['Admin'],
    }),
    adminStatus: b.mutation<AdminRecord, { resource: string; id: number; status: string }>({
      query: ({ resource, id, status }) => ({ url: `/admin/${resource}/${id}/status`, method: 'PATCH', body: { status } }),
      invalidatesTags: ['Admin'],
    }),
    adminDelete: b.mutation<void, { resource: string; id: number }>({
      query: ({ resource, id }) => ({ url: `/admin/${resource}/${id}`, method: 'DELETE' }),
      invalidatesTags: ['Admin'],
    }),
    verifyDoctor: b.mutation<AdminRecord, { id: number; verification_status: string }>({
      query: ({ id, verification_status }) => ({ url: `/admin/doctors/${id}/verification`, method: 'PATCH', body: { verification_status } }),
      invalidatesTags: ['Admin', 'Doctor'],
    }),
    uploadDoctorFile: b.mutation<unknown, { id: number; kind: 'photo' | 'documents'; form: FormData }>({
      query: ({ id, kind, form }) => ({ url: `/admin/doctors/${id}/${kind}`, method: 'POST', body: form }),
      invalidatesTags: ['Admin'],
    }),
    reviewDocument: b.mutation<unknown, { id: number; documentId: number; status: string }>({
      query: ({ id, documentId, status }) => ({ url: `/admin/doctors/${id}/documents/${documentId}`, method: 'PATCH', body: { status } }),
      invalidatesTags: ['Admin'],
    }),
    adminAction: b.mutation<{ meta?: Record<string, unknown> }, string>({
      query: (path) => ({ url: path, method: 'POST' }),
      invalidatesTags: ['Admin'],
    }),
    analytics: b.query<Record<string, unknown>, number>({
      query: (days) => `/admin/analytics?days=${days}`,
      transformResponse: (r: { data: Record<string, unknown> }) => r.data,
      providesTags: ['Admin'],
    }),
    setPatientActive: b.mutation<unknown, { id: number; is_active: boolean }>({
      query: ({ id, is_active }) => ({ url: `/admin/patients/${id}/active`, method: 'PATCH', body: { is_active } }),
      invalidatesTags: ['Admin'],
    }),
  }),
});

export const {
  useAdminDashboardQuery, useAdminListQuery, useAdminGetQuery, useAdminSaveMutation, useAdminStatusMutation,
  useAdminDeleteMutation, useVerifyDoctorMutation, useUploadDoctorFileMutation, useReviewDocumentMutation, useSetPatientActiveMutation,
  useAdminActionMutation, useAnalyticsQuery,
} = adminApi;
