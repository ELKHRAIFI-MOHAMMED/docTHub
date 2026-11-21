import { useTranslation } from 'react-i18next';
import type { OrientationResult } from '../../types/api';

/** Emergency instruction. Takes over the results page; nothing else is shown before it. */
export function EmergencyAlert({ emergency }: { emergency: NonNullable<OrientationResult['emergency']> }) {
  const { t } = useTranslation();
  const number = emergency.emergency_number;

  return (
    <section role="alert" aria-live="assertive" className="overflow-hidden rounded-2xl bg-red-600 text-white shadow-lg">
      <div className="p-6 sm:p-8">
        <p className="text-sm font-semibold uppercase tracking-wide text-red-100">
          {emergency.severity === 'critical' ? t('emergency.critical') : t('emergency.urgent')}
        </p>
        <h1 className="mt-2 text-2xl font-bold sm:text-3xl">{t('emergency.title')}</h1>
        <ul className="mt-4 space-y-2 text-base leading-relaxed">
          {emergency.messages.map((m) => <li key={m}>{m}</li>)}
        </ul>

        {number ? (
          <a href={`tel:${number.replace(/\s/g, '')}`} className="mt-6 inline-flex items-center gap-3 rounded-xl bg-white px-6 py-4 text-xl font-bold text-red-700 shadow hover:bg-red-50">
            <span aria-hidden>📞</span> {t('emergency.call', { number })}
          </a>
        ) : (
          <p className="mt-6 rounded-xl bg-red-700/60 p-4 font-semibold">{t('emergency.noNumber')}</p>
        )}

        {number && !emergency.emergency_number_verified && (
          <p className="mt-3 text-sm text-red-100">{t('emergency.unverifiedNumber', { country: emergency.country ?? '' })}</p>
        )}
      </div>
      <p className="bg-red-700 px-6 py-3 text-sm text-red-50 sm:px-8">{t('emergency.notDiagnosis')}</p>
    </section>
  );
}
