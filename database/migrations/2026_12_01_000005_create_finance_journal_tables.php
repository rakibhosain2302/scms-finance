<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::create('finance_journal_entries', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->date('entry_date');
            $table->string('description', 500)->nullable();
            $table->string('reference', 100)->nullable();
            // The document that made the entry: "savings_transaction", "loan_repayment", "manual", …
            $table->string('source_type', 50)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('total', 15, $scale)->default(0);
            // A reversal points at the entry it cancels; unique, so an entry is reversed once at most.
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'entry_date']);
            $table->index(['status', 'entry_date']);
            $table->index(['source_type', 'source_id']);
            $table->index('financial_year_id');

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });

        Schema::create('finance_journal_lines', function (Blueprint $table) use ($scale) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('finance_journal_entries')->restrictOnDelete();
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->decimal('debit', 15, $scale)->default(0);
            $table->decimal('credit', 15, $scale)->default(0);
            $table->string('description', 500)->nullable();
            $table->string('component', 20)->nullable();
            // Sub-ledgers: whose money the line is (foreign keys added once those tables exist).
            $table->unsignedBigInteger('cash_bank_account_id')->nullable()->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->unsignedBigInteger('savings_account_id')->nullable()->index();
            $table->unsignedBigInteger('loan_id')->nullable()->index();
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
            $table->foreign('cash_bank_account_id')->references('id')->on('finance_cash_bank_accounts')->restrictOnDelete();
        });

        // A line is a debit or a credit, never negative, never both, never empty.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement('ALTER TABLE finance_journal_lines ADD CONSTRAINT finance_journal_lines_amount_check CHECK (debit >= 0 AND credit >= 0 AND (debit = 0 OR credit = 0) AND (debit > 0 OR credit > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_journal_lines');
        Schema::dropIfExists('finance_journal_entries');
    }
};
