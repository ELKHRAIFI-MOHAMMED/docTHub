<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends ApiController
{
    public function store(Request $request, int $doctor): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 403, 'Only patients can save favourites.');

        $model = Doctor::publiclyVisible()->findOrFail($doctor);
        $patient->favoriteDoctors()->syncWithoutDetaching([$model->id]);

        return response()->json(['data' => ['doctor_id' => $model->id, 'is_favorite' => true]], 201);
    }

    public function destroy(Request $request, int $doctor): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if(! $patient, 403, 'Only patients can save favourites.');

        $patient->favoriteDoctors()->detach($doctor);

        return response()->json(['data' => ['doctor_id' => $doctor, 'is_favorite' => false]]);
    }
}
