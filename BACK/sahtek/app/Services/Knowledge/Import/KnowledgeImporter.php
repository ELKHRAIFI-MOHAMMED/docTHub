<?php

namespace App\Services\Knowledge\Import;

use App\Models\Disease;
use App\Models\KnowledgeAlias;
use App\Models\KnowledgeSource;
use App\Models\Symptom;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes normalised records into the EXISTING catalog tables (symptoms or diseases).
 *
 * - The type (symptom / condition) is chosen by the operator (--as): the sources do not give it in a way we verified.
 * - Idempotent: keyed on the internal code (updateOrCreate semantics), rerunning creates nothing new.
 * - Every imported row: status draft, review draft, version 1, is_sample false, source_document_id, severity NULL,
 *   no specialty, no body part, no symptom link, no rule. Never approved, never published.
 * - An item that is no longer a draft (reviewed / published) is never overwritten: differences are reported.
 * - Only locales actually provided become translations; the others are listed in needs_translation.
 * - Synonyms go to knowledge_aliases, deduplicated per locale; an alias already used by another item is reported.
 */
class KnowledgeImporter
{
    public const TYPES = ['disease' => Disease::class, 'symptom' => Symptom::class];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** @return array{action: string, model: ?Model, notes: string[]} action: created|updated|unchanged|conflict */
    public function import(KnowledgeSource $source, ImportRecord $record, string $type): array
    {
        $class = self::TYPES[$type];
        $other = $type === 'disease' ? Symptom::class : Disease::class;
        $notes = [];

        if ($other::withTrashed()->where('code', $record->code)->exists()) {
            return ['action' => 'conflict', 'model' => null, 'notes' => ['already imported as '.($type === 'disease' ? 'symptom' : 'disease').': type not changed']];
        }

        return DB::transaction(function () use ($source, $record, $type, $class, &$notes) {
            /** @var Model $model */
            $model = $class::withTrashed()->where('code', $record->code)->first();
            $isNew = ! $model;
            $model ??= new $class(['code' => $record->code]);

            $content = [
                'name' => mb_substr($record->names['en'], 0, $type === 'disease' ? 200 : 150),
                'external_system' => $record->system,
                'external_code' => $record->externalCode,
                'external_uri' => $record->uri,
                'source_document_id' => $record->sourceDocumentId,
            ];
            if (isset($record->descriptions['en'])) { // ICD-11: code, title and URI only, an existing description is left alone
                $content['description'] = $record->descriptions['en'];
            }
            if ($type === 'disease') {
                $content['source_name'] = $source->name;
                $content['source_url'] = $record->uri;
            }

            if (! $isNew && ! $this->isDraft($model)) {
                $model->fill($content);
                $differs = $model->isDirty() || $this->translationsDiffer($model, $record);
                $model->refresh();

                return ['action' => $differs ? 'conflict' : 'unchanged', 'model' => $model,
                    'notes' => $differs ? ['already reviewed or published: left unchanged, review it against the new source'] : []];
            }

            if ($isNew) {
                $content += ['status' => 'draft', 'version' => 1];
                if ($type === 'disease') {
                    $content += ['review_status' => 'draft', 'is_sample' => false, 'severity' => null];
                }
            }
            $model->fill($content);
            $changed = $model->isDirty();
            $model->save();

            $changed = $this->syncTranslations($model, $record) || $changed;
            $missing = $this->missingLocales($model, $record);
            if ($missing !== ($model->needs_translation ?? [])) {
                $model->forceFill(['needs_translation' => $missing ?: null])->save();
                $changed = $changed || ! $isNew;
            }
            [$aliasChanged, $aliasNotes] = $this->syncAliases($model, $record);
            $changed = $changed || $aliasChanged;
            $notes = array_merge($notes, $aliasNotes, $this->possibleDuplicates($model, $type));

            $action = $isNew ? 'created' : ($changed ? 'updated' : 'unchanged');
            if ($action !== 'unchanged') {
                $this->audit->log("knowledge.import.$action", $model, null, [
                    'code' => $model->code, 'external' => "{$record->system}:{$record->externalCode}", 'source_document_id' => $record->sourceDocumentId,
                ]);
            }

            return ['action' => $action, 'model' => $model, 'notes' => $notes];
        });
    }

    private function isDraft(Model $model): bool
    {
        return $model->status === 'draft' && ! $model->trashed()
            && (! $model instanceof Disease || $model->review_status === 'draft');
    }

    /** Only locales provided by the source; existing translations of other locales are kept. */
    private function syncTranslations(Model $model, ImportRecord $record): bool
    {
        $changed = false;
        foreach (config('docthub.supported_locales') as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $fields = array_filter(['name' => $record->names[$locale] ?? null, 'description' => $record->descriptions[$locale] ?? null]);
            foreach ($fields as $field => $value) {
                $existing = $model->translations()->where(['locale' => $locale, 'field' => $field])->value('value');
                if ($existing !== $value) {
                    $model->syncTranslations([$locale => [$field => $value]]);
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    private function translationsDiffer(Model $model, ImportRecord $record): bool
    {
        foreach ($record->names as $locale => $value) {
            if ($locale !== 'en' && $model->translations()->where(['locale' => $locale, 'field' => 'name'])->value('value') !== $value) {
                return true;
            }
        }

        return false;
    }

    /** Locales for which the name has no text at all (neither from the source nor added by hand). */
    private function missingLocales(Model $model, ImportRecord $record): array
    {
        $missing = [];
        foreach (config('docthub.supported_locales') as $locale) {
            if ($locale === 'en' || isset($record->names[$locale])) {
                continue;
            }
            if (! $model->translations()->where(['locale' => $locale, 'field' => 'name'])->exists()) {
                $missing[] = $locale;
            }
        }

        return $missing;
    }

    /** @return array{0: bool, 1: string[]} */
    private function syncAliases(Model $model, ImportRecord $record): array
    {
        $changed = false;
        $notes = [];
        $nameKeys = [Normalizer::key($model->name)];

        foreach ($record->aliases as $locale => $aliases) {
            foreach ($aliases as $alias) {
                $key = Normalizer::key($alias);
                if ($key === '' || in_array($key, $nameKeys, true)) {
                    continue; // same as the name: not a synonym
                }
                $existing = KnowledgeAlias::where(['aliasable_type' => $model->getMorphClass(), 'locale' => $locale, 'normalized' => $key])->first();
                if ($existing && (int) $existing->aliasable_id !== (int) $model->getKey()) {
                    $owner = $model::withTrashed()->find($existing->aliasable_id);
                    $notes[] = "alias \"$alias\" already belongs to ".($owner?->code ?? "#{$existing->aliasable_id}").': not added';

                    continue;
                }
                if (! $existing) {
                    $model->aliases()->create(['locale' => $locale, 'alias' => mb_substr($alias, 0, 255), 'normalized' => mb_substr($key, 0, 255), 'source_document_id' => $record->sourceDocumentId]);
                    $changed = true;
                }
            }
        }

        return [$changed, $notes];
    }

    /** Same English name under another code (e.g. the same condition from two sources): reported, never merged. */
    private function possibleDuplicates(Model $model, string $type): array
    {
        $key = Normalizer::key($model->name);
        $others = $model::query()->whereKeyNot($model->getKey())->whereRaw('LOWER(name) LIKE ?', ['%'.explode(' ', $key)[0].'%'] /* pre-filter on the first word */)->get(['id', 'code', 'name'])
            ->filter(fn ($m) => Normalizer::key($m->name) === $key)->pluck('code')->all();

        return $others ? ['possible duplicate of '.implode(', ', $others).' (same name, not merged)'] : [];
    }
}
