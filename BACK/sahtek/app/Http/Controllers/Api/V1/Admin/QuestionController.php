<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\QuestionOption;
use App\Models\SymptomQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Questionnaire questions with their options and display conditions (dynamic questionnaire). */
class QuestionController extends AdminCrudController
{
    protected array $with = ['options', 'conditions', 'bodyPart:id,code,name'];
    protected array $searchable = ['text', 'code'];
    protected array $filterable = ['body_part_id', 'type'];
    protected string $defaultSort = 'sort_order';

    protected function modelClass(): string
    {
        return SymptomQuestion::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $type = $request->input('type', $model?->type);

        return [
            'code' => [$this->req($model), 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('symptom_questions')->ignore($model)],
            'body_part_id' => ['nullable', 'integer', 'exists:body_parts,id'],
            'text' => [$this->req($model), 'string', 'max:1000'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'summary_template' => ['nullable', 'string', 'max:500'],
            'type' => [$this->req($model), Rule::in(SymptomQuestion::TYPES)],
            'min_value' => ['nullable', 'integer', Rule::requiredIf(in_array($type, ['scale'], true))],
            'max_value' => ['nullable', 'integer', 'gte:min_value', Rule::requiredIf(in_array($type, ['scale'], true))],
            'unit' => ['nullable', 'string', 'max:32'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(SymptomQuestion::STATUSES)],
            'options' => [Rule::requiredIf(! $model && in_array($type, ['single_choice', 'multi_choice'], true)), 'array'],
            'options.*.code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.score' => ['nullable', 'integer'],
            'options.*.symptom_id' => ['nullable', 'integer', 'exists:symptoms,id'],
            'options.*.translations' => ['nullable', 'array'],
            'options.*.translations.*.label' => ['nullable', 'string', 'max:255'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*.depends_on_question_id' => ['required', 'integer', 'exists:symptom_questions,id', Rule::notIn([$model?->id])],
            'conditions.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'in', 'not_in', 'gte', 'lte', 'answered', 'contains'])],
            'conditions.*.value' => ['nullable'],
        ];
    }

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var SymptomQuestion $model */
        if (array_key_exists('options', $data)) {
            $keep = [];
            foreach ($data['options'] as $i => $opt) {
                $option = QuestionOption::updateOrCreate(
                    ['question_id' => $model->id, 'code' => $opt['code']],
                    ['label' => $opt['label'], 'score' => $opt['score'] ?? null, 'symptom_id' => $opt['symptom_id'] ?? null, 'sort_order' => $i]
                );
                if (! empty($opt['translations'])) {
                    $option->syncTranslations($opt['translations']);
                }
                $keep[] = $option->id;
            }
            $model->options()->whereNotIn('id', $keep)->delete();
        }

        if (array_key_exists('conditions', $data)) {
            $model->conditions()->delete();
            foreach ($data['conditions'] as $condition) {
                $model->conditions()->create([
                    'depends_on_question_id' => $condition['depends_on_question_id'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'] ?? null,
                ]);
            }
        }
    }
}
