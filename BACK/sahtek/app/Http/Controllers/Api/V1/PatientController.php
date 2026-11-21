<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Patient\UpdateProfileRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\PatientResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PatientController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function show(Request $request): PatientResource
    {
        return new PatientResource($this->patient($request)->load(['country', 'city', 'preference']));
    }

    public function update(UpdateProfileRequest $request): PatientResource
    {
        $patient = $this->patient($request);
        $data = $request->validated();

        DB::transaction(function () use ($patient, $data, $request) {
            $patient->fill(array_intersect_key($data, array_flip(['first_name', 'last_name', 'phone', 'date_of_birth', 'sex', 'country_id', 'city_id'])));
            if (array_key_exists('country_id', $data) && ! array_key_exists('city_id', $data) && $patient->isDirty('country_id')) {
                $patient->city_id = null;
            }
            $patient->save();

            $user = $request->user();
            $user->fill(array_filter([
                'name' => trim($patient->first_name.' '.$patient->last_name),
                'phone' => $data['phone'] ?? $user->phone,
                'preferred_language' => $data['preferred_language'] ?? null,
            ], fn ($v) => $v !== null))->save();

            $prefs = array_intersect_key($data, array_flip(['share_location', 'email_notifications']));
            if (isset($data['preferred_language'])) {
                $prefs['language_code'] = $data['preferred_language'];
            }
            if ($prefs) {
                $patient->preference()->updateOrCreate([], $prefs);
            }
        });

        $this->audit->log('patient.profile_updated', $patient);

        return new PatientResource($patient->fresh()->load(['country', 'city', 'preference']));
    }

    public function assessments(Request $request)
    {
        $page = $this->patient($request)->assessments()
            ->with(['alerts', 'outcome', 'results' => fn ($q) => $q->where('result_type', 'specialty')->with(['specialty' => fn ($s) => $s->withLocaleTranslations()])])
            ->latest()
            ->paginate(min((int) $request->query('per_page', 10), 50));

        return AssessmentResource::collection($page);
    }

    public function favorites(Request $request)
    {
        $patient = $this->patient($request);
        $doctors = $patient->favoriteDoctors()
            ->with(['specialties' => fn ($q) => $q->withLocaleTranslations(), 'languages', 'locations.city', 'locations.country'])
            ->orderByDesc('favorites.created_at')
            ->paginate(min((int) $request->query('per_page', 12), 50));

        DoctorResource::$favoriteIds = $doctors->pluck('id')->all();

        return DoctorResource::collection($doctors);
    }

    /** Right of access / portability: everything we store about the patient, as JSON. */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = $this->patient($request)->load(['country', 'city', 'preference', 'consents', 'favoriteDoctors:id,first_name,last_name']);
        $assessments = $patient->assessments()->with(['answers', 'results', 'alerts'])->get();

        $this->audit->log('patient.data_exported', $patient);

        return response()->json(['data' => [
            'exported_at' => now()->toIso8601String(),
            'account' => $user->only(['name', 'email', 'phone', 'preferred_language', 'created_at', 'last_login_at']),
            'profile' => $patient->only(['first_name', 'last_name', 'phone', 'date_of_birth', 'sex', 'created_at']) + [
                'country' => $patient->country?->name,
                'city' => $patient->city?->name,
            ],
            'preferences' => $patient->preference?->only(['language_code', 'share_location', 'email_notifications']),
            'consents' => $patient->consents->map->only(['consent_type', 'version', 'granted_at', 'revoked_at']),
            'favorite_doctors' => $patient->favoriteDoctors->map(fn ($d) => $d->first_name.' '.$d->last_name),
            'assessments' => $assessments->map(fn ($a) => [
                'id' => $a->uuid,
                'created_at' => $a->created_at,
                'body_part_ids' => $a->body_part_ids,
                'answers' => $a->answers->map->only(['question_id', 'option_codes', 'value']),
                'results' => $a->results->map->only(['result_type', 'disease_id', 'specialty_id', 'guideline_id', 'rank']),
                'alerts' => $a->alerts->map->only(['severity', 'message']),
            ]),
        ]]);
    }

    /**
     * Right to erasure. Identifying data is removed immediately; assessments are kept
     * de-identified (patient link removed) for aggregate statistics.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }

        $patient = $this->patient($request);

        DB::transaction(function () use ($user, $patient) {
            $this->audit->log('patient.account_deleted', $patient);

            $patient->assessments()->update(['patient_id' => null]);
            $patient->favoriteDoctors()->detach();
            $patient->forceFill(['first_name' => 'Deleted', 'last_name' => 'Patient', 'phone' => null, 'date_of_birth' => null, 'sex' => null])->save();
            $patient->delete();

            $user->tokens()->delete();
            $user->forceFill([
                'name' => 'Deleted user',
                'email' => 'deleted-'.$user->id.'@deleted.invalid',
                'phone' => null,
                'is_active' => false,
            ])->save();
            $user->delete();
        });

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 403, 'Only patients can access this resource.');

        return $patient;
    }
}
