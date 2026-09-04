<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;

Route::middleware('auth:sanctum')->get('/user', function () {
    return auth()->user();
});
