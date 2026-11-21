<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\City;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CityController extends AdminCrudController
{
    protected array $with = ['country:id,iso2,name'];
    protected array $filterable = ['country_id'];
    protected ?string $statusColumn = null;
    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return City::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $countryId = $request->input('country_id', $model?->country_id);

        return [
            'country_id' => [$this->req($model), 'integer', 'exists:countries,id'],
            'name' => [$this->req($model), 'string', 'max:120', Rule::unique('cities')->where('country_id', $countryId)->ignore($model)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'timezone' => ['nullable', 'timezone'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
