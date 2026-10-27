<?php

use Illuminate\Support\Facades\Route;
use SCMS\Finance\Http\Controllers\Api\FinanceApiController;

/*
 * Read-only JSON (scms-finance.api.enabled). Prefix "api/finance", names "finance.api.".
 */

Route::get('dashboard', [FinanceApiController::class, 'dashboard'])->name('dashboard');
Route::get('accounts', [FinanceApiController::class, 'accounts'])->name('accounts');
Route::get('cash-bank', [FinanceApiController::class, 'cashBank'])->name('cash-bank');
Route::get('savings-accounts', [FinanceApiController::class, 'savingsAccounts'])->name('savings-accounts');
Route::get('savings-accounts/{savingsAccount}', [FinanceApiController::class, 'savingsAccount'])->name('savings-accounts.show');
Route::get('loans', [FinanceApiController::class, 'loans'])->name('loans');
Route::get('loans/{loan}', [FinanceApiController::class, 'loan'])->name('loans.show');
