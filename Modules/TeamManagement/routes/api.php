<?php

use Illuminate\Support\Facades\Route;
use Modules\TeamManagement\Http\Controllers\TeamManagementController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('teammanagements', TeamManagementController::class)->names('teammanagement');
});
