<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ReclamationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AlertController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login',  [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/me',      [AuthController::class, 'me']);

// Dashboard
Route::get('/dashboard/stats',   [DashboardController::class, 'stats']);
Route::get('/dashboard/revenus', [DashboardController::class, 'revenus']);

// Services
Route::apiResource('services', ServiceController::class);

// Réclamations
Route::get('/reclamations/{msisdn}', [ReclamationController::class, 'byMsisdn']);

// Users (Admin)
Route::get('/users',          [UserController::class, 'index']);
Route::post('/users',         [UserController::class, 'store']);
Route::put('/users/{id}',     [UserController::class, 'update']);

// Alerts
Route::get('/alerts',         [AlertController::class, 'index']);
Route::put('/alerts/{id}',    [AlertController::class, 'resolve']);