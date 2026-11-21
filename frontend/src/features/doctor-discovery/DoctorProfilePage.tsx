import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Alert, Badge, Card, PageLoader, ErrorState } from '../../components/common/ui';
import { DoctorAvatar, DoctorStatusBadge } from '../../components/doctors/DoctorCard';
import { FavoriteButton } from '../../components/doctors/FavoriteButton';
import { MapView } from '../../components/doctors/MapView';
import { formatFee } from '../../utils/format';
import { useDoctorQuery } from './doctorsApi';

export default function DoctorProfilePage() {
  const { t, i18n } = useTranslation();
  const { slug = '' } = useParams();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: d, isLoading, error, refetch } = useDoctorQuery({ id: slug, locale });

  if (isLoading) return <PageLoader />;
  if (error || !d) return <ErrorState error={error} onRetry={refetch} fallback={t('errors.notFound')} />;

  const primary = d.locations?.find((l) => l.is_primary) ?? d.locations?.[0];

  return (
    <div className="space-y-6">
      <Link to="/doctors" className="text-sm text-brand-700 inline-flex min-h-11 items-center gap-1 hover:underline"><span aria-hidden className="inline-block rtl:rotate-180">←</span> {t('doctors.backToResults')}</Link>
      {d.is_demo && <Alert tone="warning" title={t('doctor.demoProfile')}>{t('doctor.demoExplanation')}</Alert>}

      <Card>
        <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
          <DoctorAvatar doctor={d} size="lg" />
          <div className="flex-1 space-y-2">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-bold text-slate-900">{d.full_name}</h1>
              <DoctorStatusBadge doctor={d} />
              {d.is_favorite !== null && <FavoriteButton doctorId={d.id} isFavorite={Boolean(d.is_favorite)} />}
            </div>
            <div className="flex flex-wrap gap-2">
              {d.specialties.map((s) => <Badge key={s.id} tone={s.is_primary ? 'info' : 'neutral'}>{s.name}</Badge>)}
            </div>
            <p className="flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
              {d.years_of_experience !== null && <span>{t('doctor.experience', { count: d.years_of_experience })}</span>}
              {d.languages.length > 0 && <span>{t('doctor.speaks')}: {d.languages.map((l) => l.native_name).join(', ')}</span>}
              {d.consultation_fee !== null && <span>{t('doctor.fee')}: {formatFee(d.consultation_fee, d.fee_currency, i18n.language)}</span>}
            </p>
          </div>
        </div>
      </Card>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {d.bio && (
            <Card>
              <h2 className="text-lg font-semibold">{t('doctor.about')}</h2>
              <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{d.bio}</p>
            </Card>
          )}
          {d.qualifications && (
            <Card>
              <h2 className="text-lg font-semibold">{t('doctor.qualifications')}</h2>
              <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{d.qualifications}</p>
            </Card>
          )}
          {primary && (
            <Card>
              <h2 className="text-lg font-semibold">{t('doctor.location')}</h2>
              <address className="mt-2 text-sm not-italic text-slate-700">
                {primary.label && <span className="block font-medium">{primary.label}</span>}
                {primary.address && <span className="block">{primary.address}</span>}
                <span className="block">{[primary.postal_code, primary.city?.name, primary.country?.name].filter(Boolean).join(' ')}</span>
              </address>
              {primary.latitude !== null && primary.longitude !== null && (
                <div className="mt-4"><MapView lat={primary.latitude} lng={primary.longitude} label={d.full_name} /></div>
              )}
            </Card>
          )}
        </div>
        <aside>
          <Card className="space-y-3">
            <h2 className="text-lg font-semibold">{t('doctor.contact')}</h2>
            {d.phone && <a href={`tel:${d.phone}`} className="block rounded-xl bg-brand-600 px-4 py-3 text-center font-semibold text-white hover:bg-brand-700">📞 {d.phone}</a>}
            {d.email && <a href={`mailto:${d.email}`} className="block rounded-xl bg-white px-4 py-3 text-center font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">✉️ {d.email}</a>}
            {d.website && <a href={d.website} target="_blank" rel="noreferrer noopener" className="block text-center text-sm text-brand-700 hover:underline">{t('doctor.website')}</a>}
            {!d.phone && !d.email && !d.website && <p className="text-sm text-slate-500">{t('doctor.noContact')}</p>}
            <p className="text-xs text-slate-500">{t('doctor.appointmentNote')}</p>
          </Card>
        </aside>
      </div>
    </div>
  );
}
