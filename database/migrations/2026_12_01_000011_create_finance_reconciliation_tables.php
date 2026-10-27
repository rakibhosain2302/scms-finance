<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation: a statement (date and closing balance) of a cash or bank account, and the
 * ledger lines ticked off against it. A line is cleared by one reconciliation at most.
 */
return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::create('finance_reconciliations', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('cash_bank_account_id')->constrained('finance_cash_bank_accounts')->restrictOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, $scale);
            // Set when completed: the cleared balance then equals the statement balance.
            $table->decimal('cleared_balance', 15, $scale)->nullable();
            $table->decimal('book_balance', 15, $scale)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['cash_bank_account_id', 'status']);
            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });

        Schema::create('finance_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('finance_reconciliations')->cascadeOnDelete();
            $table->foreignId('journal_line_id')->unique()->constrained('finance_journal_lines')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_reconciliation_lines');
        Schema::dropIfExists('finance_reconciliations');
    }
};
