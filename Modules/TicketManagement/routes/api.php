<?php

use Illuminate\Support\Facades\Route;
use Modules\TicketManagement\Http\Controllers\TicketManagementController;
use Modules\TicketManagement\Http\Controllers\TicketController;

Route::middleware(['auth:api'])->prefix('v1')->group(function () {
    Route::apiResource('ticketmanagements', TicketManagementController::class)->names('ticketmanagement');
    
    // Ticket routes
    Route::get('/projects/{projectId}/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/projects/{projectId}/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    
    // Ticket relationships
    Route::get('/tickets/{ticket}/comments', [TicketController::class, 'comments'])->name('tickets.comments');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'addComment'])->name('tickets.comments.store');
    Route::get('/tickets/{ticket}/attachments', [TicketController::class, 'attachments'])->name('tickets.attachments');
    Route::post('/tickets/{ticket}/attachments', [TicketController::class, 'addAttachment'])->name('tickets.attachments.store');
    Route::get('/tickets/{ticket}/history', [TicketController::class, 'history'])->name('tickets.history');
    Route::get('/tickets/{ticket}/time-logs', [TicketController::class, 'timeLogs'])->name('tickets.time-logs');
    Route::post('/tickets/{ticket}/time-logs', [TicketController::class, 'addTimeLog'])->name('tickets.time-logs.store');
    
    // Ticket operations
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/watch', [TicketController::class, 'watch'])->name('tickets.watch');
    Route::post('/tickets/{ticket}/unwatch', [TicketController::class, 'unwatch'])->name('tickets.unwatch');
    Route::post('/tickets/{ticket}/link', [TicketController::class, 'link'])->name('tickets.link');
    Route::post('/tickets/{ticket}/move', [TicketController::class, 'move'])->name('tickets.move');
    Route::patch('/tickets/{ticket_id}/update-ticket-status', [TicketController::class, 'updateTicketStatus'])->name('tickets.update-status');
});
