<?php

use App\Http\Controllers\Api\Bloodstream\ContractsController;
use App\Http\Controllers\Api\Bloodstream\SubjectsController;
use Illuminate\Support\Facades\Route;

Route::prefix('bloodstream')
    ->name('bloodstream.')
    ->group(function (): void {
        Route::get('contracts', ContractsController::class)->name('contracts.index');
        Route::get('subjects', SubjectsController::class)->name('subjects.index');
    });
