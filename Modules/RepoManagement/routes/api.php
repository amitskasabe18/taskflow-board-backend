<?php

use Illuminate\Support\Facades\Route;
use Modules\RepoManagement\Http\Controllers\RepoManagementController;

Route::apiResource('repomanagements', RepoManagementController::class)->names('repomanagement');

Route::prefix('repos')->group(function () {
    Route::get('/', [RepoManagementController::class, 'index']);
    Route::post('/', [RepoManagementController::class, 'store']);
    Route::get('/{repo}', [RepoManagementController::class, 'show']);
    Route::put('/{repo}', [RepoManagementController::class, 'update']);
    Route::delete('/{repo}', [RepoManagementController::class, 'destroy']);
    Route::get('/{repo}/files', [RepoManagementController::class, 'files']);
});
