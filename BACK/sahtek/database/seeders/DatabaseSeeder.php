<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment('production')) {
            $this->command?->warn('Production: only reference data seeded. Sample medical content and demo accounts are skipped.');

            return;
        }

        $this->call([
            MedicalCatalogSeeder::class,
            QuestionnaireSeeder::class,
            DemoAccountsSeeder::class,
        ]);
    }
}
