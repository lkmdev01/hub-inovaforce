<?php

use App\Http\Controllers\Internal\FinanceOperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/v1/finance')
    ->middleware(['internal.signature', 'throttle:60,1'])
    ->name('internal.finance.')
    ->group(function () {
        Route::post('customers', [FinanceOperationsController::class, 'storeCustomer'])->name('customers.store');
        Route::post('customers/{team:slug}/sync', [FinanceOperationsController::class, 'syncCustomer'])->name('customers.sync');
        Route::post('customers/{team:slug}/payments', [FinanceOperationsController::class, 'storePayment'])->name('payments.store');
        Route::post('customers/{team:slug}/subscriptions', [FinanceOperationsController::class, 'storeSubscription'])->name('subscriptions.store');
        Route::post('subscriptions/{subscription}/cancel', [FinanceOperationsController::class, 'cancelSubscription'])->name('subscriptions.cancel');
        Route::post('invoices/{invoice}/refund', [FinanceOperationsController::class, 'refundInvoice'])->name('invoices.refund');
        Route::patch('alerts/{alert}/resolve', [FinanceOperationsController::class, 'resolveAlert'])->name('alerts.resolve');
    });
