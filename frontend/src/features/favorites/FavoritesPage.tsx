import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { EmptyState, PageLoader, Pagination, ErrorState } from '../../components/common/ui';
import { DoctorCard } from '../../components/doctors/DoctorCard';
import { useFavoritesQuery } from '../doctor-discovery/doctorsApi';

export default function FavoritesPage() {
  const { t } = useTranslation();
  const locale = useAppSelector((s) => s.preferences.locale);
  const [page, setPage] = useState(1);
  const { data, isLoading, error, refetch } = useFavoritesQuery({ page, locale });

  return (
    <div className="space-y-6">
      <h1 className="text-3xl font-bold text-slate-900">{t('nav.favorites')}</h1>
      {isLoading ? <PageLoader /> : error ? <ErrorState error={error} onRetry={refetch} /> : !data?.data.length ? (
        <EmptyState icon="♡" title={t('favorites.empty')}><Link to="/doctors" className="text-brand-700 underline">{t('nav.findDoctor')}</Link></EmptyState>
      ) : (
        <>
          <div className="space-y-3">{data.data.map((d) => <DoctorCard key={d.id} doctor={d} />)}</div>
          <Pagination page={data.meta.current_page} lastPage={data.meta.last_page} onChange={setPage} />
        </>
      )}
    </div>
  );
}
