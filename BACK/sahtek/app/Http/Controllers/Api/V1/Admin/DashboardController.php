<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\AssessmentAlert;
use App\Models\AssessmentResult;
use App\Models\AuditLog;
use App\Models\Disease;
use App\Models\Doctor;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\SymptomAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function stats(): JsonResponse
    {
        $since = now()->subDays(30)->startOfDay();

        $perDay = SymptomAssessment::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')->orderBy('day')->pluck('total', 'day');

        $topSpecialties = AssessmentResult::query()
            ->where('result_type', 'specialty')->where('rank', 1)
            ->where('created_at', '>=', $since)
            ->selectRaw('specialty_id, COUNT(*) as total')
            ->groupBy('specialty_id')->orderByDesc('total')->limit(5)->get();
        $names = Specialty::withTrashed()->whereIn('id', $topSpecialties->pluck('specialty_id'))->pluck('name', 'id');

        return response()->json(['data' => [
            'patients' => Patient::count(),
            'doctors' => Doctor::selectRaw('verification_status, COUNT(*) as total')->groupBy('verification_status')->pluck('total', 'verification_status'),
            'diseases_published' => Disease::published()->count(),
            'rules' => OrientationRule::selectRaw('validation_status, COUNT(*) as total')->groupBy('validation_status')->pluck('total', 'validation_status'),
            'guidelines_pending_review' => MedicalGuideline::whereIn('review_status', ['draft', 'in_review'])->count(),
            'assessments_total' => SymptomAssessment::count(),
            'assessments_last_30_days' => $perDay,
            'emergency_alerts_last_30_days' => AssessmentAlert::where('created_at', '>=', $since)->count(),
            'top_recommended_specialties' => $topSpecialties->map(fn ($r) => ['specialty' => $names[$r->specialty_id] ?? '—', 'total' => (int) $r->total]),
        ]]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'auditable_id' => ['nullable', 'integer']]);
        $query = AuditLog::with('user:id,name,email')->orderByDesc('id');
        if ($request->filled('action')) {
            $query->where('action', 'like', addcslashes((string) $request->query('action'), '%_\\').'%');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        // History of one item (e.g. auditable_type=disease&auditable_id=12) and period filters.
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', (string) $request->query('auditable_type'));
        }
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->integer('auditable_id'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse((string) $request->query('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse((string) $request->query('to'))->endOfDay());
        }

        return response()->json($query->paginate(min((int) $request->query('per_page', 50), 100)));
    }
}
