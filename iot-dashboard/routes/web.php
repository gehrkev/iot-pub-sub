<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Rota principal - Dashboard
Route::get('/', [DashboardController::class, 'index']);
Route::get('/dashboard', [DashboardController::class, 'index']);

// API Routes para o Dashboard
Route::prefix('dashboard')->group(function () {
    Route::get('/sensor-data', [DashboardController::class, 'getSensorData'])
        ->name('dashboard.sensor-data');

    Route::get('/historical-data', [DashboardController::class, 'getHistoricalData'])
        ->name('dashboard.historical-data');

    Route::get('/system-status', [DashboardController::class, 'getSystemStatus'])
        ->name('dashboard.system-status');
});

// Rotas de fallback para desenvolvimento
Route::fallback(function () {
    return view('dashboard.index');
});
