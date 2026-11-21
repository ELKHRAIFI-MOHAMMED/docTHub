<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\MedicalGuideline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Medical guidelines. Publication requires a source; approval requires a named reviewer.
 * Any content change on an approved guideline bumps the version and sends it back to review.
 */
class GuidelineController extends AdminCrudController
{
    protected array $with = ['disease:id,code,name', 'specialty:id,code,name'];
    protected array $searchable = ['title'];
    protected array $filterable = ['disease_id', 'specialty_id', 'guideline_type', 'review_status'];

    protected function modelClass(): string
    {
        return MedicalGuideline::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        return [
            'disease_id' => ['nullable', 'integer', 'exists:diseases,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'title' => [$this->req($model), 'string', 'max:255'],
            'content' => [$this->req($model), 'string', 'max:20000'],
            'guideline_type' => [$this->req($model), Rule::in(MedicalGuideline::TYPES)],
            'source_name' => [$this->req($model), 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_document_id' => ['nullable', 'integer', 'exists:source_documents,id'],
            'review_status' => ['sometimes', Rule::in(MedicalGuideline::REVIEW_STATUSES)],
            'reviewed_by_name' => ['nullable', 'string', 'max:255', 'required_if:review_status,approved'],
            'is_sample' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(MedicalGuideline::STATUSES)],
        ];
    }

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var MedicalGuideline $model */
        $contentChanged = $model->wasChanged(['title', 'content']) || isset($data['translations']);
        if (! $model->wasRecentlyCreated && $contentChanged) {
            $model->forceFill([
                'version' => $model->version + 1,
                'review_status' => ($data['review_status'] ?? null) === 'approved' ? 'approved' : 'in_review',
            ])->save();
        }
        if ($model->review_status === 'approved' && ! $model->reviewed_at) {
            $model->forceFill(['reviewed_at' => now()])->save();
        }
    }
}
