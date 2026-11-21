<?php

namespace App\Console\Commands;

use App\Services\Knowledge\Validation\Issue;
use App\Services\Knowledge\Validation\KnowledgeValidator;
use Illuminate\Console\Command;

/**
 * Quality gate of the knowledge base. Exit code 1 when any error is found (also with --strict for warnings).
 *
 *   php artisan knowledge:validate
 *   php artisan knowledge:validate --format=markdown >> "$GITHUB_STEP_SUMMARY"
 *   php artisan knowledge:validate --format=json
 */
class KnowledgeValidate extends Command
{
    protected $signature = 'knowledge:validate
        {--format=table : table, markdown or json}
        {--strict : Warnings also make the command fail}';

    protected $description = 'Check the medical knowledge base (links, approved items, codes, values, emergency rules)';

    private const CHECKS = [
        'broken_link' => 'Links to existing symptoms / conditions / specialties',
        'approved_incomplete' => 'Approved items: EN/FR/AR, source, reviewer, version',
        'duplicate_code' => 'No duplicate codes',
        'invalid_value' => 'Severity in low/moderate/high, priority 0..100',
        'disease_incomplete' => 'Conditions have a body part, a symptom and a specialty',
        'emergency_reviewer' => 'Every active emergency rule has a reviewer',
    ];

    public function handle(KnowledgeValidator $validator): int
    {
        $issues = $validator->run();
        $errors = count(array_filter($issues, fn (Issue $i) => $i->level === 'error'));
        $warnings = count($issues) - $errors;
        $failed = $errors > 0 || ($this->option('strict') && $warnings > 0);

        $summary = [];
        foreach (self::CHECKS as $check => $label) {
            $e = count(array_filter($issues, fn (Issue $i) => $i->check === $check && $i->level === 'error'));
            $w = count(array_filter($issues, fn (Issue $i) => $i->check === $check && $i->level === 'warning'));
            $summary[] = [$e ? 'FAIL' : 'OK', $check, $label, $e, $w];
        }
        $details = array_map(fn (Issue $i) => [$i->level, $i->check, $i->item, $i->message], $issues);

        match ($this->option('format')) {
            'json' => $this->line(json_encode([
                'passed' => ! $failed, 'errors' => $errors, 'warnings' => $warnings,
                'issues' => array_map(fn (Issue $i) => $i->toArray(), $issues),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'markdown' => $this->markdown($summary, $details, $failed, $errors, $warnings),
            default => $this->human($summary, $details, $failed, $errors, $warnings),
        };

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function human(array $summary, array $details, bool $failed, int $errors, int $warnings): void
    {
        $this->table(['Result', 'Check', 'Rule', 'Errors', 'Warnings'], $summary);
        if ($details) {
            $this->table(['Level', 'Check', 'Item', 'Problem'], $details);
        }
        $failed ? $this->error("Knowledge base NOT valid: $errors error(s), $warnings warning(s).")
            : $this->info("Knowledge base valid ($warnings warning(s)).");
    }

    private function markdown(array $summary, array $details, bool $failed, int $errors, int $warnings): void
    {
        $esc = fn ($v) => str_replace(['|', "\n"], ['\\|', ' '], (string) $v);
        $lines = ['## knowledge:validate — '.($failed ? "❌ $errors error(s)" : '✅ valid').", $warnings warning(s)", '',
            '| Result | Check | Rule | Errors | Warnings |', '|---|---|---|---|---|'];
        foreach ($summary as $row) {
            $lines[] = '| '.implode(' | ', array_map($esc, $row)).' |';
        }
        if ($details) {
            array_push($lines, '', '| Level | Check | Item | Problem |', '|---|---|---|---|');
            foreach ($details as $row) {
                $lines[] = '| '.implode(' | ', array_map($esc, $row)).' |';
            }
        }
        $this->line(implode("\n", $lines));
    }
}
