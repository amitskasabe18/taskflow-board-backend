<?php

use Illuminate\Support\Facades\Route;
use Modules\TicketManagement\Http\Controllers\TicketManagementController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ticketmanagements', TicketManagementController::class)->names('ticketmanagement');
});
