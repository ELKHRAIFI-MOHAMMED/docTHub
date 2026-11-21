<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Audit trail of one knowledge item: who changed what, when, old and new value.
 *
 *   php artisan knowledge:history disease:icd11_1a00
 *   php artisan knowledge:history rule:em-chest-cardiac
 *   php artisan knowledge:history guideline:12          (items without code: id)
 */
class KnowledgeHistory extends Command
{
    protected $signature = 'knowledge:history {item : type:code or type:id (disease, symptom, rule, guideline, question, specialty, body_part…)}';

    protected $description = 'Show who changed a knowledge item, when, with old and new values';

    public function handle(): int
    {
        [$type, $key] = array_pad(explode(':', (string) $this->argument('item'), 2), 2, '');
        $class = Relation::getMorphedModel($type);
        if (! $class || $key === '') {
            $this->error('Use type:code, e.g. disease:icd11_1a00 (types: '.implode(', ', array_keys(Relation::morphMap())).').');

            return self::INVALID;
        }

        $query = method_exists($class, 'withTrashed') ? $class::withTrashed() : $class::query();
        $model = ctype_digit($key) && ! $this->hasCode($class, $key) ? $query->find((int) $key) : $query->where('code', $key)->first();
        if (! $model) {
            $this->error("No $type \"$key\".");

            return self::FAILURE;
        }

        $logs = AuditLog::with('user:id,name,email')->where('auditable_type', $type)->where('auditable_id', $model->getKey())->orderBy('id')->get();
        if ($logs->isEmpty()) {
            $this->info('No recorded change.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($logs as $log) {
            $who = $log->user ? "{$log->user->name} <{$log->user->email}>" : ($log->user_agent && str_starts_with($log->user_agent, 'artisan ') ? $log->user_agent : 'system');
            $fields = array_unique(array_merge(array_keys((array) $log->old_values), array_keys((array) $log->new_values)));
            if (! $fields) {
                $rows[] = [$log->created_at, $who, $log->action, '', '', ''];
            }
            foreach ($fields as $field) {
                $rows[] = [$log->created_at, $who, $log->action, $field, self::short($log->old_values[$field] ?? null), self::short($log->new_values[$field] ?? null)];
            }
        }
        $this->table(['When', 'Who', 'Action', 'Field', 'Old', 'New'], $rows);

        return self::SUCCESS;
    }

    private function hasCode(string $class, string $key): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn((new $class)->getTable(), 'code') && $class::query()->where('code', $key)->exists();
    }

    private static function short(mixed $value): string
    {
        $text = is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return mb_strimwidth((string) $text, 0, 60, '…');
    }
}
