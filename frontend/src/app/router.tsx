import { lazy, Suspense, type ReactNode } from 'react';
import { createBrowserRouter, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { EmptyState, PageLoader } from '../components/common/ui';
import { RequireRole } from '../features/auth/RequireRole';
import HomePage from '../features/home/HomePage';

// Route-level code splitting: admin and secondary pages are loaded on demand.
const LoginPage = lazy(() => import('../features/auth/LoginPage'));
const RegisterPage = lazy(() => import('../features/auth/RegisterPage'));
const SymptomCheckerPage = lazy(() => import('../features/symptom-checker/SymptomCheckerPage'));
const QuestionnairePage = lazy(() => import('../features/symptom-checker/QuestionnairePage'));
const ResultsPage = lazy(() => import('../features/orientation/ResultsPage'));
const DoctorsPage = lazy(() => import('../features/doctor-discovery/DoctorsPage'));
const DoctorProfilePage = lazy(() => import('../features/doctor-discovery/DoctorProfilePage'));
const FavoritesPage = lazy(() => import('../features/favorites/FavoritesPage'));
const HistoryPage = lazy(() => import('../features/patient/HistoryPage'));
const ProfilePage = lazy(() => import('../features/patient/ProfilePage'));
const AdminLayout = lazy(() => import('../features/admin/AdminLayout'));
const DashboardPage = lazy(() => import('../features/admin/DashboardPage'));
const ResourcePage = lazy(() => import('../features/admin/ResourcePage'));
const DoctorsAdminPage = lazy(() => import('../features/admin/DoctorsAdminPage'));
const PatientsAdminPage = lazy(() => import('../features/admin/PatientsAdminPage'));
const AnalyticsPage = lazy(() => import('../features/admin/AnalyticsPage'));

const s = (el: ReactNode) => <Suspense fallback={<PageLoader />}>{el}</Suspense>;

function NotFound() {
  const { t } = useTranslation();
  return <EmptyState icon="🧭" title={t('errors.notFound')}><Link to="/" className="text-brand-700 underline">{t('nav.home')}</Link></EmptyState>;
}

export const routes = [
  {
    element: <AppLayout />,
    children: [
      { path: '/', element: <HomePage /> },
      { path: '/login', element: s(<LoginPage />) },
      { path: '/register', element: s(<RegisterPage />) },
      { path: '/symptom-checker', element: s(<SymptomCheckerPage />) },
      { path: '/symptom-checker/:id', element: s(<QuestionnairePage />) },
      { path: '/results/:id', element: s(<ResultsPage />) },
      { path: '/doctors', element: s(<DoctorsPage />) },
      { path: '/doctors/:slug', element: s(<DoctorProfilePage />) },
      { path: '/favorites', element: s(<RequireRole role="patient"><FavoritesPage /></RequireRole>) },
      { path: '/history', element: s(<RequireRole role="patient"><HistoryPage /></RequireRole>) },
      { path: '/profile', element: s(<RequireRole role="patient"><ProfilePage /></RequireRole>) },
      {
        path: '/admin',
        element: s(<RequireRole role="admin"><AdminLayout /></RequireRole>),
        children: [
          { index: true, element: s(<DashboardPage />) },
          { path: 'doctors', element: s(<DoctorsAdminPage />) },
          { path: 'patients', element: s(<PatientsAdminPage />) },
          { path: 'analytics', element: s(<AnalyticsPage />) },
          { path: 'r/:resource', element: s(<ResourcePage />) },
        ],
      },
      { path: '*', element: <NotFound /> },
    ],
  },
];

export const router = createBrowserRouter(routes);
