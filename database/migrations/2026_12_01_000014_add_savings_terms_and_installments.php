<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fixed-term savings (DPS / FDR) and the regular deposit an account is expected to make. */
return new class extends Migration
{
    public function up(): void
    {
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::table('finance_savings_schemes', function (Blueprint $table) {
            $table->unsignedSmallInteger('term_months')->nullable()->after('max_withdrawals_per_month');
            $table->boolean('withdraw_before_maturity')->default(true)->after('term_months');
        });

        Schema::table('finance_savings_accounts', function (Blueprint $table) use ($scale) {
            $table->date('maturity_date')->nullable()->after('opened_on');
            // Expected deposit per period of a daily / weekly / monthly scheme (dues report).
            $table->decimal('installment_amount', 15, $scale)->nullable()->after('maturity_date');
        });
    }

    public function down(): void
    {
        Schema::table('finance_savings_accounts', fn (Blueprint $table) => $table->dropColumn(['maturity_date', 'installment_amount']));
        Schema::table('finance_savings_schemes', fn (Blueprint $table) => $table->dropColumn(['term_months', 'withdraw_before_maturity']));
    }
};
