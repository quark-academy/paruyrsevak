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
        Schema::create('poem_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poem_id')->constrained('poems')->cascadeOnDelete();
            $table->string('word');
            $table->decimal('start_time', 8, 2)->default(0);
            $table->decimal('end_time', 8, 2)->default(0);
            $table->unsignedInteger('word_order')->default(0);
            $table->timestamps();

            $table->index(['poem_id', 'word_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poem_words');
    }
};
