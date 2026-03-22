<?php

use Illuminate\Support\Facades\Route;
use Modules\RepoManagement\Http\Controllers\RepoManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('repomanagements', RepoManagementController::class)->names('repomanagement');
});
