<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\Role;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Languages, countries, cities, roles and settings.
 * Emergency numbers are seeded with emergency_number_verified = false: an administrator must
 * confirm each one against an official national source before production.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl'],
        ] as $lang) {
            Language::updateOrCreate(['code' => $lang['code']], $lang + ['is_active' => true]);
        }

        foreach ([
            ['name' => 'patient', 'label' => 'Patient'],
            ['name' => 'admin', 'label' => 'Administrator'],
        ] as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }

        $countries = [
            'MA' => ['Morocco', '+212', 'MAD', 'Africa/Casablanca', 'ar', '15', ['fr' => 'Maroc', 'ar' => 'المغرب'], [
                ['Casablanca', 33.5731, -7.5898, 'الدار البيضاء'], ['Rabat', 34.0209, -6.8416, 'الرباط'],
                ['Marrakech', 31.6295, -7.9811, 'مراكش'], ['Agadir', 30.4278, -9.5981, 'أكادير'],
                ['Fès', 34.0181, -5.0078, 'فاس'], ['Tanger', 35.7595, -5.8340, 'طنجة'],
            ]],
            'FR' => ['France', '+33', 'EUR', 'Europe/Paris', 'fr', '112', ['fr' => 'France', 'ar' => 'فرنسا'], [
                ['Paris', 48.8566, 2.3522, 'باريس'], ['Lyon', 45.7640, 4.8357, 'ليون'], ['Marseille', 43.2965, 5.3698, 'مرسيليا'],
            ]],
            'BE' => ['Belgium', '+32', 'EUR', 'Europe/Brussels', 'fr', '112', ['fr' => 'Belgique', 'ar' => 'بلجيكا'], [
                ['Brussels', 50.8503, 4.3517, 'بروكسل'],
            ]],
            'CA' => ['Canada', '+1', 'CAD', 'America/Toronto', 'en', '911', ['fr' => 'Canada', 'ar' => 'كندا'], [
                ['Montréal', 45.5019, -73.5674, 'مونتريال'], ['Toronto', 43.6532, -79.3832, 'تورونتو'],
            ]],
            'GB' => ['United Kingdom', '+44', 'GBP', 'Europe/London', 'en', '999', ['fr' => 'Royaume-Uni', 'ar' => 'المملكة المتحدة'], [
                ['London', 51.5074, -0.1278, 'لندن'],
            ]],
        ];

        foreach ($countries as $iso => [$name, $phone, $currency, $tz, $lang, $emergency, $tr, $cities]) {
            $country = Country::updateOrCreate(['iso2' => $iso], [
                'name' => $name, 'phone_code' => $phone, 'currency_code' => $currency, 'timezone' => $tz,
                'default_language_code' => $lang, 'emergency_number' => $emergency, 'is_active' => true,
            ]);
            $country->syncTranslations(['fr' => ['name' => $tr['fr']], 'ar' => ['name' => $tr['ar']]]);

            foreach ($cities as [$cityName, $lat, $lng, $arName]) {
                $city = City::updateOrCreate(
                    ['country_id' => $country->id, 'name' => $cityName],
                    ['latitude' => $lat, 'longitude' => $lng, 'timezone' => $tz, 'is_active' => true]
                );
                $city->syncTranslations(['ar' => ['name' => $arName]]);
            }
        }

        SystemSetting::updateOrCreate(['key' => 'orientation.fallback_specialty_code'], [
            'value' => 'general-practice',
            'description' => 'Specialty recommended when no rule or condition matches.',
        ]);
    }
}
