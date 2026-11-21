import { fireEvent, screen, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, renderWithProviders } from '../../test/utils';
import HistoryPage from '../../features/patient/HistoryPage';
import DoctorProfilePage from '../../features/doctor-discovery/DoctorProfilePage';

describe('loading, error and empty states', () => {
  it('shows a skeleton while loading, then a clear error with a working Retry button', async () => {
    let calls = 0;
    mockApi({
      'GET /patient/assessments': () => (++calls === 1
        ? { __status: 500, message: 'Server unavailable' }
        : { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 }, links: {} }),
    });
    renderWithProviders(<HistoryPage />);

    expect(screen.getByRole('status')).toHaveAttribute('aria-busy', 'true');
    expect(await screen.findByText('This content could not be loaded')).toBeInTheDocument();
    expect(screen.getByText('Server unavailable')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));
    // Empty state after a successful retry.
    expect(await screen.findByText('No assessments yet.')).toBeInTheDocument();
    await waitFor(() => expect(calls).toBe(2));
  });

  it('does not offer Retry when the item does not exist', async () => {
    mockApi({ 'GET /doctors/nobody': { __status: 404, message: 'Not found.' } });
    renderWithProviders(<DoctorProfilePage />, { path: '/doctors/:slug', route: '/doctors/nobody' });

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument();
  });
});
