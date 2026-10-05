<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('external_video_id', 100)->unique()->index();
            $table->string('title', 255)->index();
            $table->string('artist', 255)->default('Unknown')->index();
            $table->string('duration_formatted', 10)->default('00:00');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('thumbnail_url');
            $table->string('source_provider', 50)->default('youtube');
            $table->json('metadata_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
