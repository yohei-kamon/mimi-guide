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
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibition_id')->constrained()->restrictOnDelete();
            $table->char('slug', 12)->unique();
            $table->unsignedInteger('guide_number');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('image_path')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['exhibition_id', 'guide_number']);
            $table->index(['exhibition_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
