import { useTranslation } from 'react-i18next';
import type { Question } from '../../types/api';
import { cx, Input } from '../../components/common/ui';

export interface DraftAnswer {
  option_codes?: string[];
  value?: string;
}

const NONE = 'none';

/** Renders one questionnaire question according to its type. Controlled component. */
export function QuestionField({ question, value, onChange }: { question: Question; value: DraftAnswer; onChange: (v: DraftAnswer) => void }) {
  const { t } = useTranslation();
  const name = `q-${question.id}`;
  const selected = value.option_codes ?? [];
  const choiceCls = (active: boolean) =>
    cx('flex cursor-pointer items-center gap-3 rounded-xl px-4 py-3 text-sm ring-1 transition', active ? 'bg-brand-50 text-brand-900 ring-brand-400' : 'bg-white ring-slate-200 hover:ring-brand-200');

  switch (question.type) {
    case 'single_choice':
      return (
        <div role="radiogroup" aria-labelledby={`${name}-label`} className="grid gap-2">
          {question.options.map((o) => (
            <label key={o.code} className={choiceCls(selected[0] === o.code)}>
              <input type="radio" name={name} className="h-4 w-4 accent-brand-600" checked={selected[0] === o.code} onChange={() => onChange({ option_codes: [o.code] })} />
              {o.label}
            </label>
          ))}
        </div>
      );

    case 'multi_choice': {
      // "None of these" is exclusive with the other options.
      const toggle = (code: string) => {
        if (code === NONE) return onChange({ option_codes: selected.includes(NONE) ? [] : [NONE] });
        const without = selected.filter((c) => c !== NONE);
        onChange({ option_codes: without.includes(code) ? without.filter((c) => c !== code) : [...without, code] });
      };
      return (
        <div role="group" aria-labelledby={`${name}-label`} className="grid gap-2">
          {question.options.map((o) => (
            <label key={o.code} className={choiceCls(selected.includes(o.code))}>
              <input type="checkbox" className="h-4 w-4 accent-brand-600" checked={selected.includes(o.code)} onChange={() => toggle(o.code)} />
              {o.label}
            </label>
          ))}
          <p className="text-xs text-slate-500">{t('checker.selectAll')}</p>
        </div>
      );
    }

    case 'boolean':
      return (
        <div role="radiogroup" aria-labelledby={`${name}-label`} className="grid grid-cols-2 gap-2">
          {(['yes', 'no'] as const).map((v) => (
            <label key={v} className={choiceCls(value.value === v)}>
              <input type="radio" name={name} className="h-4 w-4 accent-brand-600" checked={value.value === v} onChange={() => onChange({ value: v })} />
              {t(`common.${v}`)}
            </label>
          ))}
        </div>
      );

    case 'scale': {
      const min = question.min_value ?? 0;
      const max = question.max_value ?? 10;
      const values = Array.from({ length: max - min + 1 }, (_, i) => min + i);
      return (
        <div role="radiogroup" aria-labelledby={`${name}-label`} className="space-y-2">
          <div className="flex flex-wrap gap-1.5">
            {values.map((n) => {
              const active = value.value === String(n);
              const tone = n >= 8 ? 'bg-red-500' : n >= 5 ? 'bg-amber-500' : 'bg-brand-600';
              return (
                <label key={n} className={cx('flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl text-sm font-semibold ring-1', active ? `${tone} text-white ring-transparent` : 'bg-white text-slate-700 ring-slate-200 hover:ring-brand-300')}>
                  <input type="radio" name={name} className="sr-only" checked={active} onChange={() => onChange({ value: String(n) })} />
                  {n}
                </label>
              );
            })}
          </div>
          <div className="flex justify-between text-xs text-slate-500"><span>{t('checker.scaleMin')}</span><span>{t('checker.scaleMax')}</span></div>
        </div>
      );
    }

    default:
      return (
        <div className="flex max-w-xs items-center gap-2">
          <Input
            type="number"
            inputMode="numeric"
            aria-labelledby={`${name}-label`}
            min={question.min_value ?? undefined}
            max={question.max_value ?? undefined}
            value={value.value ?? ''}
            onChange={(e) => onChange({ value: e.target.value })}
          />
          {question.unit && <span className="text-sm text-slate-500">{question.unit}</span>}
        </div>
      );
  }
}

export function isAnswerFilled(question: Question, a: DraftAnswer): boolean {
  if (question.type === 'single_choice' || question.type === 'multi_choice') return (a.option_codes?.length ?? 0) > 0;
  return a.value !== undefined && a.value !== '';
}
