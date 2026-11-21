<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Resources\AdminRecordResource;
use App\Models\SourceDocument;
use App\Services\Knowledge\SourceFetcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SourceDocumentController extends AdminCrudController
{
    protected array $with = ['source:id,name,license'];
    protected array $searchable = ['title', 'url'];
    protected array $filterable = ['knowledge_source_id'];
    protected ?string $statusColumn = null;
    protected string $defaultSort = '-updated_at';

    protected function modelClass(): string
    {
        return SourceDocument::class;
    }

    protected function rules(Request $request, ?Model $model): array
    {
        $sourceId = $request->input('knowledge_source_id', $model?->knowledge_source_id);

        return [
            'knowledge_source_id' => [$this->req($model), 'integer', 'exists:knowledge_sources,id'],
            'url' => [$this->req($model), 'url', 'max:500', Rule::unique('source_documents')->where('knowledge_source_id', $sourceId)->ignore($model)],
            'title' => [$this->req($model), 'string', 'max:500'],
            'language' => ['sometimes', 'string', 'max:10'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** Download now, fingerprint, and send cited approved content back to review if the document changed. */
    public function fetch(int $id, SourceFetcher $fetcher): JsonResponse
    {
        $document = SourceDocument::findOrFail($id);
        $result = $fetcher->fetch($document);

        return response()->json(['data' => (new AdminRecordResource($document->fresh($this->with)))->resolve(), 'meta' => $result]);
    }
}
