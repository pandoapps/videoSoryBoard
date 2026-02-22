<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Video;
use App\Services\ApiUsageTracker;
use App\Services\KlingService;
use App\Services\PipelineOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateMiniVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Video $video,
        public ?string $newPrompt = null,
        public array $klingParams = [],
    ) {}

    public function handle(KlingService $kling, PipelineOrchestrator $orchestrator, ApiUsageTracker $usageTracker): void
    {
        $story = $this->video->story;
        $kling->forUser($story->user_id);

        try {
            // Delete the existing final video since it's now stale
            $story->videos()->where('is_final', true)->delete();

            // Update prompt if provided
            if ($this->newPrompt) {
                $this->video->update(['prompt' => $this->newPrompt]);
            }

            // Load frame relationships
            $this->video->load(['frameFrom', 'frameTo']);

            $frameFrom = $this->video->frameFrom;
            $frameTo = $this->video->frameTo;

            if (! $frameFrom?->image_url || ! $frameTo?->image_url) {
                Log::error('Mini-video regeneration failed: missing frame images', [
                    'video_id' => $this->video->id,
                ]);
                $this->video->update(['status' => 'failed']);

                return;
            }

            // Submit to Kling (image_tail requires pro mode)
            $taskId = $kling->submitGeneration(
                [$frameFrom->image_url, $frameTo->image_url],
                array_merge([
                    'prompt' => $this->video->prompt ?? '',
                    'duration' => '5',
                    'mode' => 'pro',
                ], $this->klingParams),
            );

            $usageTracker->recordApiCall(
                $story->id,
                'kling',
                'regenerate_mini_video',
                [
                    'task_id' => $taskId,
                    'video_id' => $this->video->id,
                    'sequence_number' => $this->video->sequence_number,
                ],
            );

            $metadata = $this->video->metadata ?? [];
            unset($metadata['stale']);

            $this->video->update([
                'external_job_id' => $taskId,
                'status' => 'processing',
                'video_url' => null,
                'video_path' => null,
                'duration_seconds' => null,
                'metadata' => $metadata,
            ]);

            // Dispatch polling (Kling takes ~5-6 min)
            PollMiniVideoStatus::dispatch($story, $this->video->id, $taskId)
                ->onQueue('pipeline')
                ->delay(now()->addSeconds(60));
        } catch (\Throwable $e) {
            Log::error('Mini-video regeneration failed', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
            ]);

            $this->video->update(['status' => 'failed']);

            throw $e;
        }
    }
}
