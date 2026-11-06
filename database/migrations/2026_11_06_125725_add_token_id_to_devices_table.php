<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a device to the Sanctum token it uses for offline sync.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->unique()->after('user_id');
            $table->timestamp('last_seen_at')->nullable()->after('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['personal_access_token_id']);
            $table->dropColumn(['personal_access_token_id', 'last_seen_at']);
        });
    }
};
