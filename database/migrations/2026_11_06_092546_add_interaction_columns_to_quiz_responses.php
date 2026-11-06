<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Behavioural detail per answer, used by the analytics (all nullable: older answers lack it).
     */
    public function up(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->unsignedInteger('first_tap_ms')->nullable()->after('response_time_ms');
            $table->unsignedInteger('prompt_ended_ms')->nullable()->after('first_tap_ms');
            $table->unsignedSmallInteger('replay_count')->default(0)->after('prompt_ended_ms');
            $table->boolean('answered_during_prompt')->default(false)->after('replay_count');
            $table->json('option_order')->nullable()->after('answered_during_prompt');
            $table->unsignedTinyInteger('selected_position')->nullable()->after('option_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn([
                'first_tap_ms', 'prompt_ended_ms', 'replay_count',
                'answered_during_prompt', 'option_order', 'selected_position',
            ]);
        });
    }
};
