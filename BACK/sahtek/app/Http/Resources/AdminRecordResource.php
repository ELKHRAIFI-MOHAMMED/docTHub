<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Raw record for the admin UI: all fillable attributes, loaded relations and every translation. */
class AdminRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->attributesToArray();

        foreach ($this->resource->getRelations() as $name => $relation) {
            if ($name === 'translations') {
                continue;
            }
            $data[$name] = $relation?->toArray();
        }

        if (method_exists($this->resource, 'translationsByLocale')) {
            $data['translations'] = $this->resource->translationsByLocale();
        }

        return $data;
    }
}
