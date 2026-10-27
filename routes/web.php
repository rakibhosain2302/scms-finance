<?php

use Illuminate\Support\Facades\Route;
use SCMS\Finance\Http\Controllers as C;

/*
 * Loaded by SCMSFinanceServiceProvider inside prefix "finance", names "finance.", middleware
 * from config('scms-finance.routes'). Every action checks its ability or policy itself.
 */

Route::get('/', C\DashboardController::class)->name('dashboard');

// Accounting
Route::resource('accounts', C\AccountController::class);
Route::resource('financial-years', C\FinancialYearController::class)->only(['index', 'create', 'store'])->parameters(['financial-years' => 'financialYear']);
Route::post('financial-years/{financialYear}/close', [C\FinancialYearController::class, 'close'])->name('financial-years.close');
Route::resource('journals', C\JournalController::class)->except('destroy')->parameters(['journals' => 'journalEntry']);
Route::post('journals/{journalEntry}/post', [C\JournalController::class, 'post'])->name('journals.post');
Route::post('journals/{journalEntry}/cancel', [C\JournalController::class, 'cancel'])->name('journals.cancel');
Route::post('journals/{journalEntry}/reverse', [C\JournalController::class, 'reverse'])->name('journals.reverse');

// Cash and bank
Route::resource('cash-bank', C\CashBankAccountController::class)->except('destroy')->parameters(['cash-bank' => 'cashBankAccount']);
Route::resource('transfers', C\TransferController::class)->except('destroy');
Route::post('transfers/{transfer}/post', [C\TransferController::class, 'post'])->name('transfers.post');
Route::post('transfers/{transfer}/cancel', [C\TransferController::class, 'cancel'])->name('transfers.cancel');
Route::post('transfers/{transfer}/reverse', [C\TransferController::class, 'reverse'])->name('transfers.reverse');

// Savings
Route::resource('savings-schemes', C\SavingsSchemeController::class)->except(['show', 'destroy'])->parameters(['savings-schemes' => 'savingsScheme']);
Route::resource('savings-accounts', C\SavingsAccountController::class)->only(['index', 'create', 'store', 'show'])->parameters(['savings-accounts' => 'savingsAccount']);
Route::post('savings-accounts/{savingsAccount}/status', [C\SavingsAccountController::class, 'status'])->name('savings-accounts.status');
Route::resource('savings-transactions', C\SavingsTransactionController::class)->only(['index', 'create', 'store', 'show'])->parameters(['savings-transactions' => 'savingsTransaction']);
Route::post('savings-transactions/{savingsTransaction}/reverse', [C\SavingsTransactionController::class, 'reverse'])->name('savings-transactions.reverse');

// Loans
Route::resource('loan-products', C\LoanProductController::class)->except(['show', 'destroy'])->parameters(['loan-products' => 'loanProduct']);
Route::get('loans/preview', [C\LoanController::class, 'preview'])->name('loans.preview');
Route::resource('loans', C\LoanController::class)->only(['index', 'create', 'store', 'show']);
Route::post('loans/{loan}/approve', [C\LoanController::class, 'approve'])->name('loans.approve');
Route::post('loans/{loan}/reject', [C\LoanController::class, 'reject'])->name('loans.reject');
Route::post('loans/{loan}/cancel', [C\LoanController::class, 'cancel'])->name('loans.cancel');
Route::get('loans/{loan}/disburse', [C\LoanController::class, 'disburseForm'])->name('loans.disburse.create');
Route::post('loans/{loan}/disburse', [C\LoanController::class, 'disburse'])->name('loans.disburse');
Route::post('loans/{loan}/reverse-disbursement', [C\LoanController::class, 'reverseDisbursement'])->name('loans.reverse-disbursement');
Route::get('loans/{loan}/repayments/create', [C\LoanRepaymentController::class, 'create'])->name('loan-repayments.create');
Route::post('loans/{loan}/repayments', [C\LoanRepaymentController::class, 'store'])->name('loan-repayments.store');
Route::get('loan-repayments', [C\LoanRepaymentController::class, 'index'])->name('loan-repayments.index');
Route::get('loan-repayments/{loanRepayment}', [C\LoanRepaymentController::class, 'show'])->name('loan-repayments.show');
Route::post('loan-repayments/{loanRepayment}/reverse', [C\LoanRepaymentController::class, 'reverse'])->name('loan-repayments.reverse');

// Income and expense vouchers
foreach (['incomes' => C\IncomeController::class, 'expenses' => C\ExpenseController::class] as $name => $controller) {
    Route::resource($name, $controller)->except('destroy')->parameters([$name => 'voucher']);
    Route::post("{$name}/{voucher}/post", [$controller, 'post'])->name("{$name}.post");
    Route::post("{$name}/{voucher}/cancel", [$controller, 'cancel'])->name("{$name}.cancel");
    Route::post("{$name}/{voucher}/reverse", [$controller, 'reverse'])->name("{$name}.reverse");
}

// Reports
Route::prefix('reports')->name('reports.')->controller(C\ReportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('activity', 'activity')->name('activity');
    Route::get('cashbook', 'cashbook')->name('cashbook');
    Route::get('ledger', 'ledger')->name('ledger');
    Route::get('trial-balance', 'trialBalance')->name('trial-balance');
    Route::get('income-statement', 'incomeStatement')->name('income-statement');
    Route::get('balance-sheet', 'balanceSheet')->name('balance-sheet');
    Route::get('loans', 'loans')->name('loans');
    Route::get('savings', 'savings')->name('savings');
    Route::get('branches', 'branches')->name('branches');
});
