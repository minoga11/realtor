<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PropertyOfferController;
use App\Http\Controllers\ReservationController;

Route::middleware('auth:sanctum')->get('/user', function () {
    return auth()->user();
});

Route::get('/imports', [ImportController::class, 'index']);
Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{import}', [ImportController::class, 'show']);
Route::get('/properties', [PropertyOfferController::class, 'index']);
Route::get('/properties/cheapest-offers', [PropertyOfferController::class, 'cheapestOffers']);
Route::post('/reservations', [ReservationController::class, 'store']);
Route::post('/offers/{offer}/reservations', [ReservationController::class, 'store']);
