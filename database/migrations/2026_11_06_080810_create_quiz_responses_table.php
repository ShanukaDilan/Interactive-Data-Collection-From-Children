<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshot columns (question_ref, question_text, category, correct_label)
     * keep each response meaningful even after the question is edited or deleted.
     */
    public function up(): void
    {
        Schema::create('quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_ref', 40)->nullable();
            $table->unsignedInteger('question_version')->nullable();
            $table->string('question_text', 255)->nullable();
            $table->string('category', 80)->nullable()->index();
            $table->string('correct_label', 100)->nullable();
            $table->string('selected_label', 100)->nullable();
            $table->integer('selected_number')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_responses');
    }
};
