<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\ProjectManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('projectmanagements', ProjectManagementController::class)->names('projectmanagement');
});
