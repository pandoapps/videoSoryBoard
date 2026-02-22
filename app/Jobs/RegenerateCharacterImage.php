<?php

namespace App\Jobs;

use App\Models\Character;
use App\Services\MediaStorageService;
use App\Services\NanaBananaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateCharacterImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 600;

    public function __construct(
        public Character $character,
        public string $prompt,
    ) {}

    public function handle(NanaBananaService $nanaBanana, MediaStorageService $mediaStorage): void
    {
        $nanaBanana->forUser($this->character->story->user_id);

        try {
            $imagePrompt = "Professional character design, full body portrait: {$this->character->name}. {$this->prompt}. High quality, detailed, consistent art style, suitable for animation.";

            $taskId = $nanaBanana->generateCharacterImage($imagePrompt);

            $this->character->update([
                'metadata' => array_merge($this->character->metadata ?? [], ['task_id' => $taskId]),
            ]);

            $this->pollForCompletion($nanaBanana, $mediaStorage, $taskId);
        } catch (\Throwable $e) {
            Log::error('Character image regeneration failed', [
                'character_id' => $this->character->id,
                'error' => $e->getMessage(),
            ]);

            $this->character->update([
                'metadata' => array_merge($this->character->metadata ?? [], [
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
                $stored = $mediaStorage->download($status['image_url'], "stories/{$this->character->story_id}/characters");

                $this->character->update([
                    'image_path' => $stored['path'],
                    'image_url' => $stored['url'],
                    'metadata' => array_merge($this->character->metadata ?? [], [
                        'regenerating' => false,
                        'original_image_url' => $status['image_url'],
                    ]),
                ]);

                return;
            }

            if ($status['status'] === 'failed') {
                Log::error('Character image regeneration task failed', [
                    'character_id' => $this->character->id,
                    'task_id' => $taskId,
                    'error' => $status['error'],
                ]);

                $this->character->update([
                    'metadata' => array_merge($this->character->metadata ?? [], [
                        'regenerating' => false,
                        'error' => $status['error'] ?? 'Image generation failed',
                    ]),
                ]);

                return;
            }
        }

        Log::warning('Character image regeneration timed out', [
            'character_id' => $this->character->id,
            'task_id' => $taskId,
        ]);

        $this->character->update([
            'metadata' => array_merge($this->character->metadata ?? [], [
                'regenerating' => false,
                'error' => 'Generation timed out',
            ]),
        ]);
    }
}
