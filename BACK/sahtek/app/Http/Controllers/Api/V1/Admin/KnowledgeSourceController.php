<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class KnowledgeSourceController extends AdminCrudController
{
    protected array $searchable = ['name', 'publisher'];
    protected ?string $statusColumn = null;
    protected string $defaultSort = 'name';

    protected function modelClass(): string
    {
        return KnowledgeSource::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'name' => [$this->req($model), 'string', 'max:150'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'base_url' => ['nullable', 'url', 'max:500'],
            // The licence must be recorded before any content of this source is reused.
            'license' => [$this->req($model), 'string', 'max:255'],
            'license_url' => ['nullable', 'url', 'max:500'],
            'allowed_use' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
