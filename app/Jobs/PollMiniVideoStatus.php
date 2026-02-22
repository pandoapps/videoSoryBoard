<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Story;
use App\Models\Video;
use App\Services\KlingService;
use App\Services\MediaStorageService;
use App\Services\PipelineOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollMiniVideoStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const MAX_POLLS = 60; // 60 * 30s = 30 minutes max
    private const POLL_INTERVAL = 30; // Kling takes ~5-6 min, poll every 30s

    public function __construct(
        public Story $story,
        public int $videoId,
        public string $taskId,
        public int $pollCount = 0,
    ) {}

    public function handle(KlingService $kling, PipelineOrchestrator $orchestrator, MediaStorageService $mediaStorage): void
    {
        $kling->forUser($this->story->user_id);
        $video = Video::find($this->videoId);

        if (! $video) {
            Log::error('Mini-video record not found', ['video_id' => $this->videoId]);

            return;
        }

        try {
            $status = $kling->checkStatus($this->taskId);

            if ($status['status'] === 'completed') {
                $stored = $mediaStorage->download($status['video_url'], "stories/{$this->story->id}/videos", 'mp4');

                $video->update([
                    'status' => 'completed',
                    'video_path' => $stored['path'],
                    'video_url' => $stored['url'],
                    'duration_seconds' => $status['duration'],
                ]);

                $this->submitNextOrFinish($video, $orchestrator);

                return;
            }

            if ($status['status'] === 'failed') {
                $video->update(['status' => 'failed']);
                $orchestrator->failStage($this->story, PipelineStage::Video, $status['error'] ?? 'Mini-video generation failed');

                return;
            }

            // Still processing - schedule another poll
            if ($this->pollCount >= self::MAX_POLLS) {
                $video->update(['status' => 'failed']);
                $orchestrator->failStage($this->story, PipelineStage::Video, 'Mini-video generation timed out');

                return;
            }

            self::dispatch($this->story, $this->videoId, $this->taskId, $this->pollCount + 1)
                ->onQueue('pipeline')
                ->delay(now()->addSeconds(self::POLL_INTERVAL));
        } catch (\Throwable $e) {
            Log::error('Error polling mini-video status', [
                'video_id' => $this->videoId,
                'task_id' => $this->taskId,
                'error' => $e->getMessage(),
            ]);

            if ($this->pollCount < self::MAX_POLLS) {
                self::dispatch($this->story, $this->videoId, $this->taskId, $this->pollCount + 1)
                    ->onQueue('pipeline')
                    ->delay(now()->addSeconds(self::POLL_INTERVAL * 2));
            } else {
                $video->update(['status' => 'failed']);
                $orchestrator->failStage($this->story, PipelineStage::Video, 'Mini-video polling failed: ' . $e->getMessage());
            }
        }
    }

    private function submitNextOrFinish(Video $completedVideo, PipelineOrchestrator $orchestrator): void
    {
        // Find the next queued mini-video in sequence
        $nextVideo = $this->story->miniVideos()
            ->where('sequence_number', '>', $completedVideo->sequence_number)
            ->where('status', 'queued')
            ->orderBy('sequence_number')
            ->first();

        if ($nextVideo) {
            // Submit the next one
            SubmitMiniVideo::dispatch($this->story, $nextVideo->id)->onQueue('pipeline');

            return;
        }

        // No more queued videos — check if all are completed
        $miniVideos = $this->story->miniVideos()->get();
        $allCompleted = $miniVideos->every(fn (Video $v) => $v->status === 'completed');

        if ($allCompleted) {
            ConcatenateVideos::dispatch($this->story)->onQueue('pipeline');
        }
    }
}
