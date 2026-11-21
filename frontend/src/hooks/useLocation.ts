import { useAppSelector } from '../app/store';
import { useCitiesQuery, useCountriesQuery } from '../features/catalog/catalogApi';

/** Selected country/city (from preferences), resolved to full objects from the API. */
export function useSelectedLocation() {
  const { countryId, cityId, locale } = useAppSelector((s) => s.preferences);
  const { data: countries = [] } = useCountriesQuery(locale);
  const { data: cities = [] } = useCitiesQuery({ countryId: countryId ?? 0, locale }, { skip: !countryId });

  return {
    countryId,
    cityId,
    country: countries.find((c) => c.id === countryId) ?? null,
    city: cities.find((c) => c.id === cityId) ?? null,
    countries,
    cities,
  };
}
