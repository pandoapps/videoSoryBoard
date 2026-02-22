<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('service'); // anthropic, nano_banana, higgsfield
            $table->string('operation'); // chat, extract_script, generate_panels, generate_character_image, generate_storyboard_frame, submit_video_generation
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('story_id');
            $table->index('service');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage');
    }
};
