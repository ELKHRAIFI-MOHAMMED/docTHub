import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import type { OrientationResult } from '../../types/api';

type Emergency = NonNullable<OrientationResult['emergency']>;

function CallButton({ number, big, buttonRef }: { number: string; big?: boolean; buttonRef?: React.Ref<HTMLAnchorElement> }) {
  const { t } = useTranslation();
  return (
    <a
      ref={buttonRef}
      href={`tel:${number.replace(/[^\d+]/g, '')}`}
      className={big
        ? 'flex min-h-16 w-full items-center justify-center gap-3 rounded-2xl bg-white px-6 py-4 text-2xl font-extrabold text-red-800 shadow-lg hover:bg-red-50 focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-white sm:w-auto'
        : 'inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 py-2 font-bold text-red-800 hover:bg-red-50'}
    >
      <span aria-hidden>📞</span> {t('emergency.call', { number })}
    </a>
  );
}

/**
 * Emergency instruction. Full screen, high contrast, above everything else (portal, top z-index, scroll locked,
 * focus kept inside). Once acknowledged it stays pinned at the top of the page with the call button.
 */
export function EmergencyAlert({ emergency }: { emergency: Emergency }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(true);
  const titleId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);
  const callRef = useRef<HTMLAnchorElement>(null);
  const number = emergency.emergency_number;

  useEffect(() => {
    if (!open) return;
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    (callRef.current ?? dialogRef.current)?.focus();
    return () => { document.body.style.overflow = previous; };
  }, [open]);

  // Keep keyboard focus inside the emergency screen while it is open.
  function trapFocus(e: KeyboardEvent<HTMLDivElement>) {
    if (e.key !== 'Tab' || !dialogRef.current) return;
    const items = Array.from(dialogRef.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled])'));
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  // Fixed to the bottom of the screen, above everything (the site header stays usable at the top), with a spacer
  // so that it never hides the end of the page.
  const pinned = (
    <>
      <div aria-hidden className="h-32 sm:h-20" />
      <div role="alert" className="fixed inset-x-0 bottom-0 z-[900] bg-red-700 text-white shadow-[0_-4px_12px_rgba(0,0,0,0.25)]">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-3">
          <p className="font-bold">{t('emergency.title')}</p>
          <div className="flex flex-wrap gap-2">
            {number && <CallButton number={number} />}
            <button type="button" onClick={() => setOpen(true)} className="min-h-11 rounded-xl px-3 font-semibold text-white ring-2 ring-white/70 hover:bg-red-800">
              {t('emergency.showAgain')}
            </button>
          </div>
        </div>
      </div>
    </>
  );

  if (!open) return pinned;

  return createPortal(
    <div
      ref={dialogRef}
      role="alertdialog"
      aria-modal="true"
      aria-labelledby={titleId}
      tabIndex={-1}
      onKeyDown={trapFocus}
      className="fixed inset-0 z-[1000] overflow-y-auto bg-red-700 text-white"
    >
      <div className="mx-auto flex min-h-full max-w-2xl flex-col justify-center gap-6 px-5 py-10">
        <p className="text-sm font-bold uppercase tracking-wide text-red-50">
          {emergency.severity === 'critical' ? t('emergency.critical') : t('emergency.urgent')}
        </p>
        <h1 id={titleId} className="text-3xl font-extrabold leading-tight sm:text-4xl">{t('emergency.title')}</h1>
        <ul className="space-y-2 text-lg leading-relaxed">
          {emergency.messages.map((m) => <li key={m}>{m}</li>)}
        </ul>

        {number ? (
          <div className="space-y-2">
            <CallButton number={number} big buttonRef={callRef} />
            {!emergency.emergency_number_verified && (
              <p className="text-sm text-red-50">{t('emergency.unverifiedNumber', { country: emergency.country ?? '' })}</p>
            )}
          </div>
        ) : (
          <p className="rounded-xl bg-red-900/60 p-4 text-lg font-semibold">{t('emergency.noNumber')}</p>
        )}

        <p className="rounded-xl bg-red-900/50 p-4 text-sm text-red-50">{t('emergency.notDiagnosis')}</p>

        <button
          type="button"
          onClick={() => setOpen(false)}
          className="min-h-11 self-start rounded-xl px-4 py-2 font-semibold text-white ring-2 ring-white/70 hover:bg-red-800 focus-visible:outline-4 focus-visible:outline-white"
        >
          {t('emergency.acknowledge')}
        </button>
      </div>
    </div>,
    document.body,
  );
}
