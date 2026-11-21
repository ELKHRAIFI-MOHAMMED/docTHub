import { useTranslation } from 'react-i18next';

/**
 * Map of a doctor's practice. Uses the OpenStreetMap embed (no API key, only the doctor's public
 * coordinates are sent). Replace this component to switch to Google Maps / Mapbox.
 */
export function MapView({ lat, lng, label }: { lat: number; lng: number; label: string }) {
  const { t } = useTranslation();
  const d = 0.01;
  const bbox = [lng - d, lat - d, lng + d, lat + d].map((n) => n.toFixed(5)).join(',');
  const src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lng}`;

  return (
    <div className="space-y-2">
      <iframe title={t('doctor.mapOf', { label })} src={src} loading="lazy" className="h-64 w-full rounded-xl ring-1 ring-slate-200" referrerPolicy="no-referrer" />
      <a className="text-sm text-brand-700 hover:underline" href={`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=16/${lat}/${lng}`} target="_blank" rel="noreferrer noopener">
        {t('doctor.openMap')}
      </a>
    </div>
  );
}
