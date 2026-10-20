<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

// rotas explícitas (sem apiResource) pra bater 1:1 com o openapi.yaml, só PUT e sem PATCH
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:api-token')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('tokens', [TokenController::class, 'index'])->name('tokens.index');
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        // leitura aceita token de leitura ou de escrita, escrita só com a ability de escrita
        Route::controller(ClientController::class)->group(function () {
            Route::middleware('ability:clients:read,clients:write')->group(function () {
                Route::get('clients', 'index')->name('clients.index');
                Route::get('clients/{client}', 'show')->name('clients.show');
            });
            Route::middleware('abilities:clients:write')->group(function () {
                Route::post('clients', 'store')->name('clients.store');
                Route::put('clients/{client}', 'update')->name('clients.update');
                Route::delete('clients/{client}', 'destroy')->name('clients.destroy');
            });
        });

        Route::controller(InvoiceController::class)->group(function () {
            Route::middleware('ability:invoices:read,invoices:write')->group(function () {
                Route::get('invoices', 'index')->name('invoices.index');
                Route::get('invoices/{invoice}', 'show')->name('invoices.show');
            });
            Route::middleware('abilities:invoices:write')->group(function () {
                Route::post('invoices', 'store')->name('invoices.store');
                Route::put('invoices/{invoice}', 'update')->name('invoices.update');
                Route::delete('invoices/{invoice}', 'destroy')->name('invoices.destroy');
                Route::post('invoices/{invoice}/pay', 'pay')->name('invoices.pay');
            });
        });
    });
});
