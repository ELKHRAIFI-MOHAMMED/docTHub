import { api } from '../../app/api';
import type { Doctor, Paginated } from '../../types/api';

export interface DoctorFilters {
  q?: string;
  specialty_id?: number;
  country_id?: number;
  city_id?: number;
  language?: string;
  max_fee?: number;
  lat?: number;
  lng?: number;
  radius_km?: number;
  sort?: 'relevance' | 'distance' | 'experience' | 'fee' | 'name';
  page?: number;
  per_page?: number;
  locale?: string; // cache key only
}

export type DoctorPage = Paginated<Doctor> & { meta: Paginated<Doctor>['meta'] & { distance_basis: 'coordinates' | 'none' } };

function toQuery(filters: DoctorFilters): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([k, v]) => {
    if (k !== 'locale' && v !== undefined && v !== null && v !== '') params.set(k, String(v));
  });
  return params.toString();
}

export const doctorsApi = api.injectEndpoints({
  endpoints: (b) => ({
    searchDoctors: b.query<DoctorPage, DoctorFilters>({
      query: (filters) => `/doctors?${toQuery(filters)}`,
      providesTags: ['Doctor', 'Favorites'],
    }),
    doctor: b.query<Doctor, { id: string; locale: string }>({
      query: ({ id }) => `/doctors/${encodeURIComponent(id)}`,
      transformResponse: (r: { data: Doctor }) => r.data,
      providesTags: ['Doctor', 'Favorites'],
    }),
    favorites: b.query<Paginated<Doctor>, { page?: number; locale: string }>({
      query: ({ page = 1 }) => `/patient/favorites?page=${page}`,
      providesTags: ['Favorites'],
    }),
    addFavorite: b.mutation<void, number>({
      query: (id) => ({ url: `/favorites/${id}`, method: 'POST' }),
      invalidatesTags: ['Favorites'],
    }),
    removeFavorite: b.mutation<void, number>({
      query: (id) => ({ url: `/favorites/${id}`, method: 'DELETE' }),
      invalidatesTags: ['Favorites'],
    }),
  }),
});

export const { useSearchDoctorsQuery, useDoctorQuery, useFavoritesQuery, useAddFavoriteMutation, useRemoveFavoriteMutation } = doctorsApi;
