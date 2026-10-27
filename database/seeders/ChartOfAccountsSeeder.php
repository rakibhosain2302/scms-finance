<?php

namespace SCMS\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use SCMS\Finance\Models\Account;

/**
 * A starting chart of accounts for a savings and credit cooperative. Safe to run again: existing
 * codes are left alone. Accounts named in config('scms-finance.accounts') are marked "system".
 */
class ChartOfAccountsSeeder extends Seeder
{
    /** [code, name, type, subtype, postable, parent code] */
    public const ACCOUNTS = [
        ['1000', 'Assets', 'asset', 'general', false, null],
        ['1100', 'Cash', 'asset', 'cash', false, '1000'],
        ['1101', 'Cash in Hand', 'asset', 'cash', true, '1100'],
        ['1200', 'Bank', 'asset', 'bank', false, '1000'],
        ['1201', 'Bank Accounts', 'asset', 'bank', true, '1200'],
        ['1300', 'Loans to Members', 'asset', 'loan_receivable', true, '1000'],
        ['1400', 'Other Assets', 'asset', 'general', false, '1000'],
        ['1401', 'Advances', 'asset', 'general', true, '1400'],
        ['1402', 'Furniture and Equipment', 'asset', 'general', true, '1400'],

        ['2000', 'Liabilities', 'liability', 'general', false, null],
        ['2100', 'Member Savings', 'liability', 'member_savings', true, '2000'],
        ['2200', 'Other Liabilities', 'liability', 'general', false, '2000'],
        ['2201', 'Payables', 'liability', 'general', true, '2200'],

        ['3000', 'Equity', 'equity', 'equity', false, null],
        ['3100', 'Opening Balance Equity', 'equity', 'equity', true, '3000'],
        ['3200', 'Accumulated Surplus', 'equity', 'equity', true, '3000'],
        ['3300', 'Share Capital', 'equity', 'equity', true, '3000'],

        ['4000', 'Income', 'income', 'general', false, null],
        ['4100', 'Service Income', 'income', 'general', true, '4000'],
        ['4200', 'Interest Income', 'income', 'interest_income', true, '4000'],
        ['4300', 'Penalty Income', 'income', 'penalty_income', true, '4000'],
        ['4400', 'Loan Processing Fees', 'income', 'fee_income', true, '4000'],
        ['4500', 'Admission Fees', 'income', 'fee_income', true, '4000'],
        ['4600', 'Registration Fees', 'income', 'fee_income', true, '4000'],
        ['4900', 'Other Income', 'income', 'general', true, '4000'],

        ['5000', 'Expenses', 'expense', 'general', false, null],
        ['5100', 'Office Expense', 'expense', 'general', true, '5000'],
        ['5200', 'Salary', 'expense', 'general', true, '5000'],
        ['5300', 'Rent', 'expense', 'general', true, '5000'],
        ['5400', 'Electricity', 'expense', 'general', true, '5000'],
        ['5500', 'Transport', 'expense', 'general', true, '5000'],
        ['5600', 'Maintenance', 'expense', 'general', true, '5000'],
        ['5700', 'Loan Loss (Write-off)', 'expense', 'general', true, '5000'],
        ['5800', 'Interest on Savings', 'expense', 'general', true, '5000'],
        ['5900', 'Other Expense', 'expense', 'general', true, '5000'],
    ];

    public function run(): void
    {
        $system = array_values((array) config('scms-finance.accounts', []));
        $ids = Account::query()->pluck('id', 'code')->all();

        foreach (self::ACCOUNTS as [$code, $name, $type, $subtype, $postable, $parent]) {
            if (isset($ids[$code])) {
                continue;
            }

            $account = new Account(['code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype, 'is_postable' => $postable, 'is_active' => true]);
            $account->forceFill(['parent_id' => $parent ? ($ids[$parent] ?? null) : null, 'is_system' => in_array($code, $system, true)])->save();
            $ids[$code] = $account->id;
        }
    }
}
