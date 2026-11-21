<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\BodyPart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BodyPartController extends AdminCrudController
{
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['view'];
    protected string $defaultSort = 'sort_order';

    protected function modelClass(): string
    {
        return BodyPart::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => [$this->req($model), 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('body_parts')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'view' => [$this->req($model), Rule::in(BodyPart::VIEWS)],
            'svg_region' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', 'exists:body_parts,id', Rule::notIn([$model?->id])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(BodyPart::STATUSES)],
        ];
    }
}
