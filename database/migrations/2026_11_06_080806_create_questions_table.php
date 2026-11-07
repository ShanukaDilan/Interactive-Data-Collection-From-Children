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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id', 40)->nullable()->unique();
            $table->string('type', 20)->default('choice');
            $table->string('category', 80)->index();
            $table->string('speak_text', 255);
            $table->string('hint', 120)->nullable();
            $table->string('question_emoji', 64)->nullable();
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('correct_number')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_archived')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
