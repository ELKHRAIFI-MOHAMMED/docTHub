<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\AdminRecordResource;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shared admin CRUD for catalog-like resources (protected by auth:sanctum + role:admin in routes).
 * Subclasses declare the model, validation rules and optional relation syncing.
 * Every write is audit-logged. Translations are accepted as {"translations": {"fr": {"name": "..."}}}.
 */
abstract class AdminCrudController extends ApiController
{
    /** @var string[] relations eager-loaded on index/show */
    protected array $with = [];

    /** @var string[] columns searched by ?q= */
    protected array $searchable = ['name'];

    /** @var string[] query params applied as equality filters */
    protected array $filterable = [];

    /** Column used by PATCH /{id}/status, with its allowed values. Null = no status workflow. */
    protected ?string $statusColumn = 'status';

    protected array $statuses = ['draft', 'published', 'archived'];

    protected string $defaultSort = 'id';

    public function __construct(protected readonly AuditLogger $audit)
    {
    }

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function rules(Request $request, ?Model $model): array;

    /** Hook for pivot / child syncing after create or update (inside the transaction). */
    protected function afterSave(Model $model, array $data, Request $request): void
    {
    }

    public function index(Request $request)
    {
        $query = $this->query()->with($this->with);

        if ($q = trim((string) $request->query('q'))) {
            $term = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function (Builder $w) use ($term) {
                foreach ($this->searchable as $column) {
                    $w->orWhere($column, 'like', $term);
                }
            });
        }
        foreach ($this->filterable as $param) {
            if ($request->filled($param)) {
                $query->where($param, $request->query($param));
            }
        }
        if ($this->statusColumn && $request->filled('status')) {
            $query->where($this->statusColumn, $request->query('status'));
        }
        if ($request->boolean('trashed') && $this->usesSoftDeletes()) {
            $query->onlyTrashed();
        }

        $sort = (string) $request->query('sort', $this->defaultSort);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $model = $this->newModel();
        if (in_array($column, array_merge(['id', 'created_at', 'updated_at'], $model->getFillable()), true)) {
            $query->orderBy($column, $direction);
        }

        return AdminRecordResource::collection($query->paginate(min(max((int) $request->query('per_page', 25), 1), 100)));
    }

    public function show(int $id): AdminRecordResource
    {
        return new AdminRecordResource($this->query()->with($this->with)->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);

        $model = DB::transaction(function () use ($data, $request) {
            $model = $this->newModel();
            $model->fill($data)->save();
            $this->saveTranslations($model, $data);
            $this->afterSave($model, $data, $request);

            return $model;
        });

        $this->audit->log('admin.created', $model, null, $model->attributesToArray());

        return (new AdminRecordResource($model->fresh($this->with)))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id): AdminRecordResource
    {
        $model = $this->query()->findOrFail($id);
        $data = $this->validated($request, $model);
        $old = $model->attributesToArray();

        DB::transaction(function () use ($model, $data, $request) {
            $model->fill($data)->save();
            $this->saveTranslations($model, $data);
            $this->afterSave($model, $data, $request);
        });

        $this->audit->log('admin.updated', $model, array_intersect_key($old, $model->getChanges()), $model->getChanges());

        return new AdminRecordResource($model->fresh($this->with));
    }

    /** Publish / archive / review workflow. */
    public function updateStatus(Request $request, int $id): AdminRecordResource
    {
        abort_if($this->statusColumn === null, 404);
        $data = $request->validate(['status' => ['required', Rule::in($this->statuses)]]);

        $model = $this->query()->findOrFail($id);
        $old = $model->getAttribute($this->statusColumn);
        $model->forceFill([$this->statusColumn => $data['status']])->save();

        $this->audit->log('admin.status_changed', $model, [$this->statusColumn => $old], [$this->statusColumn => $data['status']]);

        return new AdminRecordResource($model->fresh($this->with));
    }

    public function destroy(int $id): JsonResponse
    {
        $model = $this->query()->findOrFail($id);

        try {
            $model->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // Foreign key in use (e.g. a country still referenced by doctors).
            return response()->json(['message' => 'This record is still in use and cannot be deleted. Archive or deactivate it instead.'], 409);
        }

        $this->audit->log('admin.deleted', $model, $model->attributesToArray(), null);

        return response()->json(null, 204);
    }

    protected function query(): Builder
    {
        return $this->modelClass()::query();
    }

    protected function newModel(): Model
    {
        $class = $this->modelClass();

        return new $class();
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass()), true);
    }

    protected function validated(Request $request, ?Model $model): array
    {
        $rules = $this->rules($request, $model);
        if (method_exists($this->newModel(), 'translatableFields')) {
            $locales = implode(',', array_diff(config('docthub.supported_locales'), [config('app.fallback_locale')]));
            $rules['translations'] = ['sometimes', 'array'];
            foreach ($this->newModel()->translatableFields() as $field) {
                $rules["translations.*.$field"] = ['nullable', 'string', 'max:10000'];
            }
            $request->validate(['translations' => ['sometimes', 'array', function ($attr, $value, $fail) use ($locales) {
                foreach (array_keys((array) $value) as $locale) {
                    if (! in_array($locale, explode(',', $locales), true)) {
                        $fail("Unsupported translation locale: $locale.");
                    }
                }
            }]]);
        }

        return $request->validate($rules);
    }

    protected function saveTranslations(Model $model, array $data): void
    {
        if (isset($data['translations']) && method_exists($model, 'syncTranslations')) {
            $model->syncTranslations($data['translations']);
        }
    }

    /** Helper for "required on create, optional on update" rules. */
    protected function req(?Model $model): string
    {
        return $model ? 'sometimes' : 'required';
    }
}
