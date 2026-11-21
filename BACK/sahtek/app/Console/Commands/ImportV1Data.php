<?php

namespace App\Console\Commands;

use App\Models\BodyPart;
use App\Models\City;
use App\Models\Country;
use App\Models\Disease;
use App\Models\Doctor;
use App\Models\MedicalGuideline;
use App\Models\Specialty;
use App\Models\Symptom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Copies V1 content (parties_corp, specialite, malade, symptome, consigne, medcin) into V2 tables.
 * Idempotent: V2 codes are "v1-<entity>-<id>". Everything imported is draft / pending review.
 */
class ImportV1Data extends Command
{
    protected $signature = 'docthub:import-v1 {--country=MA : ISO2 country used to match V1 doctor cities} {--dry-run}';

    protected $description = 'Import legacy docTHub V1 data into the V2 schema (as draft content needing review)';

    private const SOURCE = 'Imported from docTHub V1 — source unknown, requires medical review';

    public function handle(): int
    {
        foreach (['parties_corp', 'specialite', 'malade', 'symptome', 'consigne', 'medcin'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("V1 table `$table` not found.");

                return self::FAILURE;
            }
        }
        $country = Country::where('iso2', strtoupper($this->option('country')))->first();
        if (! $country) {
            $this->error('Unknown country '.$this->option('country').'. Seed reference data first.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        $counts = array_fill_keys(['body_parts', 'specialties', 'diseases', 'symptoms', 'guidelines', 'doctors'], 0);
        $unmatchedCities = [];

        $bodyParts = [];
        foreach (DB::table('parties_corp')->get() as $row) {
            $bodyParts[$row->id_parties_corps] = BodyPart::updateOrCreate(['code' => "v1-body-part-{$row->id_parties_corps}"], [
                'name' => $row->nom, 'description' => $row->description, 'view' => 'front', 'status' => 'draft',
            ])->id;
            $counts['body_parts']++;
        }

        $specialties = [];
        foreach (DB::table('specialite')->get() as $row) {
            $specialties[$row->id_specialite] = Specialty::updateOrCreate(['code' => "v1-specialty-{$row->id_specialite}"], [
                'name' => $row->nom, 'description' => $row->description, 'status' => 'draft',
            ])->id;
            $counts['specialties']++;
        }

        $diseases = [];
        foreach (DB::table('malade')->get() as $row) {
            $disease = Disease::updateOrCreate(['code' => "v1-disease-{$row->id_malade}"], [
                'name' => $row->nom, 'description' => $row->description,
                'specialty_id' => $specialties[$row->id_specialite] ?? null,
                'status' => 'draft', 'review_status' => 'draft', 'source_name' => self::SOURCE,
            ]);
            if (isset($bodyParts[$row->id_parties_corps])) {
                $disease->bodyParts()->syncWithoutDetaching([$bodyParts[$row->id_parties_corps] => ['weight' => 1]]);
            }
            $diseases[$row->id_malade] = $disease;
            $counts['diseases']++;
        }

        foreach (DB::table('symptome')->get() as $row) {
            $symptom = Symptom::updateOrCreate(['code' => "v1-symptom-{$row->id_symptome}"], [
                'name' => Str::limit($row->description, 147), 'description' => $row->description, 'status' => 'draft',
            ]);
            if (isset($diseases[$row->id_malade])) {
                $diseases[$row->id_malade]->symptoms()->syncWithoutDetaching([$symptom->id => ['weight' => 1]]);
            }
            $counts['symptoms']++;
        }

        foreach (DB::table('consigne')->get() as $row) {
            MedicalGuideline::updateOrCreate(['title' => "V1 advice #{$row->id_consigne}"], [
                'disease_id' => $diseases[$row->id_malade]->id ?? null, 'content' => $row->description,
                'guideline_type' => 'self_care', 'source_name' => self::SOURCE, 'review_status' => 'draft', 'status' => 'draft',
            ]);
            $counts['guidelines']++;
        }

        foreach (DB::table('medcin')->get() as $row) {
            $parts = preg_split('/\s+/', trim($row->nom_prenom), 2);
            $doctor = Doctor::updateOrCreate(['slug' => "v1-doctor-{$row->id_medcin}"], [
                'first_name' => $parts[1] ?? $parts[0], 'last_name' => $parts[0],
                'bio' => $row->bio, 'verification_status' => 'pending', 'status' => 'draft',
            ]);
            if (isset($specialties[$row->id_specialite])) {
                $doctor->specialties()->syncWithoutDetaching([$specialties[$row->id_specialite] => ['is_primary' => true]]);
            }
            $city = City::where('country_id', $country->id)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) ($row->ville ?? '')))])->first();
            if ($city) {
                $doctor->locations()->firstOrCreate(['city_id' => $city->id], ['country_id' => $country->id, 'is_primary' => true]);
            } else {
                $unmatchedCities[] = "{$row->nom_prenom} ({$row->ville})";
            }
            $counts['doctors']++;
        }

        $this->table(['Entity', 'Imported'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values());
        if ($unmatchedCities) {
            $this->warn('Doctors whose city was not found (add the city, then re-run or set the location in the admin):');
            foreach ($unmatchedCities as $line) {
                $this->line(" - $line");
            }
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->info('Dry run: nothing was saved.');
        } else {
            DB::commit();
            $this->info('Import complete. All imported content is DRAFT and must be reviewed before publication.');
        }

        return self::SUCCESS;
    }
}
