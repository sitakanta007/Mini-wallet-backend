<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Sender and receiver
            $table->unsignedBigInteger('sender_id')->index();
            $table->unsignedBigInteger('receiver_id')->index();

            // Amount receiver gets
            $table->decimal('amount', 20, 2);

            // Commission charged to sender
            $table->decimal('commission_fee', 20, 2)->default(0);

            $table->enum('status', ['completed', 'failed'])->default('completed');

            $table->timestamps();

            // add foreign keys
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
