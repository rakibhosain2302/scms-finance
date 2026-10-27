<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Savings interest runs, loan penalty rules (tracked per installment) and loan write-off.
 * Also adds the two expense accounts they post to when the default chart is in use.
 */
return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::table('finance_loan_products', function (Blueprint $table) {
            // none | fixed (amount per overdue installment) | percent (of the installment still unpaid).
            $table->string('penalty_type', 20)->default('none')->after('grace_days');
            $table->decimal('penalty_value', 15, 4)->default(0)->after('penalty_type');
        });

        Schema::table('finance_loan_installments', function (Blueprint $table) use ($scale) {
            $table->decimal('penalty_charged', 15, $scale)->default(0)->after('interest_paid');
        });

        Schema::table('finance_loan_repayment_allocations', function (Blueprint $table) use ($scale) {
            $table->decimal('penalty', 15, $scale)->default(0)->after('interest');
        });

        Schema::table('finance_loans', function (Blueprint $table) {
            $table->date('written_off_on')->nullable()->after('closed_on');
            $table->unsignedBigInteger('written_off_by')->nullable()->after('written_off_on');
            $table->foreignId('write_off_entry_id')->nullable()->unique()->after('written_off_by')->constrained('finance_journal_entries')->restrictOnDelete();
            $table->string('write_off_reason', 500)->nullable()->after('write_off_entry_id');
        });

        Schema::create('finance_savings_interest_runs', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('savings_scheme_id')->constrained('finance_savings_schemes')->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->date('posting_date');
            $table->decimal('rate', 8, 4);
            $table->decimal('total', 15, $scale)->default(0);
            $table->unsignedInteger('accounts_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['savings_scheme_id', 'branch_id', 'period_from'], 'finance_interest_runs_scheme_branch_from_index');
            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });

        Schema::table('finance_savings_transactions', function (Blueprint $table) {
            // Interest moves no cash.
            $table->unsignedBigInteger('cash_bank_account_id')->nullable()->change();
            $table->foreignId('savings_interest_run_id')->nullable()->after('cash_bank_account_id')->constrained('finance_savings_interest_runs')->restrictOnDelete();
        });

        $this->addAccounts();
    }

    public function down(): void
    {
        Schema::table('finance_savings_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('savings_interest_run_id');
        });
        Schema::dropIfExists('finance_savings_interest_runs');
        Schema::table('finance_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('write_off_entry_id');
            $table->dropColumn(['written_off_on', 'written_off_by', 'write_off_reason']);
        });
        Schema::table('finance_loan_repayment_allocations', fn (Blueprint $table) => $table->dropColumn('penalty'));
        Schema::table('finance_loan_installments', fn (Blueprint $table) => $table->dropColumn('penalty_charged'));
        Schema::table('finance_loan_products', fn (Blueprint $table) => $table->dropColumn(['penalty_type', 'penalty_value']));
    }

    /** Installs that ran the seeder before these accounts existed get them under "Expenses" (5000). */
    private function addAccounts(): void
    {
        $parent = DB::table('finance_accounts')->where('code', '5000')->value('id');
        if (! $parent) {
            return; // no default chart: the seeder adds them when it runs
        }

        foreach ([['5700', 'Loan Loss (Write-off)'], ['5800', 'Interest on Savings']] as [$code, $name]) {
            if (! DB::table('finance_accounts')->where('code', $code)->exists()) {
                DB::table('finance_accounts')->insert([
                    'code' => $code, 'name' => $name, 'type' => 'expense', 'subtype' => 'general', 'parent_id' => $parent,
                    'is_postable' => true, 'is_active' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
};
