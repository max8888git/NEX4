<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'show']);
Route::get('/{path}', [PageController::class, 'show'])->where('path', '.*');
