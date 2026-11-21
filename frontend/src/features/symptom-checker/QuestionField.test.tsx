import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import type { Question } from '../../types/api';
import { isAnswerFilled, QuestionField, type DraftAnswer } from './QuestionField';

const base: Omit<Question, 'type' | 'options'> = {
  id: 1, code: 'q', body_part_id: null, text: 'Q?', help_text: null, min_value: null, max_value: null, unit: null, is_required: true, answer: null,
};

function Harness({ question, onValue }: { question: Question; onValue?: (v: DraftAnswer) => void }) {
  const [v, setV] = useState<DraftAnswer>({});
  return <QuestionField question={question} value={v} onChange={(x) => { setV(x); onValue?.(x); }} />;
}

describe('QuestionField', () => {
  it('makes "none" exclusive in multiple choice', async () => {
    const q: Question = { ...base, type: 'multi_choice', options: [{ code: 'fever', label: 'Fever' }, { code: 'cough', label: 'Cough' }, { code: 'none', label: 'None of these' }] };
    let last: DraftAnswer = {};
    render(<Harness question={q} onValue={(v) => (last = v)} />);

    await userEvent.click(screen.getByLabelText('Fever'));
    await userEvent.click(screen.getByLabelText('Cough'));
    expect(last.option_codes).toEqual(['fever', 'cough']);

    await userEvent.click(screen.getByLabelText('None of these'));
    expect(last.option_codes).toEqual(['none']);
    expect(screen.getByLabelText('Fever')).not.toBeChecked();

    await userEvent.click(screen.getByLabelText('Fever'));
    expect(last.option_codes).toEqual(['fever']);
  });

  it('renders a 0–10 scale as a radio group', async () => {
    const q: Question = { ...base, type: 'scale', options: [], min_value: 0, max_value: 10 };
    let last: DraftAnswer = {};
    render(<Harness question={q} onValue={(v) => (last = v)} />);
    expect(screen.getAllByRole('radio')).toHaveLength(11);
    await userEvent.click(screen.getByLabelText('7'));
    expect(last).toEqual({ value: '7' });
  });

  it('boolean questions answer yes / no', async () => {
    const q: Question = { ...base, type: 'boolean', options: [] };
    let last: DraftAnswer = {};
    render(<Harness question={q} onValue={(v) => (last = v)} />);
    await userEvent.click(screen.getByLabelText('No'));
    expect(last).toEqual({ value: 'no' });
  });

  it('knows when an answer is filled', () => {
    expect(isAnswerFilled({ ...base, type: 'single_choice', options: [] }, {})).toBe(false);
    expect(isAnswerFilled({ ...base, type: 'single_choice', options: [] }, { option_codes: ['a'] })).toBe(true);
    expect(isAnswerFilled({ ...base, type: 'number', options: [] }, { value: '' })).toBe(false);
    expect(isAnswerFilled({ ...base, type: 'number', options: [] }, { value: '0' })).toBe(true);
  });
});
