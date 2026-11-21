import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useLocation } from 'react-router-dom';
import { Alert, PageLoader } from '../../components/common/ui';
import { useAppSelector } from '../../app/store';
import { useSession } from '../../hooks/useSession';

/** Route guard. UI-only convenience: the API enforces authorisation on every request. */
export function RequireRole({ role, children }: { role: 'patient' | 'admin'; children: ReactNode }) {
  const { t } = useTranslation();
  const location = useLocation();
  const token = useAppSelector((s) => s.auth.token);
  const { user, isLoading } = useSession();

  if (!token) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
  if (isLoading || !user) return <PageLoader />;
  if (!user.roles.includes(role)) return <Alert tone="danger">{t('errors.forbidden')}</Alert>;
  return <>{children}</>;
}
