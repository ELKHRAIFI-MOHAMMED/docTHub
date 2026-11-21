<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BodyPartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->translate('name'),
            'description' => $this->translate('description'),
            'view' => $this->view,
            'svg_region' => $this->svg_region,
            'parent_id' => $this->parent_id,
        ];
    }
}
