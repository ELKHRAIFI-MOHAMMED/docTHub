<?php

namespace App\Services\Knowledge\Import;

use App\Models\KnowledgeSource;
use App\Models\SourceDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the current raw ICD-11 entity documents. Keeps ONLY code, title and URI (decision of 2026-11-21, see
 * docs/sources.md): no definition, no synonyms, no hierarchy, no link to other items.
 * One record per entity; the English document is the base, other WHO languages become translations.
 */
class IcdReader
{
    /** @return array{records: ImportRecord[], errors: string[], skipped: string[]} */
    public function read(KnowledgeSource $source, array $only = []): array
    {
        $only = array_map('strtoupper', $only);
        $byEntity = [];
        $errors = [];
        $skipped = [];

        $documents = SourceDocument::where('knowledge_source_id', $source->id)->where('status', 'active')->whereNotNull('raw_path')->orderBy('id')->get();
        foreach ($documents as $doc) {
            $json = json_decode((string) Storage::disk(config('docthub.knowledge.raw_disk'))->get($doc->raw_path), true);
            if (! is_array($json)) {
                $errors[] = "document #{$doc->id}: raw file missing or not JSON";

                continue;
            }
            $code = isset($json['code']) ? strtoupper((string) $json['code']) : null;
            if ($only && ! in_array($code, $only, true)) {
                continue;
            }
            if (($json['classKind'] ?? null) !== 'category' || ! $code) {
                $skipped[] = "document #{$doc->id}: ".($json['classKind'] ?? 'unknown').' without code (chapters and blocks are not imported)';

                continue;
            }

            try {
                $title = Normalizer::text($json['title']['@value'] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors[] = "$code ({$doc->language}): {$e->getMessage()}";

                continue;
            }
            if (! $title) {
                $errors[] = "$code ({$doc->language}): no title";

                continue;
            }

            $byEntity[$code]['uri'] ??= $json['@id'] ?? null;
            $byEntity[$code]['names'][$doc->language] = $title;
            $byEntity[$code]['docs'][$doc->language] = $doc->id;
        }

        $records = [];
        foreach ($byEntity as $code => $entity) {
            if (! isset($entity['names']['en'])) {
                $errors[] = "$code: no English version fetched (the base label must be English): run knowledge:fetch icd11 --target=$code --lang=en";

                continue;
            }
            $records[] = new ImportRecord(
                system: 'icd11',
                externalCode: $code,
                code: Normalizer::code('icd11', $code),
                uri: $entity['uri'],
                sourceDocumentId: $entity['docs']['en'],
                names: $entity['names'],
            );
        }

        return ['records' => $records, 'errors' => $errors, 'skipped' => $skipped];
    }
}
