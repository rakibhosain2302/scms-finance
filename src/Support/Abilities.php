<?php

namespace SCMS\Finance\Support;

/**
 * The finance abilities. They are added to nit-master's config('ipermissions'), so they appear
 * on Authorization › Role and can be given in config/rolepermission.php
 * ('Finance: Savings::finance_savings_deposit', or 'Finance: Savings::*').
 * Checked with Gate / @can like any other ability; super admins have all of them.
 */
final class Abilities
{
    public const DASHBOARD = 'finance_dashboard_view';

    public const ACCOUNT_VIEW = 'finance_account_view';
    public const ACCOUNT_MANAGE = 'finance_account_manage';
    public const YEAR_MANAGE = 'finance_year_manage';
    public const JOURNAL_VIEW = 'finance_journal_view';
    public const JOURNAL_CREATE = 'finance_journal_create';
    public const JOURNAL_POST = 'finance_journal_post';
    public const REVERSE = 'finance_transaction_reverse';

    public const CASHBANK_VIEW = 'finance_cashbank_view';
    public const CASHBANK_MANAGE = 'finance_cashbank_manage';
    public const TRANSFER_VIEW = 'finance_transfer_view';
    public const TRANSFER_CREATE = 'finance_transfer_create';
    public const TRANSFER_POST = 'finance_transfer_post';

    public const SAVINGS_VIEW = 'finance_savings_view';
    public const SAVINGS_MANAGE = 'finance_savings_manage';
    public const SAVINGS_DEPOSIT = 'finance_savings_deposit';
    public const SAVINGS_WITHDRAW = 'finance_savings_withdraw';

    public const LOAN_VIEW = 'finance_loan_view';
    public const LOAN_PRODUCT_MANAGE = 'finance_loan_product_manage';
    public const LOAN_CREATE = 'finance_loan_create';
    public const LOAN_APPROVE = 'finance_loan_approve';
    public const LOAN_DISBURSE = 'finance_loan_disburse';
    public const LOAN_REPAY = 'finance_loan_repay';

    public const INCOME_VIEW = 'finance_income_view';
    public const INCOME_CREATE = 'finance_income_create';
    public const INCOME_POST = 'finance_income_post';
    public const EXPENSE_VIEW = 'finance_expense_view';
    public const EXPENSE_CREATE = 'finance_expense_create';
    public const EXPENSE_POST = 'finance_expense_post';

    public const REPORT_VIEW = 'finance_report_view';
    public const REPORT_ALL_BRANCHES = 'finance_report_all_branches';

    /**
     * In nit-master's config/ipermissions.php format.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Finance: Accounting' => [
                'icon' => 'fa-book',
                self::DASHBOARD => 'View the finance dashboard',
                self::ACCOUNT_VIEW => 'View the chart of accounts',
                self::ACCOUNT_MANAGE => 'Add and change accounts',
                self::YEAR_MANAGE => 'Open and close financial years',
                self::JOURNAL_VIEW => 'View journal entries',
                self::JOURNAL_CREATE => 'Write journal entries',
                self::JOURNAL_POST => 'Post journal entries',
                self::REVERSE => 'Reverse posted transactions',
            ],
            'Finance: Cash & Bank' => [
                'icon' => 'fa-university',
                self::CASHBANK_VIEW => 'View cash and bank accounts',
                self::CASHBANK_MANAGE => 'Add and change cash and bank accounts',
                self::TRANSFER_VIEW => 'View transfers',
                self::TRANSFER_CREATE => 'Write transfers',
                self::TRANSFER_POST => 'Post transfers',
            ],
            'Finance: Savings' => [
                'icon' => 'fa-piggy-bank',
                self::SAVINGS_VIEW => 'View savings accounts and transactions',
                self::SAVINGS_MANAGE => 'Manage savings schemes and open or close accounts',
                self::SAVINGS_DEPOSIT => 'Take savings deposits',
                self::SAVINGS_WITHDRAW => 'Pay savings withdrawals',
            ],
            'Finance: Loans' => [
                'icon' => 'fa-hand-holding-usd',
                self::LOAN_VIEW => 'View loans',
                self::LOAN_PRODUCT_MANAGE => 'Manage loan products',
                self::LOAN_CREATE => 'Enter loan applications',
                self::LOAN_APPROVE => 'Approve or reject loan applications',
                self::LOAN_DISBURSE => 'Disburse loans',
                self::LOAN_REPAY => 'Take loan repayments',
            ],
            'Finance: Income & Expense' => [
                'icon' => 'fa-receipt',
                self::INCOME_VIEW => 'View income',
                self::INCOME_CREATE => 'Write income vouchers',
                self::INCOME_POST => 'Post income vouchers',
                self::EXPENSE_VIEW => 'View expenses',
                self::EXPENSE_CREATE => 'Write expense vouchers',
                self::EXPENSE_POST => 'Post expense vouchers',
            ],
            'Finance: Reports' => [
                'icon' => 'fa-chart-line',
                self::REPORT_VIEW => 'View finance reports',
                self::REPORT_ALL_BRANCHES => 'Finance reports across all branches',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return collect(self::groups())->flatMap(fn ($g) => array_keys($g))->reject(fn ($k) => $k === 'icon')->values()->all();
    }
}
