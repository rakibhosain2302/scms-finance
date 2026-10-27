<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $members = config('scms-finance.tables.members');

        Schema::table('finance_journal_lines', function (Blueprint $table) use ($members) {
            $table->foreign('savings_account_id')->references('id')->on('finance_savings_accounts')->restrictOnDelete();
            $table->foreign('loan_id')->references('id')->on('finance_loans')->restrictOnDelete();
            if ($members && Schema::hasTable($members)) {
                $table->foreign('member_id')->references('id')->on($members)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_journal_lines', function (Blueprint $table) {
            $table->dropForeign(['savings_account_id']);
            $table->dropForeign(['loan_id']);
        });
    }
};
