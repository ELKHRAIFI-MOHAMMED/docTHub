<?php

namespace App\Services\Knowledge\Import;

use App\Models\KnowledgeSource;
use App\Models\SourceDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Reads health topics from the current raw MedlinePlus search results (structure: docs/sources.md):
 * <document><content name="healthTopic"><health-topic id title url language><also-called/>…<full-summary/>.
 * Keeps title, "also called" synonyms and the full summary (HTML removed). Links to other pages (A.D.A.M., sites…)
 * are never followed nor imported. English only: MedlinePlus has no French or Arabic text.
 */
class MedlinePlusReader
{
    /** @return array{records: ImportRecord[], errors: string[], skipped: string[]} */
    public function read(KnowledgeSource $source, array $only = []): array
    {
        $topics = [];
        $errors = [];
        $skipped = [];

        $documents = SourceDocument::where('knowledge_source_id', $source->id)->where('status', 'active')->whereNotNull('raw_path')->orderBy('id')->get();
        foreach ($documents as $doc) {
            $xml = self::parse((string) Storage::disk(config('docthub.knowledge.raw_disk'))->get($doc->raw_path));
            if (! $xml) {
                $errors[] = "document #{$doc->id}: raw file missing or not XML";

                continue;
            }

            foreach ($xml->xpath('//document/content[@name="healthTopic"]/health-topic') ?: [] as $topic) {
                $id = (string) $topic['id'];
                if ($id === '' || ($only && ! in_array($id, $only, true)) || isset($topics[$id])) {
                    continue; // the same topic can appear in several searches: first (oldest) document wins
                }
                if ((string) $topic['language'] !== 'English') {
                    $skipped[] = "topic $id: language ".((string) $topic['language'] ?: 'unknown').' not imported';

                    continue;
                }

                try {
                    $title = Normalizer::text((string) $topic['title']);
                    $summary = Normalizer::text((string) $topic->{'full-summary'}, multiline: true);
                    $aliases = array_values(array_filter(array_map(fn ($a) => Normalizer::text((string) $a), iterator_to_array($topic->{'also-called'}, false))));
                } catch (\InvalidArgumentException $e) {
                    $errors[] = "topic $id: {$e->getMessage()}";

                    continue;
                }
                if (! $title) {
                    $errors[] = "topic $id: no title";

                    continue;
                }

                $topics[$id] = new ImportRecord(
                    system: 'medlineplus',
                    externalCode: $id,
                    code: Normalizer::code('mplus', $id),
                    uri: ((string) $topic['url']) ?: null,
                    sourceDocumentId: $doc->id,
                    names: ['en' => $title],
                    descriptions: $summary ? ['en' => $summary] : [],
                    aliases: $aliases ? ['en' => $aliases] : [],
                );
            }
        }

        return ['records' => array_values($topics), 'errors' => $errors, 'skipped' => $skipped];
    }

    private static function parse(string $body): ?\SimpleXMLElement
    {
        if ($body === '') {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
