<?php

use App\Http\Controllers\Admin\AdminAutomationController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminCustomerGroupController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\BillingCustomerController;
use App\Http\Controllers\BillingPortalController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('webhooks/asaas', AsaasWebhookController::class)->name('webhooks.asaas');

Route::prefix('admin')->middleware(['auth', 'verified', 'admin'])->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('clientes', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::post('clientes', [AdminCustomerController::class, 'store'])->name('customers.store');
    Route::get('clientes/{team}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::post('clientes/{team}/sincronizar', [AdminCustomerController::class, 'sync'])->name('customers.sync');
    Route::patch('clientes/{team}/grupo', [AdminCustomerController::class, 'assignGroup'])->name('customers.group');
    Route::post('grupos-de-clientes', [AdminCustomerGroupController::class, 'store'])->name('customer-groups.store');
    Route::delete('grupos-de-clientes/{group}', [AdminCustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');
    Route::get('assinaturas', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('assinaturas/{subscription}/cancelar', [AdminSubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::get('produtos', [AdminProductController::class, 'index'])->name('products.index');
    Route::post('produtos', [AdminProductController::class, 'store'])->name('products.store');
    Route::put('produtos/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::post('produtos/{product}/planos', [AdminProductController::class, 'storePlan'])->name('plans.store');
    Route::put('planos/{plan}', [AdminProductController::class, 'updatePlan'])->name('plans.update');
    Route::get('automacoes', [AdminAutomationController::class, 'index'])->name('automations.index');
    Route::patch('automacoes/alertas/{alert}/resolver', [AdminAutomationController::class, 'resolve'])->name('automations.resolve');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', [BillingPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('assinaturas', [BillingPortalController::class, 'subscriptions'])->name('subscriptions.index');
        Route::post('assinaturas/contratar/{plan}', [SubscriptionCheckoutController::class, 'store'])->name('subscriptions.store');
        Route::patch('assinaturas/{subscription}', [BillingPortalController::class, 'changePlan'])->name('subscriptions.update');
        Route::post('assinaturas/{subscription}/alternar', [BillingPortalController::class, 'toggleSubscription'])->name('subscriptions.toggle');
        Route::post('assinaturas/{subscription}/faturas', [BillingPortalController::class, 'issueInvoice'])->name('invoices.issue');
        Route::get('faturas', [BillingPortalController::class, 'invoices'])->name('invoices.index');
        Route::get('faturas/{invoice}', [BillingPortalController::class, 'invoice'])->name('invoices.show');
        Route::get('produtos', [BillingPortalController::class, 'products'])->name('products.index');
        Route::get('cliente', [BillingCustomerController::class, 'show'])->name('customer.show');
        Route::put('cliente', [BillingCustomerController::class, 'update'])->name('customer.update');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
