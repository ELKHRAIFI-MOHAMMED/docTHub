import { forwardRef, useId, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { useTranslation } from 'react-i18next';
import { errorMessage, errorStatus } from '../../utils/errors';

const cx = (...c: (string | false | null | undefined)[]) => c.filter(Boolean).join(' ');

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
const variants: Record<Variant, string> = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700 disabled:bg-brand-200',
  secondary: 'bg-white text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50 disabled:text-slate-500',
  ghost: 'text-slate-600 hover:bg-slate-100',
  danger: 'bg-red-600 text-white hover:bg-red-700 disabled:bg-red-300',
};

export function Button({ variant = 'primary', loading, className, children, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; loading?: boolean }) {
  return (
    <button
      {...props}
      disabled={props.disabled || loading}
      className={cx('inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed', variants[variant], className)}
    >
      {loading && <Spinner small />}
      {children}
    </button>
  );
}

export function Card({ className, children, as: Tag = 'section' }: { className?: string; children: ReactNode; as?: 'section' | 'div' | 'article' }) {
  return <Tag className={cx('rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70 sm:p-6', className)}>{children}</Tag>;
}

export function Spinner({ small, label }: { small?: boolean; label?: string }) {
  const { t } = useTranslation();
  return (
    <span role="status" className="inline-flex items-center gap-2 text-slate-500">
      <span className={cx('animate-spin rounded-full border-2 border-current border-t-transparent', small ? 'h-4 w-4' : 'h-6 w-6')} aria-hidden />
      <span className={small ? 'sr-only' : 'text-sm'}>{label ?? t('common.loading')}</span>
    </span>
  );
}

/** Placeholder block shown while content loads. */
export function Skeleton({ className }: { className?: string }) {
  return <div aria-hidden className={cx('animate-pulse rounded-lg bg-slate-200', className)} />;
}

/** Loading state of a page or section: skeleton lines shaped like the content, announced to screen readers. */
export function PageLoader({ rows = 3 }: { rows?: number }) {
  const { t } = useTranslation();
  return (
    <div role="status" aria-busy="true" className="space-y-4 py-4">
      <span className="sr-only">{t('common.loading')}</span>
      <Skeleton className="h-7 w-1/2" />
      {Array.from({ length: rows }, (_, i) => (
        <div key={i} className="space-y-2 rounded-2xl bg-white p-4 ring-1 ring-slate-200/70">
          <Skeleton className="h-4 w-2/3" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-5/6" />
        </div>
      ))}
    </div>
  );
}

/** Error state of a query: clear message and a Retry button (not for 404: retrying cannot help). */
export function ErrorState({ error, onRetry, fallback, children }: { error: unknown; onRetry?: () => void; fallback?: string; children?: ReactNode }) {
  const { t } = useTranslation();
  const notFound = errorStatus(error) === 404;
  return (
    <div role="alert" className="rounded-2xl bg-red-50 p-5 text-red-900 ring-1 ring-red-200">
      <p className="font-semibold">{notFound ? t('errors.notFound') : t('errors.loadFailed')}</p>
      <p className="mt-1 text-sm">{errorMessage(error, fallback ?? t('errors.network'))}</p>
      {children}
      {onRetry && !notFound && <Button variant="secondary" className="mt-3" onClick={onRetry}>{t('common.retry')}</Button>}
    </div>
  );
}

export function EmptyState({ title, children, icon = '🔍' }: { title: string; children?: ReactNode; icon?: string }) {
  return (
    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
      <div className="text-3xl" aria-hidden>{icon}</div>
      <p className="mt-3 font-semibold text-slate-700">{title}</p>
      {children && <div className="mt-2 text-sm text-slate-500">{children}</div>}
    </div>
  );
}

type Tone = 'info' | 'warning' | 'danger' | 'success';
const tones: Record<Tone, string> = {
  info: 'bg-brand-50 text-brand-900 ring-brand-200',
  warning: 'bg-amber-50 text-amber-900 ring-amber-200',
  danger: 'bg-red-50 text-red-900 ring-red-200',
  success: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
};
export function Alert({ tone = 'info', title, children, className }: { tone?: Tone; title?: string; children?: ReactNode; className?: string }) {
  return (
    <div role={tone === 'danger' ? 'alert' : 'note'} className={cx('rounded-xl p-4 text-sm ring-1', tones[tone], className)}>
      {title && <p className="font-semibold">{title}</p>}
      {children && <div className={title ? 'mt-1' : ''}>{children}</div>}
    </div>
  );
}

export function Badge({ tone = 'info', children }: { tone?: Tone | 'neutral'; children: ReactNode }) {
  const map = { ...tones, neutral: 'bg-slate-100 text-slate-700 ring-slate-200' };
  return <span className={cx('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1', map[tone])}>{children}</span>;
}

interface FieldProps { label: string; error?: string; hint?: string; children: (id: string, describedBy?: string) => ReactNode; required?: boolean }
export function Field({ label, error, hint, children, required }: FieldProps) {
  const id = useId();
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined;
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {label}{required && <span className="text-red-600" aria-hidden> *</span>}
      </label>
      {children(id, describedBy)}
      {hint && !error && <p id={`${id}-hint`} className="text-xs text-slate-500">{hint}</p>}
      {error && <p id={`${id}-error`} className="text-xs font-medium text-red-600">{error}</p>}
    </div>
  );
}

const inputBase = 'block min-h-11 w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-500 focus:ring-2 focus:ring-brand-500 aria-[invalid=true]:ring-red-400';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...p }, ref) {
  return <input ref={ref} {...p} className={cx(inputBase, className)} />;
});
export function Select({ className, ...p }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...p} className={cx(inputBase, 'pe-8', className)} />;
}
export function Textarea({ className, ...p }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...p} className={cx(inputBase, 'min-h-24', className)} />;
}

export function Pagination({ page, lastPage, onChange }: { page: number; lastPage: number; onChange: (p: number) => void }) {
  const { t } = useTranslation();
  if (lastPage <= 1) return null;
  return (
    <nav className="flex items-center justify-center gap-3 pt-4" aria-label={t('common.pagination')}>
      <Button variant="secondary" disabled={page <= 1} onClick={() => onChange(page - 1)}>{t('common.previous')}</Button>
      <span className="text-sm text-slate-600">{t('common.pageOf', { page, total: lastPage })}</span>
      <Button variant="secondary" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>{t('common.next')}</Button>
    </nav>
  );
}

export { cx };
