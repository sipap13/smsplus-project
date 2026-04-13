<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ReclamationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SosController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\CdrController;
use App\Http\Controllers\Api\FraudController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'smsplus-api',
        'ts' => now()->toIso8601String(),
    ]);
});

// Auth
Route::post('/login',  [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
Route::get('/me',      [AuthController::class, 'me'])->middleware('auth.api');

// Dashboard
Route::get('/dashboard/stats',   [DashboardController::class, 'stats'])->middleware('auth.api');
Route::get('/dashboard/revenus', [DashboardController::class, 'revenus'])->middleware('auth.api');
Route::get('/dashboard/range',   [MetaController::class, 'dashboardRange'])->middleware('auth.api');
Route::get('/dashboard/revenus-monthly', [DashboardController::class, 'revenusMonthly'])->middleware('auth.api');
Route::get('/dashboard/revenus-fournisseur', [DashboardController::class, 'revenusByFournisseur'])->middleware('auth.api');
Route::get('/dashboard/top-services', [DashboardController::class, 'topServices'])->middleware('auth.api');
Route::get('/dashboard/mmg-vs-occ', [DashboardController::class, 'mmgVsOcc'])->middleware('auth.api');

// Services : lecture pour tous les profils authentifiés (ex. dashboard) ; écriture réservée aux ADMIN
Route::get('/services',     [ServiceController::class, 'index'])->middleware('auth.api');
Route::get('/services/{id}', [ServiceController::class, 'show'])->middleware('auth.api');
Route::post('/services',    [ServiceController::class, 'store'])->middleware(['auth.api', 'role:ADMIN']);
Route::put('/services/{id}', [ServiceController::class, 'update'])->middleware(['auth.api', 'role:ADMIN']);
Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->middleware(['auth.api', 'role:ADMIN']);

// Réclamations
Route::get('/reclamations/{msisdn}', [ReclamationController::class, 'byMsisdn'])->middleware('auth.api');

// Users (Admin)
Route::get('/users',      [UserController::class, 'index'])->middleware(['auth.api', 'role:ADMIN']);
Route::post('/users',     [UserController::class, 'store'])->middleware(['auth.api', 'role:ADMIN']);
Route::put('/users/{id}', [UserController::class, 'update'])->middleware(['auth.api', 'role:ADMIN']);

// Alerts (ADMIN + ANALYSTE_OP)
Route::get('/alerts',     [AlertController::class, 'index'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::post('/alerts',    [AlertController::class, 'store'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::put('/alerts/{id}', [AlertController::class, 'update'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);

// SOS Solde & Data
Route::get('/sos/kpis', [SosController::class, 'kpis'])->middleware('auth.api');
Route::get('/sos/bad-debts', [SosController::class, 'badDebts'])->middleware('auth.api');

// Export
Route::get('/export/revenus.csv', [ExportController::class, 'revenusCsv'])->middleware('auth.api');

// CDR (pagination serveur, lecture seule)
Route::get('/cdr/occ', [CdrController::class, 'occ'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_BUSS']);
Route::get('/cdr/occ/filter-options', [CdrController::class, 'occFilterOptions'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_BUSS']);
Route::get('/cdr/mmg', [CdrController::class, 'mmg'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::get('/cdr/mmg/filter-options', [CdrController::class, 'mmgFilterOptions'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::get('/cdr/msisdn/{msisdn}', [CdrController::class, 'byMsisdn'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP'])->where('msisdn', '[^/]+');

// Fraud detection (usage élevé)
Route::get('/fraud/usage-high', [FraudController::class, 'usageHigh'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);