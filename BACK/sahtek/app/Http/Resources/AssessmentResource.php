<?php

namespace App\Http\Resources;

use App\Models\BodyPart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Assessment summary (history list and creation response). */
class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bodyParts = BodyPart::withTrashed()->withLocaleTranslations()->whereIn('id', $this->body_part_ids ?? [])->get();

        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'locale' => $this->locale,
            'body_parts' => BodyPartResource::collection($bodyParts),
            'has_emergency_alert' => $this->whenLoaded('alerts', fn () => $this->alerts->isNotEmpty()),
            'recommended_specialty' => $this->whenLoaded('results', function () {
                $top = $this->results->firstWhere('result_type', 'specialty');

                return $top?->specialty ? new SpecialtyResource($top->specialty) : null;
            }),
            'follow_up_collected' => $this->whenLoaded('outcome', fn () => (bool) $this->outcome?->collected_at),
            'created_at' => $this->created_at?->toIso8601String(),
            'evaluated_at' => $this->evaluated_at?->toIso8601String(),
        ];
    }
}
