import { api } from '../../app/api';
import type { User } from '../../types/api';

export interface RegisterPayload {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
  date_of_birth?: string;
  sex?: string;
  country_id: number;
  city_id?: number | null;
  preferred_language: string;
  consents: { terms: boolean; privacy: boolean; health_data: boolean };
}

type TokenResponse = { data: { token: string; user: User } };

export const authApi = api.injectEndpoints({
  endpoints: (b) => ({
    login: b.mutation<TokenResponse, { email: string; password: string }>({
      query: (body) => ({ url: '/auth/login', method: 'POST', body }),
    }),
    register: b.mutation<TokenResponse, RegisterPayload>({
      query: (body) => ({ url: '/auth/register', method: 'POST', body }),
    }),
    me: b.query<User, void>({
      query: () => '/auth/me',
      transformResponse: (r: { data: User }) => r.data,
      providesTags: ['Me'],
    }),
    logout: b.mutation<void, void>({
      query: () => ({ url: '/auth/logout', method: 'POST' }),
    }),
  }),
});

export const { useLoginMutation, useRegisterMutation, useMeQuery, useLogoutMutation } = authApi;
