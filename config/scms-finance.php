<?php

/*
 * scms-finance. Publish with:  php artisan vendor:publish --tag=scms-finance-config
 */

return [

    'enabled' => env('SCMS_FINANCE_ENABLED', true),

    'currency' => env('SCMS_FINANCE_CURRENCY', 'BDT'),

    // Scale of every money column (DECIMAL(15, n)) and of all money arithmetic.
    'decimal_places' => 2,

    'pagination' => 20,

    'routes' => [
        'enabled' => true,
        'prefix' => 'finance',
        'name' => 'finance.',
        // The panel's own stack. nit-master's "authorization" middleware is not used: it only knows
        // controllers under App\ and Nit\. Every action is checked with the abilities below instead.
        'middleware' => ['web', 'auth'],
    ],

    'api' => [
        // Read-only JSON endpoints (accounts, balances, savings accounts, loans, dashboard).
        'enabled' => env('SCMS_FINANCE_API', false),
        'prefix' => 'api/finance',
        // nit-master's personal access tokens (Profile › API tokens).
        'middleware' => [\Nit\Master\Http\Middlewares\AuthenticateApiToken::class, 'throttle:60,1', \Illuminate\Routing\Middleware\SubstituteBindings::class],
    ],

    // Layout every screen extends; must yield "title" and "content" and have "css", "js" and "buttons" stacks.
    'layout' => 'nit::master',

    /*
    | Models of other packages. Nothing here is copied into finance tables.
    |
    | member: the app's member model (e.g. from scms-core). Until one is set, savings accounts
    | and loans cannot be opened. "member_columns" says how to show and search it.
    | financial_year: the package's own model unless another package provides one; it must have
    | starts_on, ends_on and status ('open' / 'closed') columns.
    */
    'models' => [
        'user' => null, // null = the auth provider's model
        'branch' => \Nit\Master\Models\Branch::class,
        'member' => env('SCMS_FINANCE_MEMBER_MODEL'),
        'financial_year' => \SCMS\Finance\Models\FinancialYear::class,
    ],

    'member_columns' => [
        'label' => 'name',            // shown in lists and selects
        'number' => 'member_no',      // shown before the name when set (null = none)
        'branch' => 'branch_id',      // a member may only use accounts of their own branch (null = not checked)
        'search' => ['name', 'member_no'],
    ],

    'tables' => [
        'branches' => 'branches',
        // When set and the table exists, member_id columns get a foreign key to it.
        'members' => env('SCMS_FINANCE_MEMBER_TABLE'),
    ],

    // Turn off when another package already creates the financial_years table.
    'migrate_financial_years' => true,

    /*
    | Transaction numbers: {prefix}-{year}-{number}. One counter per prefix and year,
    | or per prefix, year and branch when per_branch is on ({prefix}-{branch}-{year}-{number}).
    */
    'numbering' => [
        'pad' => 6,
        'per_branch' => false,
        'prefixes' => [
            'journal' => 'JV',
            'savings_deposit' => 'DEP',
            'savings_withdrawal' => 'WDR',
            'savings_interest' => 'INT',
            'savings_account' => 'SAV',
            'loan' => 'LOAN',
            'loan_disbursement' => 'DSB',
            'loan_repayment' => 'REP',
            'loan_write_off' => 'WOF',
            'income' => 'INC',
            'expense' => 'EXP',
            'transfer' => 'TRF',
            'reversal' => 'REV',
            'opening_balance' => 'OB',
            'year_close' => 'YC',
        ],
    ],

    /*
    | Chart of accounts codes the package posts to when a product or form does not say otherwise.
    | Codes, never ids: the seeder creates these, an install may point them elsewhere.
    */
    'accounts' => [
        'cash' => '1101',
        'bank' => '1201',
        'loan_receivable' => '1300',
        'member_savings' => '2100',
        'opening_balance_equity' => '3100',
        'accumulated_surplus' => '3200',
        'interest_income' => '4200',
        'penalty_income' => '4300',
        'fee_income' => '4400',
        'loan_write_off' => '5700',
        'savings_interest_expense' => '5800',
    ],

    'rules' => [
        // Refuse a payment that would take a cash drawer or bank account below zero.
        'prevent_negative_cash' => true,
        // Business days allowed in the past for a new transaction (null = any date in an open year).
        'backdate_days' => null,
        // Whoever entered a loan application may not approve it (super admins excepted).
        'loan_self_approval' => false,
        // Whoever wrote a draft journal, income or expense voucher or transfer may not post it
        // (super admins excepted). true lets one person write and post.
        'self_posting' => env('SCMS_FINANCE_SELF_POSTING', false),
        // Staff (not super admins) without any branch see and touch no branch. nit-master would let
        // them see every branch; false keeps that rule.
        'require_branch' => env('SCMS_FINANCE_REQUIRE_BRANCH', true),
        // Repayment order inside each installment.
        'repayment_order' => ['interest', 'principal'],
    ],

    'attachments' => [
        'disk' => env('SCMS_FINANCE_DISK', 'public'),
        'directory' => 'finance',
        'max_kb' => 4096,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    // Sidebar entry (nit-master's config('sidebar') format). false = none.
    'sidebar' => true,
];
