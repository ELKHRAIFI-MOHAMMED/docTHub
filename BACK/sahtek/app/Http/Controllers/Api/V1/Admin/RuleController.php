<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\OrientationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Orientation rules. Status workflow = validation_status (draft → in_review → approved | rejected | retired).
 * Editing the logic of an approved rule bumps its version and sends it back to review.
 */
class RuleController extends AdminCrudController
{
    protected array $with = ['bodyPart:id,code,name', 'question:id,code,text', 'disease:id,code,name', 'specialty:id,code,name'];
    protected array $searchable = ['name', 'code'];
    protected array $filterable = ['result_type', 'body_part_id', 'validation_status'];
    protected ?string $statusColumn = 'validation_status';
    protected array $statuses = ['draft', 'in_review', 'approved', 'rejected', 'retired'];
    protected string $defaultSort = '-priority';

    private const LOGIC_FIELDS = ['body_part_id', 'question_id', 'operator', 'expected_value', 'conditions', 'result_type', 'disease_id', 'specialty_id', 'emergency_severity'];

    protected function modelClass(): string
    {
        return OrientationRule::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $resultType = $request->input('result_type', $model?->result_type);
        // Target field is required for the result type on create, or when the type or the field itself is being changed.
        $needs = fn (string $type, string $field) => Rule::requiredIf(
            $resultType === $type && (! $model || $request->has('result_type') || $request->has($field))
        );

        return [
            'code' => [$this->req($model), 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('orientation_rules')->ignore($model)],
            'name' => [$this->req($model), 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'body_part_id' => ['nullable', 'integer', 'exists:body_parts,id'],
            'question_id' => ['nullable', 'integer', 'exists:symptom_questions,id'],
            'operator' => ['nullable', 'required_with:question_id', Rule::in(OrientationRule::OPERATORS)],
            'expected_value' => ['nullable'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.question' => ['required_without:conditions.*.field', 'string', 'exists:symptom_questions,code'],
            'conditions.*.field' => ['required_without:conditions.*.question', Rule::in(['age', 'sex'])],
            'conditions.*.operator' => ['required', Rule::in(OrientationRule::OPERATORS)],
            'conditions.*.value' => ['nullable'],
            'priority' => ['sometimes', 'integer', 'between:-1000,1000'],
            'result_type' => [$this->req($model), Rule::in(OrientationRule::RESULT_TYPES)],
            'disease_id' => ['nullable', 'integer', 'exists:diseases,id', $needs('disease', 'disease_id')],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id', $needs('specialty', 'specialty_id')],
            'guideline_id' => ['nullable', 'integer', 'exists:medical_guidelines,id', $needs('advice', 'guideline_id')],
            'emergency_severity' => ['nullable', Rule::in(['critical', 'urgent']), $needs('emergency', 'emergency_severity')],
            'weight' => ['sometimes', 'integer', 'between:1,10'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source_name' => [$this->req($model), 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_document_id' => ['nullable', 'integer', 'exists:source_documents,id'],
            'is_sample' => ['sometimes', 'boolean'],
        ];
    }

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var OrientationRule $model */
        if (! $model->body_part_id && ! $model->question_id && empty($model->conditions)) {
            abort(422, 'A rule needs at least one criterion (body part, question or condition).');
        }
        if (! $model->wasRecentlyCreated && $model->wasChanged(self::LOGIC_FIELDS)) {
            $model->forceFill([
                'version' => $model->version + 1,
                'validation_status' => $model->validation_status === 'approved' ? 'in_review' : $model->validation_status,
            ])->save();
        }
    }
}
