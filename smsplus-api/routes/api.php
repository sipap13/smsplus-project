<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ReclamationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\MetaController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login',  [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.api');
Route::get('/me',      [AuthController::class, 'me'])->middleware('auth.api');

// Dashboard
Route::get('/dashboard/stats',   [DashboardController::class, 'stats'])->middleware('auth.api');
Route::get('/dashboard/revenus', [DashboardController::class, 'revenus'])->middleware('auth.api');
Route::get('/dashboard/range',   [MetaController::class, 'dashboardRange'])->middleware('auth.api');

// Services
Route::apiResource('services', ServiceController::class)->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP,ANALYSTE_BUSS']);

// Réclamations
Route::get('/reclamations/{msisdn}', [ReclamationController::class, 'byMsisdn'])->middleware('auth.api');

// Users (Admin)
Route::get('/users',          [UserController::class, 'index'])->middleware(['auth.api', 'role:ADMIN']);
Route::post('/users',         [UserController::class, 'store'])->middleware(['auth.api', 'role:ADMIN']);
Route::put('/users/{id}',     [UserController::class, 'update'])->middleware(['auth.api', 'role:ADMIN']);

// Alerts
Route::get('/alerts',         [AlertController::class, 'index'])->middleware('auth.api');
Route::put('/alerts/{id}',    [AlertController::class, 'resolve'])->middleware(['auth.api', 'role:ADMIN,ANALYSTE_OP']);