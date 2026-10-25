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
        Schema::create('guide_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('museum_id')->constrained()->restrictOnDelete();
            $table->char('token', 40)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('amount');
            $table->string('email')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['museum_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guide_passes');
    }
};
