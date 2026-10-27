<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $members = config('scms-finance.tables.members');
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::create('finance_savings_schemes', function (Blueprint $table) use ($scale) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('frequency', 20)->default('flexible');
            $table->decimal('min_deposit', 15, $scale)->default(0);
            $table->decimal('max_deposit', 15, $scale)->nullable();
            // The balance that must stay in the account after a withdrawal.
            $table->decimal('minimum_balance', 15, $scale)->default(0);
            $table->boolean('allow_withdrawal')->default(true);
            $table->unsignedSmallInteger('max_withdrawals_per_month')->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->foreignId('liability_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('finance_savings_accounts', function (Blueprint $table) use ($branches, $members, $scale) {
            $table->id();
            $table->string('account_number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('member_id')->index();
            $table->foreignId('savings_scheme_id')->constrained('finance_savings_schemes')->restrictOnDelete();
            $table->date('opened_on');
            $table->date('closed_on')->nullable();
            $table->string('status', 20)->default('active');
            // Cache of the posted ledger, written in the same transaction as each posting
            // (finance:rebuild-balances recomputes it). Reports never read it.
            $table->decimal('balance', 15, $scale)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['branch_id', 'status']);

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
            if ($members && Schema::hasTable($members)) {
                $table->foreign('member_id')->references('id')->on($members)->restrictOnDelete();
            }
        });

        Schema::create('finance_savings_transactions', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('savings_account_id')->constrained('finance_savings_accounts')->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->string('type', 20);
            $table->date('transaction_date');
            $table->decimal('amount', 15, $scale);
            $table->foreignId('cash_bank_account_id')->constrained('finance_cash_bank_accounts')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'transaction_date']);
            $table->index(['type', 'status', 'transaction_date']);

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_savings_transactions');
        Schema::dropIfExists('finance_savings_accounts');
        Schema::dropIfExists('finance_savings_schemes');
    }
};
