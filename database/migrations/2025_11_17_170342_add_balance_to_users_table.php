<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // DECIMAL(20,2) to support large balances
            $table->decimal('balance', 20, 2)->default(0)->after('password');
            // Optional version column for optimistic concurrency
            $table->unsignedBigInteger('version')->default(0)->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance', 'version']);
        });
    }
};