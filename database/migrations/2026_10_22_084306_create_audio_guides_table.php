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
        Schema::create('audio_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['artwork_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_guides');
    }
};
