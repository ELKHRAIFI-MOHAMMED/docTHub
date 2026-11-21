<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends AdminCrudController
{
    protected array $searchable = ['name', 'code', 'native_name'];
    protected ?string $statusColumn = null;

    protected function modelClass(): string
    {
        return Language::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => [$this->req($model), 'string', 'max:10', 'regex:/^[a-z]{2,3}(-[A-Z]{2})?$/', Rule::unique('languages')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:100'],
            'native_name' => [$this->req($model), 'string', 'max:100'],
            'direction' => ['sometimes', Rule::in(['ltr', 'rtl'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
