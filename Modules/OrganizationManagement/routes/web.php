<?php

use Illuminate\Support\Facades\Route;
use Modules\OrganizationManagement\Http\Controllers\OrganizationManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('organizationmanagements', OrganizationManagementController::class)->names('organizationmanagement');
});
