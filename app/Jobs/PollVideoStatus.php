<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Story;
use App\Models\Video;
use App\Services\KlingService;
use App\Services\PipelineOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollVideoStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const MAX_POLLS = 120; // 120 * 15s = 30 minutes max
    private const POLL_INTERVAL = 15;

    public function __construct(
        public Story $story,
        public int $videoId,
        public string $generationId,
        public int $pollCount = 0,
    ) {}

    public function handle(KlingService $kling, PipelineOrchestrator $orchestrator): void
    {
        $kling->forUser($this->story->user_id);
        $video = Video::find($this->videoId);

        if (! $video) {
            Log::error('Video record not found', ['video_id' => $this->videoId]);

            return;
        }

        try {
            $status = $kling->checkStatus($this->generationId);

            if ($status['status'] === 'completed') {
                $video->update([
                    'status' => 'completed',
                    'video_url' => $status['video_url'],
                    'duration_seconds' => $status['duration'],
                ]);

                $orchestrator->completeStage($this->story, PipelineStage::Video);

                return;
            }

            if ($status['status'] === 'failed') {
                $video->update(['status' => 'failed']);
                $orchestrator->failStage($this->story, PipelineStage::Video, $status['error'] ?? 'Video generation failed');

                return;
            }

            // Still processing - schedule another poll
            if ($this->pollCount >= self::MAX_POLLS) {
                $video->update(['status' => 'failed']);
                $orchestrator->failStage($this->story, PipelineStage::Video, 'Video generation timed out');

                return;
            }

            self::dispatch($this->story, $this->videoId, $this->generationId, $this->pollCount + 1)
                ->onQueue('pipeline')
                ->delay(now()->addSeconds(self::POLL_INTERVAL));
        } catch (\Throwable $e) {
            Log::error('Error polling video status', [
                'video_id' => $this->videoId,
                'generation_id' => $this->generationId,
                'error' => $e->getMessage(),
            ]);

            // Retry polling unless we've exceeded max attempts
            if ($this->pollCount < self::MAX_POLLS) {
                self::dispatch($this->story, $this->videoId, $this->generationId, $this->pollCount + 1)
                    ->onQueue('pipeline')
                    ->delay(now()->addSeconds(self::POLL_INTERVAL * 2));
            } else {
                $video->update(['status' => 'failed']);
                $orchestrator->failStage($this->story, PipelineStage::Video, 'Video generation polling failed: ' . $e->getMessage());
            }
        }
    }
}
