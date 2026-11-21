import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { mockApi, renderWithProviders } from '../../test/utils';
import { CaseSummary } from './CaseSummary';
import { OutcomeForm } from './OutcomeForm';

const emptyOutcome = { data: { collected: false, consulted: null, consulted_specialty_id: null, diagnosis_disease_id: null, diagnosis_icd11: null, went_to_emergency: null, helpfulness: null, collected_at: null } };
const angina = { id: 5, code: 'angina', name: 'Angina', description: null, severity: 'high' as const, review_status: 'draft', is_sample: true, source_name: null };

describe('Case summary', () => {
  it('renders every paragraph produced by the API', () => {
    renderWithProviders(<CaseSummary summary={{ paragraphs: ['Areas concerned: Chest.', 'Recommended specialty: Pulmonology.', 'Not a diagnosis.'], text: 'x', generated_by: 'templates' }} />);
    expect(screen.getByRole('heading', { name: 'Summary of your case' })).toBeInTheDocument();
    expect(screen.getByText('Areas concerned: Chest.')).toBeInTheDocument();
    expect(screen.getByText('Recommended specialty: Pulmonology.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Copy' })).toBeInTheDocument();
  });
});

describe('Follow-up form', () => {
  it('sends a structured outcome and thanks the patient', async () => {
    let sent: Record<string, unknown> | null = null;
    mockApi({
      'GET /assessments/abc/outcome': emptyOutcome,
      'GET /specialties': { data: [{ id: 2, code: 'cardiology', name: 'Cardiology' }] },
      'PUT /assessments/abc/outcome': async (_url: URL, req: Request) => {
        sent = await req.json();
        return { data: { ...emptyOutcome.data, collected: true, consulted: true } };
      },
    });
    renderWithProviders(<OutcomeForm assessmentId="abc" conditions={[angina]} emergency={false} />);

    const send = screen.getByRole('button', { name: 'Send my answer' });
    expect(send).toBeDisabled();

    await userEvent.click(screen.getAllByLabelText('Yes')[0]);
    await screen.findByRole('option', { name: 'Cardiology' });
    await userEvent.selectOptions(screen.getByLabelText('Which specialty?'), '2');
    await userEvent.selectOptions(screen.getByLabelText('What did the doctor conclude?'), '5');
    await userEvent.click(screen.getByLabelText('4'));
    await userEvent.click(send);

    await waitFor(() => expect(sent).not.toBeNull());
    expect(sent).toEqual({ consulted: true, consulted_specialty_id: 2, diagnosis_disease_id: 5, went_to_emergency: null, helpfulness: 4 });
    expect(await screen.findByText('Thank you, your answer has been recorded.')).toBeInTheDocument();
  });

  it('shows the thank-you state when an answer already exists', async () => {
    mockApi({
      'GET /assessments/abc/outcome': { data: { ...emptyOutcome.data, collected: true, consulted: false } },
      'GET /specialties': { data: [] },
    });
    renderWithProviders(<OutcomeForm assessmentId="abc" conditions={[]} emergency />);
    expect(await screen.findByText('Thank you, your answer has been recorded.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Change my answer' })).toBeInTheDocument();
  });
});
