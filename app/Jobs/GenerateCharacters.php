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

class GenerateCharacters implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 600;

    public function __construct(public Story $story) {}

    public function uniqueId(): string
    {
        return 'generate-characters-' . $this->story->id;
    }

    public function failed(?\Throwable $exception): void
    {
        $orchestrator = app(PipelineOrchestrator::class);
        $orchestrator->failStage($this->story, PipelineStage::Characters, $exception?->getMessage() ?? 'Character generation failed');
    }

    public function handle(
        PipelineOrchestrator $orchestrator,
        AnthropicService $anthropic,
        ApiKeyVault $vault,
        ApiUsageTracker $usageTracker,
    ): void {
        $userId = $this->story->user_id;
        $anthropic->forUser($userId);

        try {
            $this->story->characters()->delete();

            $script = $this->story->full_script ?? '';

            if (empty($script)) {
                Log::warning('No script for character extraction', ['story_id' => $this->story->id]);
                $orchestrator->completeStage($this->story, PipelineStage::Characters);

                return;
            }

            if (! $vault->has('anthropic', $userId)) {
                $orchestrator->failStage($this->story, PipelineStage::Characters, 'Anthropic API key not configured.');

                return;
            }

            $result = $anthropic->extractCharacters($script);

            $usageTracker->recordAnthropic(
                $this->story->id,
                'extract_characters',
                $result['input_tokens'],
                $result['output_tokens'],
            );

            $characters = $result['characters'];

            if (empty($characters)) {
                Log::warning('No characters found in script', ['story_id' => $this->story->id]);
                $orchestrator->completeStage($this->story, PipelineStage::Characters);

                return;
            }

            foreach ($characters as $charData) {
                $this->story->characters()->create([
                    'name' => $charData['name'],
                    'description' => $charData['description'],
                ]);
            }

            $orchestrator->completeStage($this->story, PipelineStage::Characters);
        } catch (\Throwable $e) {
            $orchestrator->failStage($this->story, PipelineStage::Characters, $e->getMessage());

            throw $e;
        }
    }
}
