<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storyboard_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->integer('sequence_number');
            $table->text('scene_description');
            $table->string('image_path', 500)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storyboard_frames');
    }
};
