<?php

use App\Http\Controllers\SurfaceTreeNodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/surface-tree/nodes', [SurfaceTreeNodeController::class, 'roots'])
    ->name('surface-tree.nodes.roots');

Route::get('/surface-tree/nodes/{nodeKey}/children', [SurfaceTreeNodeController::class, 'children'])
    ->where('nodeKey', '.*')
    ->name('surface-tree.nodes.children');
