<?php

use SCMS\Finance\Support\Abilities as A;

/*
 * Finance menu, added to nit-master's config('sidebar') under the key "finance".
 * On finance pages nit-master shows only this module's menu; the first item leads back.
 * To change it, define 'finance' in the app's config/sidebar.php (this file is then skipped).
 */
return [
    ['sl' => 0, 'title' => 'Main dashboard', 'icon' => 'fas fa-th-large', 'url' => '/', 'dashboard' => true],
    ['sl' => 1, 'header' => 'Finance', 'permission' => A::all()],
    ['sl' => 1.1, 'title' => 'Finance dashboard', 'icon' => 'fas fa-chart-pie', 'route' => 'finance.dashboard', 'permission' => A::DASHBOARD],
    ['sl' => 2, 'title' => 'Savings', 'icon' => 'fas fa-piggy-bank', 'children' => [
        ['title' => 'Savings accounts', 'route' => 'finance.savings-accounts.index', 'permission' => A::SAVINGS_VIEW],
        ['title' => 'Deposits & withdrawals', 'route' => 'finance.savings-transactions.index', 'permission' => A::SAVINGS_VIEW],
        ['title' => 'Savings schemes', 'route' => 'finance.savings-schemes.index', 'permission' => A::SAVINGS_MANAGE],
        ['title' => 'Interest on savings', 'route' => 'finance.savings-interest.create', 'permission' => A::SAVINGS_INTEREST],
        ['title' => 'Dividend on shares', 'route' => 'finance.dividends.create', 'permission' => A::DIVIDEND_POST],
    ]],
    ['sl' => 2.5, 'title' => 'Share capital', 'icon' => 'fas fa-certificate', 'route' => 'finance.shares.index', 'permission' => A::SHARE_VIEW],
    ['sl' => 3, 'title' => 'Loans', 'icon' => 'fas fa-hand-holding-usd', 'children' => [
        ['title' => 'Loans', 'route' => 'finance.loans.index', 'permission' => A::LOAN_VIEW],
        ['title' => 'Bring in running loans', 'route' => 'finance.loans.import', 'permission' => A::LOAN_IMPORT],
        ['title' => 'Repayments', 'route' => 'finance.loan-repayments.index', 'permission' => A::LOAN_VIEW],
        ['title' => 'Loan products', 'route' => 'finance.loan-products.index', 'permission' => A::LOAN_PRODUCT_MANAGE],
    ]],
    ['sl' => 4, 'title' => 'Income & expense', 'icon' => 'fas fa-receipt', 'children' => [
        ['title' => 'Income', 'route' => 'finance.incomes.index', 'permission' => A::INCOME_VIEW],
        ['title' => 'Expenses', 'route' => 'finance.expenses.index', 'permission' => A::EXPENSE_VIEW],
    ]],
    ['sl' => 5, 'title' => 'Cash & bank', 'icon' => 'fas fa-university', 'children' => [
        ['title' => 'Cash & bank accounts', 'route' => 'finance.cash-bank.index', 'permission' => A::CASHBANK_VIEW],
        ['title' => 'Bank reconciliation', 'route' => 'finance.reconciliations.index', 'permission' => A::CASHBANK_RECONCILE],
        ['title' => 'Transfers', 'route' => 'finance.transfers.index', 'permission' => A::TRANSFER_VIEW],
    ]],
    ['sl' => 6, 'title' => 'Accounting', 'icon' => 'fas fa-book', 'children' => [
        ['title' => 'Chart of accounts', 'route' => 'finance.accounts.index', 'permission' => A::ACCOUNT_VIEW],
        ['title' => 'Journal', 'route' => 'finance.journals.index', 'permission' => A::JOURNAL_VIEW],
        ['title' => 'Financial years', 'route' => 'finance.financial-years.index', 'permission' => A::YEAR_MANAGE],
    ]],
    ['sl' => 7, 'title' => 'Reports', 'icon' => 'fas fa-chart-line', 'route' => 'finance.reports.index', 'permission' => A::REPORT_VIEW],
];
