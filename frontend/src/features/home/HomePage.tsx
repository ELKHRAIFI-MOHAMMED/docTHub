import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { useAppSelector } from '../../app/store';
import { Card } from '../../components/common/ui';
import { LocationLanguagePicker } from '../../components/layout/LocationLanguagePicker';
import { useSpecialtiesQuery } from '../catalog/catalogApi';
import { useSelectedLocation } from '../../hooks/useLocation';

export default function HomePage() {
  const { t } = useTranslation();
  const locale = useAppSelector((s) => s.preferences.locale);
  const { data: specialties = [] } = useSpecialtiesQuery(locale);
  const { country, city } = useSelectedLocation();

  const steps = [
    { icon: '🧍', title: t('home.step1Title'), text: t('home.step1Text') },
    { icon: '📝', title: t('home.step2Title'), text: t('home.step2Text') },
    { icon: '🩺', title: t('home.step3Title'), text: t('home.step3Text') },
  ];

  return (
    <div className="space-y-16">
      <section className="grid items-center gap-10 md:grid-cols-2">
        <div className="space-y-6">
          <p className="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-200">{t('home.badge')}</p>
          <h1 className="text-4xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl">{t('brand.slogan')}</h1>
          <p className="text-lg text-slate-600">{t('home.intro')}</p>
          <div className="flex flex-wrap gap-3">
            <Link to="/symptom-checker" className="rounded-xl bg-brand-600 px-6 py-3.5 text-base font-semibold text-white shadow-sm hover:bg-brand-700">{t('home.cta')}</Link>
            <Link to="/doctors" className="rounded-xl bg-white px-6 py-3.5 text-base font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">{t('nav.findDoctor')}</Link>
          </div>
          <div className="rounded-2xl bg-white p-4 ring-1 ring-slate-200">
            <p className="mb-2 text-sm font-medium text-slate-700">{t('home.whereAreYou')}</p>
            <LocationLanguagePicker />
            {country && <p className="mt-2 text-xs text-slate-500">{t('home.locationSelected', { place: [city?.name, country.name].filter(Boolean).join(', ') })}</p>}
          </div>
        </div>
        <img src="/img/medicine.png" alt="" className="mx-auto w-full max-w-md" />
      </section>

      <section aria-labelledby="how">
        <h2 id="how" className="text-2xl font-bold text-slate-900">{t('home.howItWorks')}</h2>
        <ol className="mt-6 grid gap-4 md:grid-cols-3">
          {steps.map((s, i) => (
            <li key={s.title}>
              <Card className="h-full">
                <div className="text-3xl" aria-hidden>{s.icon}</div>
                <p className="mt-3 text-sm font-semibold text-brand-700">{t('home.stepN', { n: i + 1 })}</p>
                <h3 className="font-semibold text-slate-900">{s.title}</h3>
                <p className="mt-1 text-sm text-slate-600">{s.text}</p>
              </Card>
            </li>
          ))}
        </ol>
      </section>

      <section aria-labelledby="specialties">
        <div className="flex items-end justify-between">
          <h2 id="specialties" className="text-2xl font-bold text-slate-900">{t('home.specialties')}</h2>
          <Link to="/doctors" className="text-sm font-medium text-brand-700 hover:underline">{t('home.seeAllDoctors')}</Link>
        </div>
        <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {specialties.slice(0, 8).map((s) => (
            <li key={s.id}>
              <Link to={`/doctors?specialty_id=${s.id}`} className="block rounded-xl bg-white px-4 py-3 text-sm font-medium text-slate-700 ring-1 ring-slate-200 hover:text-brand-700 hover:ring-brand-300">
                {s.name}
              </Link>
            </li>
          ))}
        </ul>
      </section>

      <section className="rounded-3xl bg-brand-700 px-6 py-10 text-white sm:px-10">
        <h2 className="text-2xl font-bold">{t('home.discoveryTitle')}</h2>
        <p className="mt-2 max-w-2xl text-brand-100">{t('home.discoveryText')}</p>
        <Link to="/doctors" className="mt-6 inline-block rounded-xl bg-white px-5 py-3 font-semibold text-brand-800 hover:bg-brand-50">{t('nav.findDoctor')}</Link>
      </section>
    </div>
  );
}
