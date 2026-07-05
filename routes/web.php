<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/surface-tree', function () {
    return view('surface-tree.index');
})->name('surface-tree.index');
