<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every tap / keypad submission the child made on a question, in order —
     * including wrong taps before the correct one in "Find the Answer" mode.
     */
    public function up(): void
    {
        Schema::create('quiz_taps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_response_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('selected_label', 100)->nullable();
            $table->integer('selected_number')->nullable();
            $table->unsignedTinyInteger('screen_position')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('elapsed_ms')->default(0);
            $table->boolean('during_prompt')->default(false);

            $table->index(['quiz_response_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_taps');
    }
};
