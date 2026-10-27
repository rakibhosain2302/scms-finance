<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Only when no other package owns financial years (scms-finance.migrate_financial_years).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('scms-finance.migrate_financial_years', true) || Schema::hasTable('financial_years')) {
            return;
        }

        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique('starts_on');
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        if (config('scms-finance.migrate_financial_years', true)) {
            Schema::dropIfExists('financial_years');
        }
    }
};
