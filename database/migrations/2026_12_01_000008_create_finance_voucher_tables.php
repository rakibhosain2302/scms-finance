<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $scale = (int) config('scms-finance.decimal_places', 2);

        // Income and expense vouchers share one shape.
        foreach (['finance_incomes' => 'payer', 'finance_expenses' => 'payee'] as $name => $party) {
            Schema::create($name, function (Blueprint $table) use ($branches, $scale, $party) {
                $table->id();
                $table->string('number', 40)->unique();
                $table->unsignedBigInteger('branch_id');
                $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
                $table->date('voucher_date');
                $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
                $table->foreignId('cash_bank_account_id')->constrained('finance_cash_bank_accounts')->restrictOnDelete();
                $table->decimal('amount', 15, $scale);
                $table->string($party)->nullable();
                $table->string('reference', 100)->nullable();
                $table->string('description', 500)->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('status', 20)->default('draft');
                $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
                $table->foreignId('reversal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'voucher_date']);
                $table->index(['status', 'voucher_date']);

                if (Schema::hasTable($branches)) {
                    $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
                }
            });
        }

        Schema::create('finance_transfers', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->date('transfer_date');
            $table->foreignId('from_account_id')->constrained('finance_cash_bank_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('finance_cash_bank_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, $scale);
            $table->string('reference', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'transfer_date']);

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transfers');
        Schema::dropIfExists('finance_expenses');
        Schema::dropIfExists('finance_incomes');
    }
};
