<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Language;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT ONLY demo accounts. Never run in production (see DatabaseSeeder).
 * Demo doctors are fictional: is_demo = true, last name "Demo-NN", and a bio stating it.
 * The UI shows a "Demo profile" badge instead of the verification badge for them.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@docthub.test'], [
            'name' => 'Demo Admin', 'password' => 'Admin12345', 'preferred_language' => 'en', 'is_active' => true,
        ]);
        $admin->assignRole(User::ROLE_ADMIN);

        $casablanca = City::where('name', 'Casablanca')->first();
        $patientUser = User::updateOrCreate(['email' => 'patient@docthub.test'], [
            'name' => 'Demo Patient', 'password' => 'Patient12345', 'preferred_language' => 'fr', 'is_active' => true,
        ]);
        $patientUser->assignRole(User::ROLE_PATIENT);
        $patient = Patient::updateOrCreate(['user_id' => $patientUser->id], [
            'first_name' => 'Demo', 'last_name' => 'Patient', 'date_of_birth' => '1990-05-14', 'sex' => 'undisclosed',
            'country_id' => $casablanca->country_id, 'city_id' => $casablanca->id,
        ]);
        $patient->preference()->updateOrCreate([], ['language_code' => 'fr']);
        foreach (PatientConsent::TYPES as $type) {
            $patient->consents()->firstOrCreate(['consent_type' => $type], ['version' => config('docthub.consent_version'), 'granted_at' => now()]);
        }

        $this->seedDoctors();
    }

    private function seedDoctors(): void
    {
        $specialties = Specialty::pluck('id', 'code');
        $languages = Language::pluck('id', 'code');
        $firstNames = ['Salma', 'Youssef', 'Nadia', 'Karim', 'Leila', 'Omar', 'Claire', 'Hugo', 'Inès', 'Thomas', 'Sara', 'Mehdi', 'Emma', 'Adam', 'Julie', 'Rachid', 'Lina', 'Paul'];

        // city => [currency, [min fee, max fee], languages]
        $cities = [
            'Casablanca' => ['MAD', [250, 500], ['ar', 'fr']],
            'Rabat' => ['MAD', [250, 450], ['ar', 'fr']],
            'Marrakech' => ['MAD', [200, 400], ['ar', 'fr', 'en']],
            'Agadir' => ['MAD', [200, 350], ['ar', 'fr']],
            'Paris' => ['EUR', [30, 80], ['fr', 'en']],
            'Lyon' => ['EUR', [30, 60], ['fr']],
            'Brussels' => ['EUR', [30, 70], ['fr', 'en']],
            'Montréal' => ['CAD', [0, 0], ['fr', 'en']],
        ];
        $specialtyCycle = ['general-practice', 'cardiology', 'neurology', 'gastroenterology', 'orthopedics', 'rheumatology', 'ent', 'urology', 'pulmonology', 'general-surgery'];

        mt_srand(42); // deterministic demo data
        $n = 1;
        foreach ($cities as $cityName => [$currency, [$minFee, $maxFee], $langs]) {
            $city = City::where('name', $cityName)->firstOrFail();
            foreach ($specialtyCycle as $i => $specialtyCode) {
                // Not every specialty in every city (to make filtering visible), except the two largest demo cities.
                if (($i + $n) % 3 === 0 && $specialtyCode !== 'general-practice' && ! in_array($cityName, ['Casablanca', 'Paris'], true)) {
                    $n++;
                    continue;
                }
                $num = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                $first = $firstNames[$n % count($firstNames)];
                $doctor = Doctor::updateOrCreate(['slug' => "demo-doctor-$num"], [
                    'title' => 'Dr.',
                    'first_name' => $first,
                    'last_name' => "Demo-$num",
                    'bio' => "Fictional demo profile for development. This is not a real doctor. Sample profile in ".str_replace('-', ' ', $specialtyCode)." based in $cityName.",
                    'qualifications' => 'Demo data — no real qualifications.',
                    'years_of_experience' => 3 + ($n * 7) % 28,
                    'email' => "demo-doctor-$num@docthub.test",
                    'phone' => null,
                    'consultation_fee' => $maxFee > 0 ? $minFee + (($n * 37) % max(1, $maxFee - $minFee)) : null,
                    'fee_currency' => $maxFee > 0 ? $currency : null,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'is_demo' => true,
                    'status' => 'published',
                ]);
                $doctor->syncTranslations(['fr' => ['bio' => "Profil de démonstration fictif pour le développement. Ce n'est pas un vrai médecin."], 'ar' => ['bio' => 'ملف تجريبي وهمي لأغراض التطوير. هذا ليس طبيبًا حقيقيًا.']]);
                $doctor->specialties()->sync([$specialties[$specialtyCode] => ['is_primary' => true]]);
                $doctor->languages()->sync(collect($langs)->map(fn ($l) => $languages[$l])->all());
                $doctor->locations()->delete();
                // Spread around the city centre (±~5 km), deterministic.
                $doctor->locations()->create([
                    'label' => 'Demo practice',
                    'address' => "$n Demo Street",
                    'country_id' => $city->country_id,
                    'city_id' => $city->id,
                    'latitude' => $city->latitude + (mt_rand(-450, 450) / 10000),
                    'longitude' => $city->longitude + (mt_rand(-450, 450) / 10000),
                    'is_primary' => true,
                ]);
                $n++;
            }
        }

        // One pending (unverified) demo doctor: must NOT appear in public results.
        $pending = Doctor::updateOrCreate(['slug' => 'demo-doctor-pending'], [
            'first_name' => 'Pending', 'last_name' => 'Demo-Unverified', 'bio' => 'Fictional demo profile awaiting verification.',
            'verification_status' => 'pending', 'is_demo' => true, 'status' => 'published',
        ]);
        $pending->specialties()->sync([$specialties['general-practice'] => ['is_primary' => true]]);
        $casablanca = City::where('name', 'Casablanca')->first();
        $pending->locations()->delete();
        $pending->locations()->create(['country_id' => $casablanca->country_id, 'city_id' => $casablanca->id, 'latitude' => $casablanca->latitude, 'longitude' => $casablanca->longitude]);
    }
}
