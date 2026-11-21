<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['first_name'].' '.$data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'preferred_language' => $data['preferred_language'],
            ]);
            $user->assignRole(User::ROLE_PATIENT);

            $patient = Patient::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'sex' => $data['sex'] ?? null,
                'country_id' => $data['country_id'],
                'city_id' => $data['city_id'] ?? null,
            ]);
            $patient->preference()->create(['language_code' => $data['preferred_language']]);

            foreach (PatientConsent::TYPES as $type) {
                $patient->consents()->create([
                    'consent_type' => $type,
                    'version' => config('docthub.consent_version'),
                    'granted_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
            }

            return $user;
        });

        $this->audit->log('patient.registered', $user);

        return $this->tokenResponse($user, $request->input('device_name', 'web'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['This account is disabled.']]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user, $request->validated('device_name') ?? 'web');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['roles', 'patient.country', 'patient.city', 'patient.preference']));
    }

    private function tokenResponse(User $user, string $device, int $status = 200): JsonResponse
    {
        $token = $user->createToken(substr($device, 0, 100))->plainTextToken;
        $user->load(['roles', 'patient.country', 'patient.city', 'patient.preference']);

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], $status);
    }
}
