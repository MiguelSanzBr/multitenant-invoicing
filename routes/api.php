<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    });
});
