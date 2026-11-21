<?php

namespace App\Console\Commands;

use App\Models\KnowledgeRelease;
use App\Services\AuditLogger;
use App\Services\Knowledge\Release\KnowledgeSnapshot;
use App\Services\Knowledge\Validation\Issue;
use App\Services\Knowledge\Validation\KnowledgeValidator;
use App\Services\Orientation\ContentVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Versioned snapshot of the APPROVED knowledge: storage/app/exports/knowledge_vX.Y.json + a knowledge_releases row.
 *
 * - Refused while knowledge:validate reports errors, or when nothing is approved.
 * - Same content as the latest release: no new version (nothing to release).
 * - Minor bump by default (1.0 → 1.1), --major for 2.0. Files and versions are never overwritten.
 */
class KnowledgeExport extends Command
{
    protected $signature = 'knowledge:export
        {--major : New major version (X+1.0) instead of a minor one}
        {--notes= : What changed in this release}';

    protected $description = 'Export the approved knowledge as a new versioned JSON snapshot';

    public function handle(KnowledgeSnapshot $snapshot, KnowledgeValidator $validator, ContentVersion $versions, AuditLogger $audit): int
    {
        $errors = array_filter($validator->run(), fn (Issue $i) => $i->level === 'error');
        if ($errors) {
            $this->error(count($errors).' validation error(s): fix them first (php artisan knowledge:validate). Nothing exported.');

            return self::FAILURE;
        }

        $snap = $snapshot->build();
        if (($snap['counts']['diseases'] + $snap['counts']['guidelines'] + $snap['counts']['rules']) === 0) {
            $this->error('Nothing is approved yet: there is no knowledge to release.');

            return self::FAILURE;
        }

        $latest = KnowledgeRelease::latestRelease();
        if ($latest && $latest->content_hash === $snap['content_hash']) {
            $this->info("Approved content unchanged since v{$latest->version}: no new release.");

            return self::SUCCESS;
        }

        [$major, $minor] = match (true) {
            ! $latest => [1, 0],
            (bool) $this->option('major') => [$latest->major + 1, 0],
            default => [$latest->major, $latest->minor + 1],
        };
        $version = "$major.$minor";
        $path = "exports/knowledge_v$version.json";
        $disk = Storage::disk(config('docthub.knowledge.exports_disk'));
        if ($disk->exists($path)) {
            $this->error("$path already exists: refusing to overwrite a release file.");

            return self::FAILURE;
        }

        $document = [
            'knowledge_version' => $version,
            'generated_at' => now()->toIso8601String(),
            'previous_version' => $latest?->version,
            'engine_version' => config('docthub.engine_version'),
            'questionnaire_version' => $versions->questionnaire(),
            'ruleset_version' => $versions->ruleset(),
            'content_hash' => $snap['content_hash'],
            'counts' => $snap['counts'],
            'notes' => $this->option('notes'),
            'scope' => 'Approved conditions, guidelines and rules, plus the specialties, body parts, symptoms and questions they reference. '
                .'Emergency rules that are not approved stay active in the engine but are NOT part of this release.',
            'items' => $snap['items'],
        ];
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $disk->put($path, $json);

        $release = KnowledgeRelease::create([
            'version' => $version, 'major' => $major, 'minor' => $minor,
            'content_hash' => $snap['content_hash'],
            'questionnaire_version' => $document['questionnaire_version'],
            'ruleset_version' => $document['ruleset_version'],
            'counts' => $snap['counts'],
            'file_path' => $path,
            'file_sha256' => hash('sha256', $json),
            'created_by' => auth()->id(),
            'created_via' => 'artisan knowledge:export',
            'notes' => $this->option('notes'),
        ]);
        $audit->log('knowledge.exported', $release, $latest ? ['version' => $latest->version, 'content_hash' => $latest->content_hash] : null,
            ['version' => $version, 'content_hash' => $snap['content_hash'], 'file' => $path, 'counts' => $snap['counts']]);

        $this->table(['Section', 'Items'], collect($snap['counts'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info("Knowledge v$version exported to storage/app/$path");

        return self::SUCCESS;
    }
}
