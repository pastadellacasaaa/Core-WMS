<?php

use App\Http\Controllers\AutoRouteController;
use App\Http\Controllers\ForecastingController;
use App\Http\Controllers\SlottingController;
use App\Http\Controllers\WavePlanningController;
use Illuminate\Support\Facades\Route;

// The public test console only needs the field-based Agent Manager entry point.
Route::get('/', [AutoRouteController::class, 'show'])->name('auto-route');
Route::post('/', [AutoRouteController::class, 'send']);

Route::get('/wave-planning', [WavePlanningController::class, 'show'])->name('wave-planning');
Route::post('/wave-planning', [WavePlanningController::class, 'plan']);

Route::get('/slotting', [SlottingController::class, 'show'])->name('slotting');
Route::post('/slotting', [SlottingController::class, 'recommend']);

Route::get('/forecasting', [ForecastingController::class, 'show'])->name('forecasting');
Route::post('/forecasting', [ForecastingController::class, 'forecast']);
