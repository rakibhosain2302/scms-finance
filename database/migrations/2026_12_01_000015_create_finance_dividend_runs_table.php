<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dividend on share capital for a closed financial year, credited to members' savings accounts. */
return new class extends Migration
{
    public function up(): void
    {
        $scale = (int) config('scms-finance.decimal_places', 2);

        Schema::create('finance_dividend_runs', function (Blueprint $table) use ($scale) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->foreignId('savings_scheme_id')->constrained('finance_savings_schemes')->restrictOnDelete();
            $table->decimal('rate', 8, 4);
            $table->date('posting_date');
            $table->decimal('total', 15, $scale)->default(0);
            $table->unsignedInteger('accounts_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('finance_savings_transactions', function (Blueprint $table) {
            $table->foreignId('dividend_run_id')->nullable()->after('savings_interest_run_id')->constrained('finance_dividend_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_savings_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('dividend_run_id'));
        Schema::dropIfExists('finance_dividend_runs');
    }
};
