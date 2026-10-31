<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_no')->unique();
            $table->string('name');
            $table->unsignedBigInteger('branch_id');
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Rolled back before the package's tables, whose member_id keys point here.
        Schema::withoutForeignKeyConstraints(fn () => Schema::dropIfExists('test_members'));
    }
};
