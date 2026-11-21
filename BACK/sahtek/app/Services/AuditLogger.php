<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /** Attributes never written to the audit log. */
    private const REDACTED = ['password', 'remember_token', 'date_of_birth'];

    public function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id() ?? auth('sanctum')->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old !== null ? $this->clean($old) : null,
            'new_values' => $new !== null ? $this->clean($new) : null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    private function clean(array $values): array
    {
        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
