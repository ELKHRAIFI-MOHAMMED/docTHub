import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';
import type { RootState } from './store';

/** Single source of truth for the API URL (env variable, never hardcoded). */
export const API_BASE_URL: string = import.meta.env.VITE_API_BASE_URL ?? '';

if (!API_BASE_URL && import.meta.env.MODE !== 'test') {
  // Fail loudly in development rather than silently calling the wrong host.
  console.error('VITE_API_BASE_URL is not set. Copy frontend/.env.example to frontend/.env.');
}

/**
 * Base RTK Query API. Feature slices inject their endpoints (features/<name>/api.ts).
 * Every request carries the bearer token (if any) and the UI locale, so the API answers in the right language.
 */
export const api = createApi({
  reducerPath: 'api',
  baseQuery: fetchBaseQuery({
    baseUrl: API_BASE_URL,
    prepareHeaders: (headers, { getState }) => {
      const state = getState() as RootState;
      headers.set('Accept', 'application/json');
      headers.set('X-Locale', state.preferences.locale);
      if (state.auth.token) headers.set('Authorization', `Bearer ${state.auth.token}`);
      return headers;
    },
  }),
  tagTypes: ['Me', 'Profile', 'Favorites', 'Assessments', 'Doctor', 'Admin'],
  endpoints: () => ({}),
});
