<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Disease;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiseaseController extends AdminCrudController
{
    protected array $with = ['symptoms:id,code,name', 'bodyParts:id,code,name', 'specialty:id,code,name'];
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['specialty_id', 'review_status'];

    protected function modelClass(): string
    {
        return Disease::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'code' => [$this->req($model), 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('diseases')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'severity' => ['sometimes', 'nullable', Rule::in(['low', 'moderate', 'high'])],
            'status' => ['sometimes', Rule::in(Disease::STATUSES)],
            'review_status' => ['sometimes', Rule::in(Disease::REVIEW_STATUSES)],
            'source_name' => ['nullable', 'string', 'max:255', 'required_if:review_status,approved'],
            // Name of the qualified person who reviewed it (checked by knowledge:validate for approved items).
            'reviewed_by_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_document_id' => ['nullable', 'integer', 'exists:source_documents,id'],
            'is_sample' => ['sometimes', 'boolean'],
            'symptoms' => ['sometimes', 'array'],
            'symptoms.*.id' => ['required', 'integer', 'exists:symptoms,id'],
            'symptoms.*.weight' => ['nullable', 'integer', 'min:1', 'max:10'],
            'body_parts' => ['sometimes', 'array'],
            'body_parts.*.id' => ['required', 'integer', 'exists:body_parts,id'],
            'body_parts.*.weight' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    protected function auditSnapshot(Model $model): array
    {
        /** @var Disease $model */
        return parent::auditSnapshot($model) + [
            'symptoms' => $model->symptoms()->orderBy('code')->get()->mapWithKeys(fn ($s) => [$s->code => (int) $s->pivot->weight])->all(),
            'body_parts' => $model->bodyParts()->orderBy('code')->get()->mapWithKeys(fn ($b) => [$b->code => (int) $b->pivot->weight])->all(),
        ];
    }

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var Disease $model */
        if (array_key_exists('symptoms', $data)) {
            $model->symptoms()->sync(collect($data['symptoms'])->mapWithKeys(fn ($s) => [$s['id'] => ['weight' => $s['weight'] ?? 1]])->all());
        }
        if (array_key_exists('body_parts', $data)) {
            $model->bodyParts()->sync(collect($data['body_parts'])->mapWithKeys(fn ($b) => [$b['id'] => ['weight' => $b['weight'] ?? 1]])->all());
        }
    }
}
