import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import type { BodyPart } from '../../types/api';
import { BodyMap } from './BodyMap';

const parts: BodyPart[] = [
  { id: 1, code: 'head', name: 'Head', description: null, view: 'both', svg_region: 'head' },
  { id: 3, code: 'chest', name: 'Chest', description: null, view: 'front', svg_region: 'chest' },
  { id: 6, code: 'arm', name: 'Arm and shoulder', description: null, view: 'both', svg_region: 'arm' },
  { id: 9, code: 'lower-back', name: 'Lower back', description: null, view: 'back', svg_region: 'lower-back' },
];

function Harness() {
  const [selected, setSelected] = useState<number[]>([]);
  return <BodyMap bodyParts={parts} selected={selected} onToggle={(id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]))} />;
}

describe('BodyMap', () => {
  it('only makes regions with a published body part interactive', () => {
    const { container } = render(<Harness />);
    // Neck, abdomen, pelvis and legs are drawn but have no body part in this data set.
    expect(container.querySelectorAll('[data-region]')).toHaveLength(1 + 1 + 2); // head, chest, both arms
    expect(screen.getAllByRole('button', { name: 'Arm and shoulder' })).toHaveLength(2);
  });

  it('toggles selection on click and keeps both arm shapes in sync', async () => {
    render(<Harness />);
    const [leftArm, rightArm] = screen.getAllByRole('button', { name: 'Arm and shoulder' });
    await userEvent.click(leftArm);
    expect(leftArm).toHaveAttribute('aria-pressed', 'true');
    expect(rightArm).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('checkbox', { name: 'Arm and shoulder' })).toBeChecked();
    await userEvent.click(rightArm);
    expect(leftArm).toHaveAttribute('aria-pressed', 'false');
  });

  it('is keyboard accessible', () => {
    render(<Harness />);
    const chest = screen.getByRole('button', { name: 'Chest' });
    fireEvent.keyDown(chest, { key: 'Enter' });
    expect(chest).toHaveAttribute('aria-pressed', 'true');
    fireEvent.keyDown(chest, { key: ' ' });
    expect(chest).toHaveAttribute('aria-pressed', 'false');
  });

  it('switches to the back view', async () => {
    render(<Harness />);
    expect(screen.queryByRole('button', { name: 'Lower back' })).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Back' }));
    expect(screen.getByRole('button', { name: 'Lower back' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Chest' })).not.toBeInTheDocument();
  });

  it('offers an accessible list alternative', async () => {
    render(<Harness />);
    await userEvent.click(screen.getByRole('checkbox', { name: 'Lower back' }));
    expect(screen.getByRole('checkbox', { name: 'Lower back' })).toBeChecked();
  });
});
