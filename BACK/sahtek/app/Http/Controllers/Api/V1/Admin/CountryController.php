<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryController extends AdminCrudController
{
    protected array $searchable = ['name', 'iso2'];
    protected ?string $statusColumn = null;
    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return Country::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'iso2' => [$this->req($model), 'string', 'size:2', 'alpha', Rule::unique('countries')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:120'],
            'phone_code' => ['nullable', 'string', 'max:8', 'regex:/^\+\d{1,4}$/'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'timezone' => ['nullable', 'timezone'],
            'default_language_code' => ['nullable', 'string', 'exists:languages,code'],
            'emergency_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +]+$/'],
            'emergency_number_verified' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
