<?php

namespace SCMS\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Support\FinanceModels;

/**
 * Sample set-up for trying the package: savings schemes, loan products and the current
 * July–June financial year. No transactions. Never run by itself:
 *     php artisan finance:install --demo
 */
class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChartOfAccountsSeeder::class);
        $code = fn (string $c) => Account::query()->where('code', $c)->value('id');

        $schemes = [
            ['DAILY', 'Daily Savings', 'daily', '10', null, '0', true, null],
            ['MONTHLY', 'Monthly Savings', 'monthly', '100', '50000', '100', true, 2],
            ['GENERAL', 'General Savings', 'flexible', '50', null, '0', true, null],
            ['SPECIAL', 'Special Savings', 'monthly', '500', null, '500', false, null],
        ];
        foreach ($schemes as [$c, $name, $frequency, $min, $max, $minimumBalance, $withdraw, $perMonth]) {
            SavingsScheme::query()->firstOrCreate(['code' => $c], [
                'name' => $name, 'frequency' => $frequency, 'min_deposit' => $min, 'max_deposit' => $max,
                'minimum_balance' => $minimumBalance, 'allow_withdrawal' => $withdraw, 'max_withdrawals_per_month' => $perMonth,
                'liability_account_id' => $code(config('scms-finance.accounts.member_savings')), 'is_active' => true,
            ]);
        }

        $products = [
            ['GENERAL', 'General Loan', 'flat', '12', 'monthly', 3, 24, '5000', '500000', '1'],
            ['MICRO', 'Micro Loan', 'declining', '15', 'weekly', 12, 52, '1000', '50000', '0'],
            ['QARD', 'Interest-free Loan', 'none', '0', 'monthly', 1, 12, '500', '20000', '0'],
        ];
        foreach ($products as [$c, $name, $method, $rate, $frequency, $minN, $maxN, $min, $max, $fee]) {
            LoanProduct::query()->firstOrCreate(['code' => $c], [
                'name' => $name, 'interest_method' => $method, 'interest_rate' => $rate, 'repayment_frequency' => $frequency,
                'min_installments' => $minN, 'max_installments' => $maxN, 'min_amount' => $min, 'max_amount' => $max,
                'processing_fee_rate' => $fee, 'grace_days' => 3,
                'receivable_account_id' => $code(config('scms-finance.accounts.loan_receivable')),
                'interest_income_account_id' => $code(config('scms-finance.accounts.interest_income')),
                'penalty_income_account_id' => $code(config('scms-finance.accounts.penalty_income')),
                'fee_income_account_id' => $code(config('scms-finance.accounts.fee_income')),
                'is_active' => true,
            ]);
        }

        $start = today()->month >= 7 ? Carbon::create(today()->year, 7, 1) : Carbon::create(today()->year - 1, 7, 1);
        $model = FinanceModels::financialYear();
        if (! $model::query()->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->exists()) {
            $year = new $model(['name' => $start->year.'-'.substr((string) ($start->year + 1), 2), 'starts_on' => $start, 'ends_on' => $start->copy()->addYear()->subDay()]);
            $year->forceFill(['status' => YearStatus::Open->value])->save();
        }
    }
}
