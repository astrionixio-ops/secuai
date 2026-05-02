<?php

/*
|--------------------------------------------------------------------------
| Phase 2 API Routes
|--------------------------------------------------------------------------
|
| Append the contents of this file into routes/api.php INSIDE the existing
| authenticated + tenant-scoped middleware group used in Phase 1.
|
| In Phase 1 the group looked roughly like:
|
|   Route::middleware(['auth:api', 'resolve.tenant'])->group(function () {
|       // ...phase 1 routes...
|   });
|
| The routes below assume:
|   - 'auth:api'        — JWT auth via tymon/jwt-auth
|   - 'resolve.tenant'  — ResolveTenant middleware that hydrates TenantContext
|   - 'tenant.role:...' — EnsureTenantRole middleware (used for sensitive ops)
|
| Adjust the middleware names if Phase 1 registered them differently.
*/

use App\Http\Controllers\Api\AiSummaryController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\CloudCredentialController;
use App\Http\Controllers\Api\ControlController;
use App\Http\Controllers\Api\CoverageSnapshotController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EnvironmentController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\EvidencePackController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\FrameworkController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ScanJobController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'resolve.tenant'])->group(function () {

    // -- Organizations & Environments ------------------------------------
    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('environments', EnvironmentController::class);

    // -- Cloud credentials (sensitive) -----------------------------------
    Route::apiResource('cloud-credentials', CloudCredentialController::class);
    Route::post('cloud-credentials/{cloud_credential}/rotate', [CloudCredentialController::class, 'rotate'])
        ->name('cloud-credentials.rotate');

    // -- Assets & Scans --------------------------------------------------
    Route::apiResource('assets', AssetController::class);

    Route::apiResource('scan-jobs', ScanJobController::class)
        ->only(['index', 'store', 'show', 'destroy']);
    Route::post('scan-jobs/simulate', [ScanJobController::class, 'simulate'])
        ->name('scan-jobs.simulate');

    Route::apiResource('findings', FindingController::class);

    // -- Frameworks & Controls (read-only reference data) ----------------
    Route::apiResource('frameworks', FrameworkController::class)->only(['index', 'show']);
    Route::get('frameworks/{framework}/controls', [FrameworkController::class, 'controls'])
        ->name('frameworks.controls');
    Route::apiResource('controls', ControlController::class)->only(['index', 'show']);

    // -- Assessments & Evidence ------------------------------------------
    Route::apiResource('assessments', AssessmentController::class);
    Route::post('assessments/{assessment}/frameworks', [AssessmentController::class, 'syncFrameworks'])
        ->name('assessments.frameworks.sync');

    Route::apiResource('evidence', EvidenceController::class)->parameters(['evidence' => 'evidence']);

    Route::apiResource('evidence-packs', EvidencePackController::class)
        ->only(['index', 'store', 'show', 'destroy']);
    Route::post('evidence-packs/{evidence_pack}/build', [EvidencePackController::class, 'build'])
        ->name('evidence-packs.build');

    // -- Documents -------------------------------------------------------
    Route::apiResource('documents', DocumentController::class);

    // -- Coverage snapshots & AI summaries -------------------------------
    Route::apiResource('coverage-snapshots', CoverageSnapshotController::class)
        ->only(['index', 'show']);
    Route::post('coverage-snapshots/generate', [CoverageSnapshotController::class, 'generate'])
        ->name('coverage-snapshots.generate');

    Route::apiResource('ai-summaries', AiSummaryController::class)
        ->only(['index', 'store', 'show', 'destroy']);

});
