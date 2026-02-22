<?php

namespace App\Jobs;

use App\Enums\PipelineStage;
use App\Models\Story;
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

class ProduceVideo implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Story $story) {}

    public function uniqueId(): string
    {
        return 'produce-video-' . $this->story->id;
    }

    public function handle(PipelineOrchestrator $orchestrator, ApiKeyVault $vault, AnthropicService $anthropic, ApiUsageTracker $usageTracker): void
    {
        $userId = $this->story->user_id;
        $anthropic->forUser($userId);

        try {
            // Clear existing video records from previous attempts
            $this->story->videos()->delete();

            if (! $vault->has('kling', $userId)) {
                $orchestrator->failStage($this->story, PipelineStage::Video, 'Kling API key not configured. Add it in Settings to produce videos.');

                return;
            }

            // Get all ordered storyboard frames (images generated on-demand by user)
            $frames = $this->story->storyboardFrames()
                ->orderBy('sequence_number')
                ->get();

            if ($frames->count() < 2) {
                Log::warning('Not enough storyboard frames for mini-video generation', [
                    'story_id' => $this->story->id,
                    'frame_count' => $frames->count(),
                ]);
                $orchestrator->failStage($this->story, PipelineStage::Video, 'At least 2 storyboard frames are required to produce video clips.');

                return;
            }

            // Build transition pairs for Anthropic prompt generation
            $transitions = [];
            for ($i = 0; $i < $frames->count() - 1; $i++) {
                $transitions[] = [
                    'from_seq' => $frames[$i]->sequence_number,
                    'to_seq' => $frames[$i + 1]->sequence_number,
                    'from_desc' => $frames[$i]->scene_description ?? $frames[$i]->prompt ?? '',
                    'to_desc' => $frames[$i + 1]->scene_description ?? $frames[$i + 1]->prompt ?? '',
                ];
            }

            // Generate prompts via Anthropic using the script context
            $result = $anthropic->generateVideoTransitionPrompts(
                $this->story->full_script,
                $transitions,
            );

            $usageTracker->recordAnthropic(
                $this->story->id,
                'generate_video_prompts',
                $result['input_tokens'],
                $result['output_tokens'],
            );

            $prompts = $result['prompts'];

            // Create N-1 mini-video placeholders with AI-generated prompts (Kling submission on-demand by user)
            for ($i = 0; $i < $frames->count() - 1; $i++) {
                $frameFrom = $frames[$i];
                $frameTo = $frames[$i + 1];
                $sequenceNumber = $i + 1;

                $this->story->videos()->create([
                    'sequence_number' => $sequenceNumber,
                    'is_final' => false,
                    'prompt' => $prompts[$i] ?? "Smooth cinematic transition from frame {$frameFrom->sequence_number} to frame {$frameTo->sequence_number}",
                    'frame_from_id' => $frameFrom->id,
                    'frame_to_id' => $frameTo->id,
                    'status' => 'queued',
                ]);
            }
        } catch (\Throwable $e) {
            $orchestrator->failStage($this->story, PipelineStage::Video, $e->getMessage());

            throw $e;
        }
    }
}
