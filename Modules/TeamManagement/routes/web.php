<?php

use Illuminate\Support\Facades\Route;
use Modules\TeamManagement\Http\Controllers\TeamManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('teammanagements', TeamManagementController::class)->names('teammanagement');
});
