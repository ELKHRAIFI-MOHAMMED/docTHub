import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, renderWithProviders } from '../../test/utils';
import ResultsPage from './ResultsPage';

const base = {
  id: 'abc', status: 'completed', evaluated_at: null, engine_version: '1.0.0',
  body_parts: [{ id: 3, code: 'chest', name: 'Chest', description: null, view: 'front', svg_region: 'chest' }],
  summary: [{ question: 'How strong is the pain?', answer: '5' }],
  disclaimer: 'Orientation only, not a diagnosis.', unreviewed_notice: null, uses_unreviewed_content: false,
  case_summary: { paragraphs: ['Areas concerned: Chest. Pain intensity: 5 out of 10.', 'No warning sign was detected in the answers.', 'Orientation only.'], text: '…', generated_by: 'templates' },
  follow_up: { collected: false, available: true },
};
const noOutcome = { data: { collected: false, consulted: null, consulted_specialty_id: null, diagnosis_disease_id: null, diagnosis_icd11: null, went_to_emergency: null, helpfulness: null, collected_at: null } };

describe('ResultsPage', () => {
  it('shows only the emergency instruction when an emergency rule matched', async () => {
    mockApi({
      'GET /assessments/abc/outcome': noOutcome,
      'GET /specialties': { data: [] },
      'GET /assessments/abc/results': {
        data: {
          ...base,
          emergency: { severity: 'critical', messages: ['Call your local emergency number now.'], emergency_number: '112', emergency_number_verified: false, country: 'France' },
          conditions: [], specialties: [], guidelines: [],
        },
      },
    });
    renderWithProviders(<ResultsPage />, { path: '/results/:id', route: '/results/abc' });

    expect(await screen.findByRole('alert')).toHaveTextContent('Call your local emergency number now.');
    expect(screen.getByRole('link', { name: /Call 112/ })).toHaveAttribute('href', 'tel:112');
    expect(screen.getByText(/not yet verified/)).toBeInTheDocument();
    expect(screen.queryByText('Possible explanations')).not.toBeInTheDocument();
    expect(screen.queryByText('Find doctors')).not.toBeInTheDocument();
  });

  it('shows possible conditions as non-diagnostic and links to doctors of the recommended specialty', async () => {
    mockApi({
      'GET /assessments/abc/outcome': noOutcome,
      'GET /specialties': { data: [] },
      'GET /assessments/abc/results': {
        data: {
          ...base,
          emergency: null,
          uses_unreviewed_content: true,
          unreviewed_notice: 'Some content has not been reviewed.',
          conditions: [{ rank: 1, match_strength: 'strong', disease: { id: 5, code: 'angina', name: 'Angina', description: 'Chest pain on effort.', severity: 'high', review_status: 'draft', is_sample: true, source_name: null } }],
          specialties: [{ id: 2, code: 'cardiology', name: 'Cardiology', description: null, icon: null }],
          guidelines: [{ id: 1, title: 'See a doctor', content: 'Soon.', guideline_type: 'when_to_consult', source_name: 'Sample', source_url: null, review_status: 'draft', reviewed_at: null, version: 1, is_sample: true }],
        },
      },
    });
    renderWithProviders(<ResultsPage />, { path: '/results/:id', route: '/results/abc' });

    expect(await screen.findByText('Angina')).toBeInTheDocument();
    expect(screen.getByText('These are possibilities to discuss with a doctor, not a diagnosis.')).toBeInTheDocument();
    expect(screen.getByText('Some content has not been reviewed.')).toBeInTheDocument();
    expect(screen.getByText('Not yet reviewed')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Find doctors' })).toHaveAttribute('href', '/doctors?specialty_id=2');
  });
});
