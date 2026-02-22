<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Story;
use App\Services\PipelineOrchestrator;
use App\Services\VideoConcatenationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConcatenateVideos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 600;

    public function __construct(public Story $story) {}

    public function handle(VideoConcatenationService $concatenator, PipelineOrchestrator $orchestrator): void
    {
        try {
            // Verify all mini-videos are completed
            $miniVideos = $this->story->miniVideos()->get();
            $allCompleted = $miniVideos->every(fn ($v) => $v->status === 'completed');

            if (! $allCompleted) {
                Log::warning('Cannot concatenate: not all mini-videos are completed', [
                    'story_id' => $this->story->id,
                ]);

                return;
            }

            // Delete existing final video
            $this->story->videos()->where('is_final', true)->delete();

            $result = $concatenator->concatenate($this->story);

            // Create final video record
            $this->story->videos()->create([
                'is_final' => true,
                'status' => 'completed',
                'video_path' => $result['path'],
                'video_url' => $result['url'],
                'duration_seconds' => $result['duration'],
            ]);

            $orchestrator->completeStage($this->story, PipelineStage::Video);
        } catch (\Throwable $e) {
            Log::error('Video concatenation failed', [
                'story_id' => $this->story->id,
                'error' => $e->getMessage(),
            ]);

            $orchestrator->failStage($this->story, PipelineStage::Video, 'Video concatenation failed: ' . $e->getMessage());
        }
    }
}
