<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Share capital bought and refunded by members (the balance itself lives in the ledger). */
return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $members = config('scms-finance.tables.members');
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::create('finance_share_transactions', function (Blueprint $table) use ($branches, $members, $scale) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('member_id')->index();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->string('type', 20); // purchase | refund
            $table->date('transaction_date');
            $table->unsignedInteger('shares')->nullable();
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
            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
            if ($members && Schema::hasTable($members)) {
                $table->foreign('member_id')->references('id')->on($members)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_share_transactions');
    }
};
