<?php

namespace App\Jobs;

use App\Models\StoryboardFrame;
use App\Services\ApiUsageTracker;
use App\Services\MediaStorageService;
use App\Services\NanaBananaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateStoryboardFrame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 600;

    public function __construct(
        public StoryboardFrame $frame,
        public string $prompt,
    ) {}

    public function handle(NanaBananaService $nanaBanana, ApiUsageTracker $usageTracker, MediaStorageService $mediaStorage): void
    {
        $nanaBanana->forUser($this->frame->story->user_id);

        try {
            // Use original remote URLs for NanoBanana references (local URLs are inaccessible to external APIs)
            // Filter to only characters that appear in this frame (fall back to all if no mapping)
            $frameCharacterNames = $this->frame->metadata['characters'] ?? null;

            $charactersQuery = $this->frame->story->characters()->whereNotNull('image_url');
            if (is_array($frameCharacterNames) && ! empty($frameCharacterNames)) {
                $charactersQuery->whereIn('name', $frameCharacterNames);
            }

            $referenceUrls = $charactersQuery
                ->get(['name', 'image_url', 'metadata'])
                ->map(fn ($c) => $c->metadata['original_image_url'] ?? $c->image_url)
                ->filter(fn ($url) => $url && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1'))
                ->values()
                ->toArray();

            // Include last 3 storyboard frames as visual context for style consistency
            $recentFrameUrls = $this->frame->story->storyboardFrames()
                ->where('id', '!=', $this->frame->id)
                ->whereNotNull('image_url')
                ->orderByDesc('sequence_number')
                ->limit(3)
                ->get(['image_url', 'metadata'])
                ->map(fn ($f) => $f->metadata['original_image_url'] ?? $f->image_url)
                ->filter(fn ($url) => $url && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1'))
                ->values()
                ->toArray();

            $referenceUrls = array_merge($referenceUrls, $recentFrameUrls);

            $taskId = $nanaBanana->generateStoryboardFrame($this->prompt, $referenceUrls);

            $usageTracker->recordApiCall(
                $this->frame->story_id,
                'nano_banana',
                'generate_storyboard_frame',
                ['task_id' => $taskId, 'frame_sequence' => $this->frame->sequence_number, 'regeneration' => true],
            );

            $this->frame->update([
                'metadata' => array_merge($this->frame->metadata ?? [], ['task_id' => $taskId]),
            ]);

            $this->pollForCompletion($nanaBanana, $mediaStorage, $taskId);
        } catch (\Throwable $e) {
            Log::error('Storyboard frame regeneration failed', [
                'frame_id' => $this->frame->id,
                'error' => $e->getMessage(),
            ]);

            $this->frame->update([
                'metadata' => array_merge($this->frame->metadata ?? [], [
                    'regenerating' => false,
                    'error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    private function pollForCompletion(NanaBananaService $nanaBanana, MediaStorageService $mediaStorage, string $taskId): void
    {
        $maxAttempts = 60;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(5);
            $attempt++;

            $status = $nanaBanana->getTaskStatus($taskId);

            if ($status['status'] === 'success' && $status['image_url']) {
                $stored = $mediaStorage->download($status['image_url'], "stories/{$this->frame->story_id}/storyboard-frames");

                $this->frame->update([
                    'image_path' => $stored['path'],
                    'image_url' => $stored['url'],
                    'metadata' => array_merge($this->frame->metadata ?? [], [
                        'regenerating' => false,
                        'original_image_url' => $status['image_url'],
                    ]),
                ]);

                return;
            }

            if ($status['status'] === 'failed') {
                Log::error('Storyboard frame regeneration task failed', [
                    'frame_id' => $this->frame->id,
                    'task_id' => $taskId,
                    'error' => $status['error'],
                ]);

                $this->frame->update([
                    'metadata' => array_merge($this->frame->metadata ?? [], [
                        'regenerating' => false,
                        'error' => $status['error'] ?? 'Image generation failed',
                    ]),
                ]);

                return;
            }
        }

        Log::warning('Storyboard frame regeneration timed out', [
            'frame_id' => $this->frame->id,
            'task_id' => $taskId,
        ]);

        $this->frame->update([
            'metadata' => array_merge($this->frame->metadata ?? [], [
                'regenerating' => false,
                'error' => 'Generation timed out',
            ]),
        ]);
    }
}
