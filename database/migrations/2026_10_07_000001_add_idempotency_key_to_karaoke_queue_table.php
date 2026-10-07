<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karaoke_queue', function (Blueprint $table) {
            // One key per logical "add song": a replayed request finds its row instead of inserting a duplicate
            $table->string('idempotency_key', 64)->nullable()->after('requested_by_member_id');
            $table->unique(['room_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('karaoke_queue', function (Blueprint $table) {
            $table->dropUnique(['room_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
