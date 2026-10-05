<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karaoke_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->foreignId('requested_by_member_id')->constrained('room_members')->cascadeOnDelete();
            $table->string('singer_name', 50);
            $table->unsignedInteger('position');
            $table->enum('status', ['pending', 'playing', 'completed', 'skipped', 'removed'])->default('pending')->index();
            $table->timestamp('played_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karaoke_queue');
    }
};
