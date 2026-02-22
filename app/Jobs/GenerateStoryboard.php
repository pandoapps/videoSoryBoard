<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Story;
use App\Models\StoryboardFrame;
use App\Services\AnthropicService;
use App\Services\ApiKeyVault;
use App\Services\ApiUsageTracker;
use App\Services\PipelineOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateStoryboard implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 600;

    public function __construct(public Story $story) {}

    public function uniqueId(): string
    {
        return 'generate-storyboard-' . $this->story->id;
    }

    public function handle(
        AnthropicService $anthropic,
        PipelineOrchestrator $orchestrator,
        ApiKeyVault $vault,
        ApiUsageTracker $usageTracker,
    ): void {
        $userId = $this->story->user_id;
        $anthropic->forUser($userId);

        try {
            // Clear existing frames from previous attempts
            StoryboardFrame::where('story_id', $this->story->id)->delete();

            $script = $this->story->full_script ?? '';

            if (empty($script)) {
                Log::warning('No script for storyboard generation', ['story_id' => $this->story->id]);
                $orchestrator->completeStage($this->story, PipelineStage::Storyboard);

                return;
            }

            if (! $vault->has('anthropic', $userId)) {
                $orchestrator->failStage($this->story, PipelineStage::Storyboard, 'Anthropic API key not configured.');

                return;
            }

            // Step 1: Ask Claude to identify scenes and estimate durations
            $durationResult = $anthropic->estimateSceneDurations($script);

            $usageTracker->recordAnthropic(
                $this->story->id,
                'estimate_scene_durations',
                $durationResult['input_tokens'],
                $durationResult['output_tokens'],
            );

            $scenes = $durationResult['scenes'];

            if (empty($scenes)) {
                Log::warning('No scenes identified in script', ['story_id' => $this->story->id]);
                $orchestrator->completeStage($this->story, PipelineStage::Storyboard);

                return;
            }

            // Step 2: Calculate frame timestamps for each scene
            $frameStructure = [];
            foreach ($scenes as $scene) {
                $duration = max((int) ($scene['duration_seconds'] ?? 5), 2);
                $frames = $this->calculateFrameTimestamps($duration);

                $frameStructure[] = [
                    'scene' => $scene['scene'] ?? 0,
                    'duration_seconds' => $duration,
                    'summary' => $scene['summary'] ?? '',
                    'frames' => $frames,
                ];
            }

            Log::info('Frame structure calculated', [
                'story_id' => $this->story->id,
                'scenes' => count($frameStructure),
                'total_frames' => array_sum(array_map(fn ($s) => count($s['frames']), $frameStructure)),
            ]);

            // Fetch character names for per-frame mapping
            $characterNames = $this->story->characters()->pluck('name')->toArray();

            // Step 3: Ask Claude for visual descriptions of each frame
            $descResult = $anthropic->generateFrameDescriptions($script, $frameStructure, $characterNames);

            $usageTracker->recordAnthropic(
                $this->story->id,
                'generate_frame_descriptions',
                $descResult['input_tokens'],
                $descResult['output_tokens'],
            );

            $panels = $descResult['panels'];

            if (empty($panels)) {
                Log::warning('No panels generated for storyboard', ['story_id' => $this->story->id]);
                $orchestrator->completeStage($this->story, PipelineStage::Storyboard);

                return;
            }

            // Step 4: Create storyboard frame records
            foreach ($panels as $index => $panel) {
                $description = $panel['description'] ?? '';
                $sceneNumber = $panel['scene'] ?? null;
                $second = $panel['second'] ?? null;
                $frameCharacters = $panel['characters'] ?? [];

                $prompt = "Comic book panel, sequential art style, no text or speech bubbles: {$description}. Cinematic composition, vivid colors, detailed illustration.";

                $sceneLabel = $sceneNumber !== null
                    ? "Scene {$sceneNumber}" . ($second !== null ? " @ {$second}s" : '')
                    : null;

                $metadata = ['characters' => $frameCharacters];
                if ($sceneNumber !== null) {
                    $metadata['scene'] = $sceneNumber;
                }
                if ($second !== null) {
                    $metadata['second'] = $second;
                }

                $this->story->storyboardFrames()->create([
                    'sequence_number' => $index + 1,
                    'scene_description' => ($sceneLabel ? "[{$sceneLabel}] " : '') . $description,
                    'prompt' => $prompt,
                    'metadata' => $metadata,
                ]);
            }

            $orchestrator->completeStage($this->story, PipelineStage::Storyboard);
        } catch (\Throwable $e) {
            $orchestrator->failStage($this->story, PipelineStage::Storyboard, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Calculate frame timestamps for a scene: 1 at start (0s), 1 every 5s, 1 at end.
     *
     * Examples:
     *  - 15s → [0, 5, 10, 15]
     *  - 8s  → [0, 5, 8]
     *  - 3s  → [0, 3]
     *  - 12s → [0, 5, 10, 12]
     *
     * @return int[]
     */
    private function calculateFrameTimestamps(int $durationSeconds): array
    {
        $timestamps = [0];

        for ($t = 5; $t < $durationSeconds; $t += 5) {
            $timestamps[] = $t;
        }

        // Always include the last second if not already covered
        if (end($timestamps) !== $durationSeconds) {
            $timestamps[] = $durationSeconds;
        }

        return $timestamps;
    }
}
