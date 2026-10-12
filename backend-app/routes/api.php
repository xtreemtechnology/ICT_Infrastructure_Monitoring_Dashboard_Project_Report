<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware('auth')->group(function (): void {
        Route::get('/auth/user', [AuthController::class, 'user']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/reports', [DashboardController::class, 'report']);
        Route::get('/device-groups', [DashboardController::class, 'groups']);
        Route::post('/device-groups', [DashboardController::class, 'storeGroup'])->middleware('can:manage-team');
        Route::get('/team', [DashboardController::class, 'users']);
        Route::post('/team', [DashboardController::class, 'storeUser'])->middleware('can:manage-team');
        Route::post('/devices/check', [DashboardController::class, 'checkDevices']);
        Route::post('/devices', [DashboardController::class, 'storeDevice']);
        Route::patch('/devices/{device}', [DashboardController::class, 'updateDevice'])->middleware('can:manage-team');
        Route::delete('/devices/{device}', [DashboardController::class, 'destroyDevice'])->middleware('can:manage-team');
        Route::patch('/alerts/{alert}/acknowledge', [DashboardController::class, 'acknowledge']);
    });
});
