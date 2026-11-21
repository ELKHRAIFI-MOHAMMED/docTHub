<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpecialtyController extends AdminCrudController
{
    protected array $searchable = ['name', 'code'];
    protected string $defaultSort = 'sort_order';

    protected function modelClass(): string
    {
        return Specialty::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => [$this->req($model), 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('specialties')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::in(Specialty::STATUSES)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
