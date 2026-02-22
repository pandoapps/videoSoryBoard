<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->integer('sequence_number')->nullable()->after('story_id');
            $table->boolean('is_final')->default(false)->after('sequence_number');
            $table->text('prompt')->nullable()->after('is_final');
            $table->foreignId('frame_from_id')->nullable()->after('prompt')
                ->constrained('storyboard_frames')->nullOnDelete();
            $table->foreignId('frame_to_id')->nullable()->after('frame_from_id')
                ->constrained('storyboard_frames')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frame_from_id');
            $table->dropConstrainedForeignId('frame_to_id');
            $table->dropColumn(['sequence_number', 'is_final', 'prompt']);
        });
    }
};
