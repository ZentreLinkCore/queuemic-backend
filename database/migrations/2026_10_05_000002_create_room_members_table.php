<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('session_token_hash', 64)->unique()->index();
            $table->string('device_uuid', 64);
            $table->string('nickname', 50);
            $table->string('avatar_color', 7)->default('#8B5CF6');
            $table->enum('role', ['host', 'guest'])->default('guest');
            $table->boolean('is_connected')->default(true);
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->index(['room_id', 'is_connected']);
            $table->index(['room_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_members');
    }
};
