import { api } from '../../app/api';
import type { BodyPart, City, Country, Language, Specialty } from '../../types/api';

const unwrap = <T,>(r: { data: T }) => r.data;

/** Reference data. Nothing here is hardcoded in the UI: it all comes from the database. */
export const catalogApi = api.injectEndpoints({
  endpoints: (b) => ({
    languages: b.query<Language[], void>({ query: () => '/languages', transformResponse: unwrap }),
    countries: b.query<Country[], string | void>({ query: () => '/countries', transformResponse: unwrap }),
    cities: b.query<City[], { countryId: number; locale?: string }>({
      query: ({ countryId }) => `/cities?country_id=${countryId}`,
      transformResponse: unwrap,
    }),
    bodyParts: b.query<BodyPart[], string | void>({ query: () => '/body-parts', transformResponse: unwrap }),
    specialties: b.query<Specialty[], string | void>({ query: () => '/specialties', transformResponse: unwrap }),
  }),
});

export const { useLanguagesQuery, useCountriesQuery, useCitiesQuery, useBodyPartsQuery, useSpecialtiesQuery } = catalogApi;
