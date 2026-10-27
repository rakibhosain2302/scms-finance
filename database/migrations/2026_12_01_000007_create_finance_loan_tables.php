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

        Schema::create('finance_loan_products', function (Blueprint $table) use ($scale) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('interest_method', 20)->default('flat');
            // Yearly rate in percent (12.5 = 12.5 % a year).
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->string('repayment_frequency', 20)->default('monthly');
            $table->unsignedSmallInteger('min_installments')->default(1);
            $table->unsignedSmallInteger('max_installments')->default(12);
            $table->decimal('min_amount', 15, $scale)->default(0);
            $table->decimal('max_amount', 15, $scale)->nullable();
            // Charged on disbursement, kept from the amount paid out (percent of principal).
            $table->decimal('processing_fee_rate', 8, 4)->default(0);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->foreignId('receivable_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('interest_income_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('penalty_income_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('fee_income_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('finance_loans', function (Blueprint $table) use ($branches, $members, $scale) {
            $table->id();
            $table->string('loan_number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('member_id')->index();
            $table->foreignId('loan_product_id')->constrained('finance_loan_products')->restrictOnDelete();
            $table->decimal('principal', 15, $scale);
            // Terms copied from the product when applied: a later product change never alters a loan.
            $table->string('interest_method', 20);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->string('repayment_frequency', 20);
            $table->unsignedSmallInteger('installments_count');
            $table->decimal('processing_fee', 15, $scale)->default(0);
            $table->decimal('total_interest', 15, $scale)->default(0);
            $table->string('purpose', 500)->nullable();
            $table->string('status', 20)->default('applied');
            $table->date('applied_on');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->date('disbursed_on')->nullable();
            $table->date('first_due_on')->nullable();
            $table->unsignedBigInteger('disbursed_by')->nullable();
            $table->foreignId('disbursement_account_id')->nullable()->constrained('finance_cash_bank_accounts')->restrictOnDelete();
            $table->foreignId('disbursement_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->foreignId('financial_year_id')->nullable()->constrained('financial_years')->restrictOnDelete();
            // Cache of the receivable sub-ledger (rebuilt by finance:rebuild-balances).
            $table->decimal('principal_outstanding', 15, $scale)->default(0);
            $table->date('closed_on')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('disbursed_on');

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
            if ($members && Schema::hasTable($members)) {
                $table->foreign('member_id')->references('id')->on($members)->restrictOnDelete();
            }
        });

        Schema::create('finance_loan_installments', function (Blueprint $table) use ($scale) {
            $table->id();
            $table->foreignId('loan_id')->constrained('finance_loans')->restrictOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->date('due_date');
            $table->decimal('principal_due', 15, $scale);
            $table->decimal('interest_due', 15, $scale)->default(0);
            $table->decimal('principal_paid', 15, $scale)->default(0);
            $table->decimal('interest_paid', 15, $scale)->default(0);
            $table->string('status', 20)->default('pending');
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_no']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('finance_loan_repayments', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('loan_id')->constrained('finance_loans')->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->date('repayment_date');
            $table->decimal('amount', 15, $scale);
            $table->decimal('principal_amount', 15, $scale)->default(0);
            $table->decimal('interest_amount', 15, $scale)->default(0);
            $table->decimal('penalty_amount', 15, $scale)->default(0);
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

            $table->index(['branch_id', 'repayment_date']);
            $table->index(['status', 'repayment_date']);

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });

        // Which installments a repayment paid, so a reversal gives back exactly that.
        Schema::create('finance_loan_repayment_allocations', function (Blueprint $table) use ($scale) {
            $table->id();
            $table->foreignId('loan_repayment_id')->constrained('finance_loan_repayments')->restrictOnDelete();
            $table->foreignId('loan_installment_id')->constrained('finance_loan_installments')->restrictOnDelete();
            $table->decimal('principal', 15, $scale)->default(0);
            $table->decimal('interest', 15, $scale)->default(0);
            $table->timestamps();

            $table->unique(['loan_repayment_id', 'loan_installment_id'], 'finance_loan_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_loan_repayment_allocations');
        Schema::dropIfExists('finance_loan_repayments');
        Schema::dropIfExists('finance_loan_installments');
        Schema::dropIfExists('finance_loans');
        Schema::dropIfExists('finance_loan_products');
    }
};
