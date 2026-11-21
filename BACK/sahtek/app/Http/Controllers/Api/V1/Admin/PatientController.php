<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Patient administration. Data minimisation: admins see account/profile data and counts,
 * not the content of assessments.
 */
class PatientController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Patient::query()->with(['user:id,email,is_active,last_login_at,created_at', 'country:id,name,iso2', 'city:id,name'])
            ->withCount(['assessments', 'favoriteDoctors']);

        if ($q = trim((string) $request->query('q'))) {
            $term = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)));
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        $page = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));
        $page->getCollection()->transform(fn (Patient $p) => $this->present($p));

        return response()->json($page);
    }

    public function show(int $id): JsonResponse
    {
        $patient = Patient::with(['user', 'country', 'city', 'consents'])->withCount(['assessments', 'favoriteDoctors'])->findOrFail($id);
        $this->audit->log('admin.patient_viewed', $patient);

        return response()->json(['data' => $this->present($patient) + [
            'consents' => $patient->consents->map->only(['consent_type', 'version', 'granted_at', 'revoked_at']),
        ]]);
    }

    public function setActive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $patient = Patient::with('user')->findOrFail($id);

        $patient->user->forceFill(['is_active' => $data['is_active']])->save();
        if (! $data['is_active']) {
            $patient->user->tokens()->delete();
        }

        $this->audit->log($data['is_active'] ? 'admin.patient_activated' : 'admin.patient_deactivated', $patient);

        return response()->json(['data' => $this->present($patient->fresh(['user', 'country', 'city'])->loadCount(['assessments', 'favoriteDoctors']))]);
    }

    private function present(Patient $p): array
    {
        return [
            'id' => $p->id,
            'first_name' => $p->first_name,
            'last_name' => $p->last_name,
            'email' => $p->user?->email,
            'is_active' => (bool) $p->user?->is_active,
            'country' => $p->country?->name,
            'city' => $p->city?->name,
            'assessments_count' => $p->assessments_count,
            'favorites_count' => $p->favorite_doctors_count,
            'last_login_at' => $p->user?->last_login_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
