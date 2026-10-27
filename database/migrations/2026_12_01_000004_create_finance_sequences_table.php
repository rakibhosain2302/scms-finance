<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One counter per number series ("DEP-2026", "DEP-DHK-2026"), read with SELECT … FOR UPDATE.
        Schema::create('finance_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 60)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_sequences');
    }
};
