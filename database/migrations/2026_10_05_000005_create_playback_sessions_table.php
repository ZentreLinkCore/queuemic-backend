<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('queue_item_id')->nullable()->constrained('karaoke_queue')->nullOnDelete();
            $table->foreignId('song_id')->nullable()->constrained('songs')->nullOnDelete();
            $table->enum('state', ['playing', 'paused', 'buffering', 'stopped'])->default('stopped');
            $table->unsignedInteger('current_time_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_sessions');
    }
};
