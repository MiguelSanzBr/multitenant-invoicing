<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

// rotas explícitas (sem apiResource) pra bater 1:1 com o openapi.yaml, só PUT e sem PATCH
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::controller(ClientController::class)->group(function () {
            Route::get('clients', 'index')->name('clients.index');
            Route::post('clients', 'store')->name('clients.store');
            Route::get('clients/{client}', 'show')->name('clients.show');
            Route::put('clients/{client}', 'update')->name('clients.update');
            Route::delete('clients/{client}', 'destroy')->name('clients.destroy');
        });

        Route::controller(InvoiceController::class)->group(function () {
            Route::get('invoices', 'index')->name('invoices.index');
            Route::post('invoices', 'store')->name('invoices.store');
            Route::get('invoices/{invoice}', 'show')->name('invoices.show');
            Route::put('invoices/{invoice}', 'update')->name('invoices.update');
            Route::delete('invoices/{invoice}', 'destroy')->name('invoices.destroy');
            Route::post('invoices/{invoice}/pay', 'pay')->name('invoices.pay');
        });
    });
});
