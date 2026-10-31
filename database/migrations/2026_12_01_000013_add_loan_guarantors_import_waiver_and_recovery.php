<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loans: guarantors and collateral, running loans brought in at go-live, interest waived on early
 * settlement, rescheduling notes, and money recovered after a write-off.
 */
return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');
        $members = config('scms-finance.tables.members');
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::table('finance_loan_products', function (Blueprint $table) {
            $table->unsignedTinyInteger('guarantors_required')->default(0)->after('penalty_value');
        });

        Schema::table('finance_loans', function (Blueprint $table) use ($scale) {
            $table->text('collateral')->nullable()->after('purpose');
            // Brought in at go-live: principal is what was still owed then; original_principal what was lent.
            $table->boolean('imported')->default(false)->after('collateral');
            $table->decimal('original_principal', 15, $scale)->nullable()->after('imported');
            $table->string('opening_reference', 100)->nullable()->after('original_principal');
            $table->timestamp('rescheduled_at')->nullable()->after('write_off_reason');
            $table->string('reschedule_note', 500)->nullable()->after('rescheduled_at');
        });

        Schema::create('finance_loan_guarantors', function (Blueprint $table) use ($members) {
            $table->id();
            $table->foreignId('loan_id')->constrained('finance_loans')->cascadeOnDelete();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->string('name');
            $table->string('relation', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('nid', 20)->nullable();
            $table->timestamps();
            if ($members && Schema::hasTable($members)) {
                $table->foreign('member_id')->references('id')->on($members)->nullOnDelete();
            }
        });

        Schema::table('finance_loan_installments', function (Blueprint $table) use ($scale) {
            $table->decimal('interest_waived', 15, $scale)->default(0)->after('interest_paid');
        });

        Schema::create('finance_loan_recoveries', function (Blueprint $table) use ($branches, $scale) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('loan_id')->constrained('finance_loans')->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->date('recovery_date');
            $table->decimal('amount', 15, $scale);
            $table->foreignId('cash_bank_account_id')->constrained('finance_cash_bank_accounts')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->unique()->constrained('finance_journal_entries')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });

        $parent = DB::table('finance_accounts')->where('code', '4000')->value('id');
        if ($parent && ! DB::table('finance_accounts')->where('code', '4700')->exists()) {
            DB::table('finance_accounts')->insert([
                'code' => '4700', 'name' => 'Recovery of Written-off Loans', 'type' => 'income', 'subtype' => 'general', 'parent_id' => $parent,
                'is_postable' => true, 'is_active' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_loan_recoveries');
        Schema::table('finance_loan_installments', fn (Blueprint $table) => $table->dropColumn('interest_waived'));
        Schema::dropIfExists('finance_loan_guarantors');
        Schema::table('finance_loans', fn (Blueprint $table) => $table->dropColumn(['collateral', 'imported', 'original_principal', 'opening_reference', 'rescheduled_at', 'reschedule_note']));
        Schema::table('finance_loan_products', fn (Blueprint $table) => $table->dropColumn('guarantors_required'));
    }
};
