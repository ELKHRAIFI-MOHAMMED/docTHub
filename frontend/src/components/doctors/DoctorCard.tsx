import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import type { Doctor } from '../../types/api';
import { Badge } from '../common/ui';
import { FavoriteButton } from './FavoriteButton';
import { formatFee } from '../../utils/format';

export function DoctorAvatar({ doctor, size = 'md' }: { doctor: Doctor; size?: 'md' | 'lg' }) {
  const cls = size === 'lg' ? 'h-24 w-24 text-2xl' : 'h-14 w-14 text-lg';
  if (doctor.photo_url) return <img src={doctor.photo_url} alt="" className={`${cls} rounded-2xl object-cover`} />;
  const initials = `${doctor.first_name[0] ?? ''}${doctor.last_name[0] ?? ''}`.toUpperCase();
  return <div aria-hidden className={`${cls} flex shrink-0 items-center justify-center rounded-2xl bg-brand-100 font-bold text-brand-800`}>{initials}</div>;
}

/** Verification badge, or a demo badge for fictional development profiles (never shown as verified). */
export function DoctorStatusBadge({ doctor }: { doctor: Doctor }) {
  const { t } = useTranslation();
  if (doctor.is_demo) return <Badge tone="warning">{t('doctor.demoProfile')}</Badge>;
  if (doctor.is_verified) return <Badge tone="success">✓ {t('doctor.verified')}</Badge>;
  return <Badge tone="neutral">{t('doctor.notVerified')}</Badge>;
}

export function DoctorCard({ doctor }: { doctor: Doctor }) {
  const { t, i18n } = useTranslation();
  const primary = doctor.specialties.find((s) => s.is_primary) ?? doctor.specialties[0];

  return (
    <article className="flex gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 transition hover:ring-brand-200 sm:p-5">
      <DoctorAvatar doctor={doctor} />
      <div className="min-w-0 flex-1 space-y-1.5">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h3 className="font-semibold text-slate-900">
              <Link to={`/doctors/${doctor.slug}`} className="hover:text-brand-700 focus:underline">{doctor.full_name}</Link>
            </h3>
            {primary && <p className="text-sm text-brand-700">{primary.name}</p>}
          </div>
          <div className="flex items-center gap-2">
            <DoctorStatusBadge doctor={doctor} />
            {doctor.is_favorite !== null && <FavoriteButton doctorId={doctor.id} isFavorite={Boolean(doctor.is_favorite)} />}
          </div>
        </div>
        <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
          {doctor.city && <span>📍 {doctor.city.name}{doctor.country ? `, ${doctor.country.name}` : ''}</span>}
          {doctor.distance_km !== null && <span>{t('doctor.distance', { km: doctor.distance_km.toLocaleString(i18n.language) })}</span>}
          {doctor.years_of_experience !== null && <span>{t('doctor.experience', { count: doctor.years_of_experience })}</span>}
          {doctor.consultation_fee !== null && <span>{formatFee(doctor.consultation_fee, doctor.fee_currency, i18n.language)}</span>}
        </p>
        {doctor.languages.length > 0 && (
          <p className="text-xs text-slate-500">{t('doctor.speaks')}: {doctor.languages.map((l) => l.native_name).join(', ')}</p>
        )}
      </div>
    </article>
  );
}
