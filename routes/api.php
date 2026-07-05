<?php

use App\Http\Controllers\Api\Bloodstream\ContractsController;
use App\Http\Controllers\Api\Bloodstream\SubjectsController;
use App\Http\Controllers\Api\OrganStateController;
use Illuminate\Support\Facades\Route;

Route::get('organs/state', OrganStateController::class)->name('organs.state');

Route::prefix('bloodstream')
    ->name('bloodstream.')
    ->group(function (): void {
        Route::get('contracts', ContractsController::class)->name('contracts.index');
        Route::get('subjects', SubjectsController::class)->name('subjects.index');
    });
