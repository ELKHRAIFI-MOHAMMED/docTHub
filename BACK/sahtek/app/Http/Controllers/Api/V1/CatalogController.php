<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BodyPartResource;
use App\Http\Resources\DiseaseResource;
use App\Http\Resources\SpecialtyResource;
use App\Http\Resources\SymptomResource;
use App\Models\BodyPart;
use App\Models\Disease;
use App\Models\Specialty;
use App\Models\Symptom;
use Illuminate\Http\Request;

/** Public, read-only medical catalog. Only published records are exposed. */
class CatalogController extends ApiController
{
    public function bodyParts(Request $request)
    {
        $query = BodyPart::published()->withLocaleTranslations()->orderBy('sort_order')->orderBy('id');
        if ($view = $request->query('view')) {
            $query->whereIn('view', [$view, 'both']);
        }

        return BodyPartResource::collection($query->get());
    }

    public function specialties()
    {
        return SpecialtyResource::collection(
            Specialty::published()->withLocaleTranslations()->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function symptoms(Request $request)
    {
        $query = Symptom::published()->withLocaleTranslations()->orderBy('name');
        if ($q = $request->query('q')) {
            $query->where('name', 'like', '%'.addcslashes($q, '%_\\').'%');
        }

        return SymptomResource::collection($query->limit(200)->get());
    }

    public function diseases(Request $request)
    {
        $query = Disease::published()
            ->usableForOrientation()
            ->withLocaleTranslations()
            ->with(['specialty' => fn ($q) => $q->withLocaleTranslations()])
            ->orderBy('name');

        if ($specialty = $request->query('specialty_id')) {
            $query->where('specialty_id', (int) $specialty);
        }
        if ($bodyPart = $request->query('body_part_id')) {
            $query->whereHas('bodyParts', fn ($q) => $q->where('body_parts.id', (int) $bodyPart));
        }

        return DiseaseResource::collection($query->paginate(min((int) $request->query('per_page', 20), 100)));
    }
}
