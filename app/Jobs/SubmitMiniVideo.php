<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Story;
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

class SubmitMiniVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Story $story,
        public int $videoId,
    ) {}

    public function handle(KlingService $kling, PipelineOrchestrator $orchestrator, ApiUsageTracker $usageTracker): void
    {
        $kling->forUser($this->story->user_id);
        $video = Video::with(['frameFrom', 'frameTo'])->find($this->videoId);

        if (! $video) {
            Log::error('Mini-video record not found for submission', ['video_id' => $this->videoId]);

            return;
        }

        $frameFrom = $video->frameFrom;
        $frameTo = $video->frameTo;

        if (! $frameFrom?->image_url || ! $frameTo?->image_url) {
            Log::error('Missing frame images for mini-video submission', ['video_id' => $video->id]);
            $video->update(['status' => 'failed']);
            $orchestrator->failStage($this->story, PipelineStage::Video, 'Missing frame images for clip #' . $video->sequence_number);

            return;
        }

        try {
            $taskId = $kling->submitGeneration(
                [$frameFrom->image_url, $frameTo->image_url],
                [
                    'prompt' => $video->prompt ?? '',
                    'duration' => '5',
                    'mode' => 'pro',
                ],
            );

            $usageTracker->recordApiCall(
                $this->story->id,
                'kling',
                'submit_mini_video_generation',
                [
                    'task_id' => $taskId,
                    'sequence_number' => $video->sequence_number,
                    'frame_from' => $frameFrom->sequence_number,
                    'frame_to' => $frameTo->sequence_number,
                ],
            );

            $video->update([
                'external_job_id' => $taskId,
                'status' => 'processing',
            ]);

            PollMiniVideoStatus::dispatch($this->story, $video->id, $taskId)
                ->onQueue('pipeline')
                ->delay(now()->addSeconds(60));
        } catch (\Throwable $e) {
            Log::error('Failed to submit mini-video', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);

            $orchestrator->failStage($this->story, PipelineStage::Video, $e->getMessage());

            throw $e;
        }
    }
}
