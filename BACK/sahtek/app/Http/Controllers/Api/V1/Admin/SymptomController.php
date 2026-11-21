<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Symptom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SymptomController extends AdminCrudController
{
    protected array $searchable = ['name', 'code'];

    protected function modelClass(): string
    {
        return Symptom::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => [$this->req($model), 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('symptoms')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(Symptom::STATUSES)],
        ];
    }
}
