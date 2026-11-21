import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, prefs, renderWithProviders } from '../../test/utils';
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

    // Full-screen emergency screen above everything, focus on the call button.
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('Call your local emergency number now.');
    expect(dialog).toHaveAttribute('aria-modal', 'true');
    expect(dialog.className).toContain('fixed inset-0');
    const call = screen.getByRole('link', { name: /Call 112/ });
    expect(call).toHaveAttribute('href', 'tel:112');
    expect(call).toHaveFocus();
    expect(screen.getByText(/not yet verified/)).toBeInTheDocument();
    expect(screen.queryByText('Possible explanations')).not.toBeInTheDocument();
    expect(screen.queryByText('Find doctors')).not.toBeInTheDocument();

    // Once acknowledged, the instruction stays pinned at the top with the call button.
    fireEvent.click(screen.getByRole('button', { name: /I understand/ }));
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('Seek emergency care now');
    expect(screen.getByRole('link', { name: /Call 112/ })).toHaveAttribute('href', 'tel:112');
    expect(screen.getByText('This is an orientation, not a diagnosis.')).toBeInTheDocument();
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

  const approvedResult = {
    ...base, emergency: null,
    conditions: [{ rank: 1, match_strength: 'moderate', disease: { id: 5, code: 'x', name: 'Fixture condition', description: null, severity: null, review_status: 'approved', is_sample: false, source_name: 'Source A' } }],
    specialties: [],
    guidelines: [{ id: 1, title: 'Fixture advice', content: 'Text.', guideline_type: 'when_to_consult', source_name: 'Source B', source_url: 'https://example.org/b', review_status: 'approved', reviewed_at: null, version: 1, is_sample: false }],
  };

  it('always shows the not-a-diagnosis banner, lists sources in a collapsible section, and warns only on unreviewed items', async () => {
    mockApi({ 'GET /assessments/abc/outcome': noOutcome, 'GET /specialties': { data: [] }, 'GET /assessments/abc/results': { data: approvedResult } });
    renderWithProviders(<ResultsPage />, { path: '/results/:id', route: '/results/abc' });

    expect(await screen.findByText('This is an orientation, not a diagnosis.')).toBeInTheDocument();
    expect(screen.queryByText('Sample content', { exact: false })).not.toBeInTheDocument();
    const sources = screen.getByText('2 sources').closest('details')!;
    expect(sources).toHaveAttribute('open');
    expect(within(sources).getByRole('link', { name: 'Source B' })).toHaveAttribute('href', 'https://example.org/b');
    expect(sources).toHaveTextContent('Source A');
  });

  it('flags a single unreviewed item even when the content gate is in production mode', async () => {
    const result = { ...approvedResult, guidelines: [{ ...approvedResult.guidelines[0], review_status: 'in_review' }] };
    mockApi({ 'GET /assessments/abc/outcome': noOutcome, 'GET /specialties': { data: [] }, 'GET /assessments/abc/results': { data: result } });
    renderWithProviders(<ResultsPage />, { path: '/results/:id', route: '/results/abc' });

    expect(await screen.findByText('Some of the information shown has not yet been reviewed by a qualified professional.')).toBeInTheDocument();
  });

  it('renders right-to-left in Arabic', async () => {
    mockApi({ 'GET /assessments/abc/outcome': noOutcome, 'GET /specialties': { data: [] }, 'GET /assessments/abc/results': { data: approvedResult } });
    renderWithProviders(<ResultsPage />, { path: '/results/:id', route: '/results/abc', state: { preferences: prefs({ locale: 'ar' }) } });

    expect(await screen.findByText('هذا توجيه وليس تشخيصًا.')).toBeInTheDocument();
    expect(document.documentElement.dir).toBe('rtl');
    expect(document.documentElement.lang).toBe('ar');
  });
});
