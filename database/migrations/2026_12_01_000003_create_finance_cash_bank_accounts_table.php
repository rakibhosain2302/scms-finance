<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');

        // A cash drawer or a bank account of one branch. Its balance is the sum of the posted
        // journal lines that carry its id; there is no balance column.
        Schema::create('finance_cash_bank_accounts', function (Blueprint $table) use ($branches) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->index();
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->string('kind', 10)->index();
            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('account_number', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['branch_id', 'name']);

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_cash_bank_accounts');
    }
};
