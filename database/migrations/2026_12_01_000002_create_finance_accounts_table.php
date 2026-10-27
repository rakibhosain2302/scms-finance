<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $branches = config('scms-finance.tables.branches', 'branches');

        // Chart of accounts: shared by all branches unless branch_id limits an account to one.
        Schema::create('finance_accounts', function (Blueprint $table) use ($branches) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('finance_accounts')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20)->index();
            $table->string('subtype', 30)->default('general')->index();
            // Group accounts only add up their children; journal lines go to postable accounts.
            $table->boolean('is_postable')->default(true);
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            // Created by the seeder and named in config: the code cannot change.
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            if (Schema::hasTable($branches)) {
                $table->foreign('branch_id')->references('id')->on($branches)->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};
