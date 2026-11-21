import { useTranslation } from 'react-i18next';
import { useAddFavoriteMutation, useRemoveFavoriteMutation } from '../../features/doctor-discovery/doctorsApi';

export function FavoriteButton({ doctorId, isFavorite }: { doctorId: number; isFavorite: boolean }) {
  const { t } = useTranslation();
  const [add, { isLoading: adding }] = useAddFavoriteMutation();
  const [remove, { isLoading: removing }] = useRemoveFavoriteMutation();
  const busy = adding || removing;

  return (
    <button
      type="button"
      disabled={busy}
      aria-pressed={isFavorite}
      onClick={() => (isFavorite ? remove(doctorId) : add(doctorId))}
      className="rounded-full p-1.5 text-xl leading-none hover:bg-rose-50 disabled:opacity-50"
      title={isFavorite ? t('doctor.removeFavorite') : t('doctor.addFavorite')}
    >
      <span aria-hidden className={isFavorite ? 'text-rose-600' : 'text-slate-500'}>♥</span>
      <span className="sr-only">{isFavorite ? t('doctor.removeFavorite') : t('doctor.addFavorite')}</span>
    </button>
  );
}
