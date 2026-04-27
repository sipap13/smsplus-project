<?php

use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CdrController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FraudController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\ReclamationController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'smsplus-api',
        'ts' => now()->toIso8601String(),
    ]);
});

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFa']);
Route::post('/resend-2fa', [AuthController::class, 'resendCode']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth.api');

// Dashboard
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('auth.api');
Route::get('/dashboard/revenus', [DashboardController::class, 'revenus'])->middleware('auth.api');
Route::get('/dashboard/range', [MetaController::class, 'dashboardRange'])->middleware('auth.api');
Route::get('/dashboard/revenus-monthly', [DashboardController::class, 'revenusMonthly'])->middleware('auth.api');
Route::get('/dashboard/revenus-fournisseur', [DashboardController::class, 'revenusByFournisseur'])->middleware('auth.api');
Route::get('/dashboard/top-services', [DashboardController::class, 'topServices'])->middleware('auth.api');
Route::get('/dashboard/mmg-vs-occ', [DashboardController::class, 'mmgVsOcc'])->middleware('auth.api');

// Services : lecture pour tous les profils authentifiés (ex. dashboard) ; écriture réservée aux ADMIN
Route::get('/services', [ServiceController::class, 'index'])->middleware('auth.api');
Route::get('/services/{id}', [ServiceController::class, 'show'])->middleware('auth.api');
Route::post('/services', [ServiceController::class, 'store'])->middleware(['auth.api', 'role:ADMIN']);
Route::put('/services/{id}', [ServiceController::class, 'update'])->middleware(['auth.api', 'role:ADMIN']);
Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->middleware(['auth.api', 'role:ADMIN']);

// Réclamations
Route::get('/reclamations/{msisdn}', [ReclamationController::class, 'byMsisdn'])->middleware('auth.api');

// Users (Admin)
Route::get('/users', [UserController::class, 'index'])->middleware(['auth.api', 'role:ADMIN']);
Route::post('/users', [UserController::class, 'store'])->middleware(['auth.api', 'role:ADMIN']);
Route::put('/users/{id}', [UserController::class, 'update'])->middleware(['auth.api', 'role:ADMIN']);

// Alerts (ADMIN + ANALYSTE_OP)
Route::get('/alerts', [AlertController::class, 'index'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::post('/alerts', [AlertController::class, 'store'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::put('/alerts/{id}', [AlertController::class, 'update'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);

// Export
Route::get('/export/revenus.csv', [ExportController::class, 'revenusCsv'])->middleware('auth.api');
Route::get('/export/occ', [ExportController::class, 'exportOcc'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_BUSS']);
Route::get('/export/mmg', [ExportController::class, 'exportMmg'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::get('/export/services', [ExportController::class, 'exportServices'])->middleware('auth.api');
Route::get('/export/alerts', [ExportController::class, 'exportAlerts'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);

// CDR (pagination serveur, lecture seule)
Route::get('/cdr/occ', [CdrController::class, 'occ'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_BUSS']);
Route::get('/cdr/occ/filter-options', [CdrController::class, 'occFilterOptions'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_BUSS']);
Route::get('/cdr/mmg', [CdrController::class, 'mmg'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::get('/cdr/mmg/filter-options', [CdrController::class, 'mmgFilterOptions'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::get('/cdr/msisdn/{msisdn}', [CdrController::class, 'byMsisdn'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP'])->where('msisdn', '[^/]+');
Route::get('/cdr/msisdn/{msisdn}/timeline', [CdrController::class, 'timeline'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP'])->where('msisdn', '[^/]+');

// Fraud detection (usage élevé)
Route::get('/fraud/usage-high', [FraudController::class, 'usageHigh'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);
Route::post('/chatbot/analyze', [ChatbotController::class, 'analyze'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP,ANALYSTE_BUSS']);
Route::post('/ai/chat', [AiChatController::class, 'chat'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP,ANALYSTE_BUSS']);

// Prédictions IA
Route::get('/predictions/revenus', [PredictionController::class, 'revenus'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_BUSS']);

// Imports (ADMIN uniquement)
Route::post('/imports/upload', [ImportController::class, 'upload'])->middleware(['auth.api', 'role:ADMIN']);
Route::get('/imports/history', [ImportController::class, 'history'])->middleware(['auth.api', 'role:ADMIN']);
Route::get('/imports/{id}/status', [ImportController::class, 'status'])->middleware(['auth.api', 'role:ADMIN']);
Route::delete('/imports/{id}', [ImportController::class, 'destroy'])->middleware(['auth.api', 'role:ADMIN']);

// Notifications in-app
Route::prefix('notifications')->middleware('auth.api')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/count', [NotificationController::class, 'count']);
    Route::post('/{id}/lire', [NotificationController::class, 'lire']);
    Route::post('/lire-tout', [NotificationController::class, 'lireTout']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/vider', [NotificationController::class, 'vider']);
});

