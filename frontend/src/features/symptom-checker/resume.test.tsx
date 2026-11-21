import { fireEvent, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { mockApi, renderWithProviders } from '../../test/utils';
import { loggedOut } from '../auth/authSlice';
import { getInProgress, saveInProgress } from './inProgress';
import SymptomCheckerPage from './SymptomCheckerPage';

const bodyParts = { data: [{ id: 3, code: 'chest', name: 'Chest', description: null, view: 'front', svg_region: 'chest' }] };

describe('assessment in progress', () => {
  beforeEach(() => localStorage.clear());

  it('offers to resume the saved assessment and lets the user discard it', async () => {
    saveInProgress('uuid-123');
    mockApi({ 'GET /body-parts': bodyParts });
    const { router } = renderWithProviders(<SymptomCheckerPage />, {
      path: '/symptom-checker', route: '/symptom-checker',
      extraRoutes: [{ path: '/symptom-checker/:id', element: <p>questionnaire</p> }],
    });

    expect(await screen.findByText('You have an assessment in progress')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Resume' }));
    expect(router.state.location.pathname).toBe('/symptom-checker/uuid-123');
  });

  it('forgets it on discard, after 24 hours and on logout', async () => {
    saveInProgress('uuid-123');
    mockApi({ 'GET /body-parts': bodyParts });
    const { store } = renderWithProviders(<SymptomCheckerPage />, { path: '/symptom-checker', route: '/symptom-checker' });

    fireEvent.click(await screen.findByRole('button', { name: 'Start a new one' }));
    expect(screen.queryByText('You have an assessment in progress')).not.toBeInTheDocument();
    expect(getInProgress()).toBeNull();

    localStorage.setItem('docthub.assessmentInProgress', JSON.stringify({ id: 'old', savedAt: Date.now() - 25 * 3600 * 1000 }));
    expect(getInProgress()).toBeNull();

    saveInProgress('uuid-456');
    store.dispatch(loggedOut());
    expect(getInProgress()).toBeNull();
  });
});
