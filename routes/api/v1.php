<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\SalesReport\DownloadReportController;
use App\Http\Controllers\SalesReport\GenerateReportController;
use App\Http\Controllers\SalesReport\ReportStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
    Route::post('forgot-password', ForgotPasswordController::class);
    Route::post('reset-password', ResetPasswordController::class);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', LogoutController::class);
        Route::get('user', UserController::class);
    });
});

Route::middleware('auth:sanctum')->prefix('reports')->group(function (): void {
    Route::post('sales', GenerateReportController::class);
    Route::get('sales/{report}', ReportStatusController::class);
    Route::get('sales/{report}/download', DownloadReportController::class);
});
