<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PropertyOfferController;

Route::middleware('auth:sanctum')->get('/user', function () {
    return auth()->user();
});

Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{import}', [ImportController::class, 'show']);
Route::get('/properties/cheapest-offers', [PropertyOfferController::class, 'cheapestOffers']);
