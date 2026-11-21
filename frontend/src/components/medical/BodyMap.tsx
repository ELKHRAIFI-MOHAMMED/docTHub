import { useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { BodyPart } from '../../types/api';
import { cx } from '../common/ui';

type View = 'front' | 'back';

/**
 * Drawing of a gender-neutral figure. Each shape carries a `region` code that is matched
 * against `body_parts.svg_region` from the API. Shapes with no matching published body part
 * are drawn but not interactive. Names and availability always come from the database.
 */
const SHAPES: Record<View, { region: string; key: string; d?: string; rect?: [number, number, number, number, number]; ellipse?: [number, number, number, number] }[]> = {
  front: [
    { region: 'head', key: 'head', ellipse: [100, 40, 26, 30] },
    { region: 'neck', key: 'neck', rect: [88, 68, 24, 20, 6] },
    { region: 'chest', key: 'chest', rect: [62, 88, 76, 68, 18] },
    { region: 'abdomen', key: 'abdomen', rect: [66, 156, 68, 54, 10] },
    { region: 'pelvis', key: 'pelvis', rect: [66, 210, 68, 42, 16] },
    { region: 'arm', key: 'arm-l', d: 'M60 96 q-14 4 -18 22 l-12 96 q-2 12 9 13 q9 1 11 -10 l14 -86 z' },
    { region: 'arm', key: 'arm-r', d: 'M140 96 q14 4 18 22 l12 96 q2 12 -9 13 q-9 1 -11 -10 l-14 -86 z' },
    { region: 'leg', key: 'leg-l', rect: [70, 254, 27, 172, 12] },
    { region: 'leg', key: 'leg-r', rect: [103, 254, 27, 172, 12] },
  ],
  back: [
    { region: 'head', key: 'head', ellipse: [100, 40, 26, 30] },
    { region: 'neck', key: 'neck', rect: [88, 68, 24, 20, 6] },
    { region: 'upper-back', key: 'upper-back', rect: [62, 88, 76, 66, 18] },
    { region: 'lower-back', key: 'lower-back', rect: [66, 154, 68, 56, 10] },
    { region: 'buttocks', key: 'buttocks', rect: [66, 210, 68, 42, 16] },
    { region: 'arm', key: 'arm-l', d: 'M60 96 q-14 4 -18 22 l-12 96 q-2 12 9 13 q9 1 11 -10 l14 -86 z' },
    { region: 'arm', key: 'arm-r', d: 'M140 96 q14 4 18 22 l12 96 q2 12 -9 13 q-9 1 -11 -10 l-14 -86 z' },
    { region: 'leg', key: 'leg-l', rect: [70, 254, 27, 172, 12] },
    { region: 'leg', key: 'leg-r', rect: [103, 254, 27, 172, 12] },
  ],
};

export interface BodyMapProps {
  bodyParts: BodyPart[];
  selected: number[];
  onToggle: (id: number) => void;
}

export function BodyMap({ bodyParts, selected, onToggle }: BodyMapProps) {
  const { t } = useTranslation();
  const [view, setView] = useState<View>('front');

  const partFor = (region: string) =>
    bodyParts.find((p) => p.svg_region === region && (p.view === view || p.view === 'both'));

  const onKey = (e: KeyboardEvent, id: number) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onToggle(id);
    }
  };

  return (
    <div className="grid gap-6 md:grid-cols-[minmax(0,260px)_1fr] md:items-start">
      <div className="mx-auto w-full max-w-[260px]">
        <div className="mb-3 flex rounded-xl bg-slate-100 p-1" role="group" aria-label={t('checker.view')}>
          {(['front', 'back'] as View[]).map((v) => (
            <button
              key={v}
              type="button"
              aria-pressed={view === v}
              onClick={() => setView(v)}
              className={cx('min-h-11 flex-1 rounded-lg py-1.5 text-sm font-medium', view === v ? 'bg-white text-brand-800 shadow-sm' : 'text-slate-600')}
            >
              {t(`checker.${v}`)}
            </button>
          ))}
        </div>
        <svg viewBox="0 0 200 440" className="w-full" role="group" aria-label={t('checker.bodyMapLabel', { view: t(`checker.${view}`) })}>
          {SHAPES[view].map((shape) => {
            const part = partFor(shape.region);
            const isSelected = part ? selected.includes(part.id) : false;
            const common = part
              ? {
                  className: 'body-region',
                  role: 'button',
                  tabIndex: 0,
                  'aria-pressed': isSelected,
                  'aria-label': part.name,
                  'data-region': shape.region,
                  onClick: () => onToggle(part.id),
                  onKeyDown: (e: KeyboardEvent) => onKey(e, part.id),
                }
              : { fill: '#f1f5f9', stroke: '#cbd5e1', strokeWidth: 1.5, 'aria-hidden': true };
            return (
              <g key={`${view}-${shape.key}`}>
                {part && <title>{part.name}</title>}
                {shape.ellipse && <ellipse cx={shape.ellipse[0]} cy={shape.ellipse[1]} rx={shape.ellipse[2]} ry={shape.ellipse[3]} {...common} />}
                {shape.rect && <rect x={shape.rect[0]} y={shape.rect[1]} width={shape.rect[2]} height={shape.rect[3]} rx={shape.rect[4]} {...common} />}
                {shape.d && <path d={shape.d} {...common} />}
              </g>
            );
          })}
        </svg>
      </div>

      {/* Accessible list alternative (also easier on small screens). */}
      <fieldset className="space-y-2">
        <legend className="mb-2 text-sm font-semibold text-slate-700">{t('checker.orChooseFromList')}</legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {bodyParts.map((p) => (
            <label key={p.id} className={cx('flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm ring-1', selected.includes(p.id) ? 'bg-brand-50 ring-brand-300 text-brand-900' : 'bg-white ring-slate-200')}>
              <input type="checkbox" className="h-4 w-4 accent-brand-600" checked={selected.includes(p.id)} onChange={() => onToggle(p.id)} />
              {p.name}
            </label>
          ))}
        </div>
      </fieldset>
    </div>
  );
}
