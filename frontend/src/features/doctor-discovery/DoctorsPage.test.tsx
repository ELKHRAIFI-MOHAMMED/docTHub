import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, prefs, renderWithProviders } from '../../test/utils';
import DoctorsPage from './DoctorsPage';

const doctor = {
  id: 7, slug: 'demo-doctor-07', title: 'Dr.', first_name: 'Hugo', last_name: 'Demo-07', full_name: 'Dr. Hugo Demo-07',
  photo_url: null, years_of_experience: 12, consultation_fee: 300, fee_currency: 'MAD', is_verified: true,
  verification_status: 'verified', is_demo: true, specialties: [{ id: 2, code: 'cardiology', name: 'Cardiology', description: null, icon: null, is_primary: true }],
  languages: [], city: { id: 1, country_id: 1, name: 'Casablanca', latitude: 33.5, longitude: -7.5 }, country: null,
  distance_km: null, is_favorite: null, bio_excerpt: '',
};

const common = {
  'GET /countries': { data: [{ id: 1, iso2: 'MA', name: 'Morocco', currency_code: 'MAD' }] },
  'GET /cities': { data: [{ id: 1, country_id: 1, name: 'Casablanca', latitude: 33.5, longitude: -7.5 }] },
  'GET /specialties': { data: [{ id: 2, code: 'cardiology', name: 'Cardiology' }] },
  'GET /languages': { data: [{ id: 1, code: 'en', name: 'English', native_name: 'English', direction: 'ltr' }] },
};

describe('DoctorsPage', () => {
  it('asks for a location instead of listing every doctor', async () => {
    const fetch = mockApi(common);
    renderWithProviders(<DoctorsPage />, { path: '/doctors', route: '/doctors' });

    expect(await screen.findByText('Choose your country or city to see doctors near you')).toBeInTheDocument();
    const calledDoctors = fetch.mock.calls.some(([req]) => new URL((req as Request).url).pathname.endsWith('/doctors'));
    expect(calledDoctors).toBe(false);
  });

  it('searches in the selected city with the specialty from the URL, without inventing a distance', async () => {
    let lastQuery: URLSearchParams | null = null;
    mockApi({
      ...common,
      'GET /doctors': (url: URL) => {
        lastQuery = url.searchParams;
        return { data: [doctor], meta: { current_page: 1, last_page: 1, per_page: 12, total: 1, distance_basis: 'none' } };
      },
    });
    renderWithProviders(<DoctorsPage />, { path: '/doctors', route: '/doctors?specialty_id=2', state: { preferences: prefs({ countryId: 1, cityId: 1 }) } });

    expect(await screen.findByText('Dr. Hugo Demo-07')).toBeInTheDocument();
    await waitFor(() => expect(lastQuery).not.toBeNull());
    expect(lastQuery!.get('city_id')).toBe('1');
    expect(lastQuery!.get('specialty_id')).toBe('2');
    expect(lastQuery!.has('lat')).toBe(false);
    expect(screen.getByText(/distances are shown only when you share your location/)).toBeInTheDocument();
    expect(screen.queryByText(/km away/)).not.toBeInTheDocument();
    // Fictional profiles are never presented as verified.
    expect(screen.getByText('Demo profile (fictional)')).toBeInTheDocument();
    expect(screen.queryByText(/✓ Verified/)).not.toBeInTheDocument();
  });
});
