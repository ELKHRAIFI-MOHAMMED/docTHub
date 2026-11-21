<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Analytics\OutcomeAnalytics;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends ApiController
{
    public function __construct(private readonly OutcomeAnalytics $analytics, private readonly AuditLogger $audit)
    {
    }

    public function indicators(Request $request): JsonResponse
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'between:1,730']])['days'] ?? 90);

        return response()->json(['data' => $this->analytics->indicators(now()->subDays($days)->startOfDay())]);
    }

    /** Pseudonymised export, one JSON object per line (JSON Lines). Logged in the audit trail. */
    public function export(Request $request): StreamedResponse
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'between:1,730']])['days'] ?? 365);
        $this->audit->log('admin.analytics_exported', null, null, ['days' => $days]);

        return response()->streamDownload(function () use ($days) {
            foreach ($this->analytics->pseudonymisedRows(now()->subDays($days)->startOfDay()) as $row) {
                echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
            }
        }, 'docthub-pseudonymised-'.now()->format('Ymd').'.jsonl', ['Content-Type' => 'application/x-ndjson']);
    }
}
