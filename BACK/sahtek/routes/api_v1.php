<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\OutcomeController;
use App\Http\Controllers\Api\V1\PatientController;
use Illuminate\Support\Facades\Route;

/*
| docTHub API v1 — prefix /api/v1, middleware: api (throttle:api), locale.
| See docs/V2_API.md.
*/

// ---------- Authentication ----------
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// ---------- Public reference data ----------
Route::get('languages', [GeoController::class, 'languages']);
Route::get('countries', [GeoController::class, 'countries']);
Route::get('cities', [GeoController::class, 'cities']);

Route::get('body-parts', [CatalogController::class, 'bodyParts']);
Route::get('specialties', [CatalogController::class, 'specialties']);
Route::get('symptoms', [CatalogController::class, 'symptoms']);
Route::get('diseases', [CatalogController::class, 'diseases']);

// ---------- Symptom checker (guests allowed; patient-owned assessments protected by policy) ----------
Route::post('assessments', [AssessmentController::class, 'store'])->middleware('throttle:assessments');
Route::prefix('assessments/{assessment}')->whereUuid('assessment')->group(function () {
    Route::get('questions', [AssessmentController::class, 'questions']);
    Route::post('answers', [AssessmentController::class, 'answers'])->middleware('throttle:assessments');
    Route::post('evaluate', [AssessmentController::class, 'evaluate'])->middleware('throttle:assessments');
    Route::get('results', [AssessmentController::class, 'results']);
    Route::get('outcome', [OutcomeController::class, 'show']);
    Route::put('outcome', [OutcomeController::class, 'update'])->middleware('throttle:assessments');
});

// ---------- Doctor discovery ----------
Route::prefix('doctors')->group(function () {
    Route::get('/', [DoctorController::class, 'index']);
    Route::get('nearby', [DoctorController::class, 'nearby']);
    Route::get('countries', [GeoController::class, 'doctorCountries']);
    Route::get('cities', [GeoController::class, 'doctorCities']);
    Route::get('specialty/{specialty}', [DoctorController::class, 'bySpecialty'])->whereNumber('specialty');
    Route::get('{doctor}', [DoctorController::class, 'show'])->where('doctor', '[A-Za-z0-9-]+');
});

// ---------- Patient ----------
Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    Route::get('patient/profile', [PatientController::class, 'show']);
    Route::put('patient/profile', [PatientController::class, 'update']);
    Route::get('patient/assessments', [PatientController::class, 'assessments']);
    Route::get('patient/favorites', [PatientController::class, 'favorites']);
    Route::get('patient/export', [PatientController::class, 'export']);
    Route::delete('patient/account', [PatientController::class, 'destroy']);

    Route::post('favorites/{doctor}', [FavoriteController::class, 'store'])->whereNumber('doctor');
    Route::delete('favorites/{doctor}', [FavoriteController::class, 'destroy'])->whereNumber('doctor');
});

// ---------- Administration ----------
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'stats']);
    Route::get('audit-logs', [Admin\DashboardController::class, 'auditLogs']);

    $crud = function (string $uri, string $controller) {
        Route::get($uri, [$controller, 'index']);
        Route::post($uri, [$controller, 'store']);
        Route::get("$uri/{id}", [$controller, 'show'])->whereNumber('id');
        Route::put("$uri/{id}", [$controller, 'update'])->whereNumber('id');
        Route::patch("$uri/{id}/status", [$controller, 'updateStatus'])->whereNumber('id');
        Route::delete("$uri/{id}", [$controller, 'destroy'])->whereNumber('id');
    };

    $crud('specialties', Admin\SpecialtyController::class);
    $crud('body-parts', Admin\BodyPartController::class);
    $crud('symptoms', Admin\SymptomController::class);
    $crud('diseases', Admin\DiseaseController::class);
    $crud('guidelines', Admin\GuidelineController::class);
    $crud('questions', Admin\QuestionController::class);
    $crud('rules', Admin\RuleController::class);
    $crud('countries', Admin\CountryController::class);
    $crud('cities', Admin\CityController::class);
    $crud('languages', Admin\LanguageController::class);
    $crud('doctors', Admin\DoctorController::class);
    $crud('knowledge-sources', Admin\KnowledgeSourceController::class);
    $crud('source-documents', Admin\SourceDocumentController::class);
    Route::post('source-documents/{id}/fetch', [Admin\SourceDocumentController::class, 'fetch'])->whereNumber('id');

    Route::get('analytics', [Admin\AnalyticsController::class, 'indicators']);
    Route::get('analytics/export', [Admin\AnalyticsController::class, 'export']);

    Route::patch('doctors/{id}/verification', [Admin\DoctorController::class, 'verify'])->whereNumber('id');
    Route::post('doctors/{id}/photo', [Admin\DoctorController::class, 'uploadPhoto'])->whereNumber('id');
    Route::post('doctors/{id}/documents', [Admin\DoctorController::class, 'uploadDocument'])->whereNumber('id');
    Route::patch('doctors/{id}/documents/{document}', [Admin\DoctorController::class, 'reviewDocument'])->whereNumber(['id', 'document']);
    Route::get('doctors/{id}/documents/{document}/download', [Admin\DoctorController::class, 'downloadDocument'])->whereNumber(['id', 'document']);

    Route::get('patients', [Admin\PatientController::class, 'index']);
    Route::get('patients/{id}', [Admin\PatientController::class, 'show'])->whereNumber('id');
    Route::patch('patients/{id}/active', [Admin\PatientController::class, 'setActive'])->whereNumber('id');
});
