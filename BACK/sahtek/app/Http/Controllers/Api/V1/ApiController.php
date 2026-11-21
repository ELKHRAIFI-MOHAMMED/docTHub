<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

/** Base class for V2 controllers (the V1 base Controller holds legacy endpoints and is not reused). */
abstract class ApiController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /** Patient of the (optionally) authenticated user on public routes. */
    protected function currentPatient(): ?Patient
    {
        return auth('sanctum')->user()?->patient;
    }
}
