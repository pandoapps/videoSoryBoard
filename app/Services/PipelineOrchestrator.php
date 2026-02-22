<?php

namespace App\Services;

use App\Enums\PipelineStage;
use App\Enums\StoryStatus;
use App\Events\StoryStageCompleted;
use App\Jobs\GenerateCharacters;
use App\Jobs\GenerateStoryboard;
use App\Jobs\ProduceVideo;
use App\Models\Story;
use Illuminate\Support\Facades\Log;

class PipelineOrchestrator
{
    public function startPipeline(Story $story): void
    {
        if (! $story->full_script) {
            throw new \RuntimeException('Cannot start pipeline without a finalized script.');
        }

        $this->advanceToStage($story, PipelineStage::Characters);
    }

    public function advanceToStage(Story $story, PipelineStage $stage): void
    {
        Log::info("Pipeline advancing to stage: {$stage->value}", ['story_id' => $story->id]);

        $story->update([
            'status' => $stage->storyStatus(),
            'current_stage' => $stage,
            'error_message' => null,
        ]);

        match ($stage) {
            PipelineStage::Characters => GenerateCharacters::dispatch($story)->onQueue('pipeline'),
            PipelineStage::Storyboard => GenerateStoryboard::dispatch($story)->onQueue('pipeline'),
            PipelineStage::Video => ProduceVideo::dispatch($story)->onQueue('pipeline'),
            PipelineStage::Script => null, // Script stage is handled by chat
        };
    }

    public function completeStage(Story $story, PipelineStage $stage): void
    {
        Log::info("Pipeline stage completed: {$stage->value}", ['story_id' => $story->id]);

        // Pause after character generation for user review
        if ($stage === PipelineStage::Characters) {
            $story->update([
                'status' => StoryStatus::CharacterReview,
                'current_stage' => PipelineStage::Characters,
            ]);

            return;
        }

        // Pause after storyboard generation for user review
        if ($stage === PipelineStage::Storyboard) {
            $story->update([
                'status' => StoryStatus::StoryboardReview,
                'current_stage' => PipelineStage::Storyboard,
            ]);

            return;
        }

        $nextStage = $stage->next();

        if ($nextStage) {
            StoryStageCompleted::dispatch($story, $stage);
        } else {
            // All stages complete
            $story->update([
                'status' => StoryStatus::Completed,
                'current_stage' => null,
            ]);
        }
    }

    public function revertToStage(Story $story, PipelineStage $targetStage): void
    {
        Log::info("Pipeline reverting to stage: {$targetStage->value}", ['story_id' => $story->id]);

        // Delete downstream data based on target stage
        match ($targetStage) {
            PipelineStage::Script => $this->deleteFromCharacters($story),
            PipelineStage::Characters => $this->deleteFromStoryboard($story),
            PipelineStage::Storyboard => $this->deleteVideos($story),
            PipelineStage::Video => null,
        };

        // Update story status
        if ($targetStage === PipelineStage::Script) {
            $story->update([
                'status' => StoryStatus::Scripting,
                'current_stage' => PipelineStage::Script,
                'full_script' => null,
                'error_message' => null,
            ]);
        } elseif ($targetStage === PipelineStage::Characters) {
            $story->update([
                'status' => StoryStatus::CharacterReview,
                'current_stage' => PipelineStage::Characters,
                'error_message' => null,
            ]);
        } elseif ($targetStage === PipelineStage::Storyboard) {
            $story->update([
                'status' => StoryStatus::StoryboardReview,
                'current_stage' => PipelineStage::Storyboard,
                'error_message' => null,
            ]);
        }
    }

    private function deleteFromCharacters(Story $story): void
    {
        $story->characters()->delete();
        $this->deleteFromStoryboard($story);
    }

    private function deleteFromStoryboard(Story $story): void
    {
        $story->storyboardFrames()->delete();
        $this->deleteVideos($story);
    }

    private function deleteVideos(Story $story): void
    {
        $story->videos()->delete();
    }

    public function failStage(Story $story, PipelineStage $stage, string $error): void
    {
        Log::error("Pipeline stage failed: {$stage->value}", [
            'story_id' => $story->id,
            'error' => $error,
        ]);

        $story->update([
            'status' => StoryStatus::Failed,
            'current_stage' => $stage,
            'error_message' => "Failed at {$stage->value}: {$error}",
        ]);
    }

    public function getStageStatus(Story $story): array
    {
        $currentStage = $story->current_stage;
        $status = $story->status;

        // Data-driven: check what actually exists
        $hasScript = (bool) $story->full_script;
        $hasCharacters = $story->characters()->exists();
        $hasFrames = $story->storyboardFrames()->exists();
        $hasCompletedVideo = $story->videos()->where('is_final', true)->where('status', 'completed')->exists();

        return [
            $this->resolveStageStatus(
                PipelineStage::Script, $currentStage, $status,
                completed: $hasScript,
                inProgress: $status === StoryStatus::Scripting,
            ),
            $this->resolveStageStatus(
                PipelineStage::Characters, $currentStage, $status,
                completed: $hasCharacters,
                inProgress: $status === StoryStatus::Characters && ! $hasCharacters,
                review: $status === StoryStatus::CharacterReview,
            ),
            $this->resolveStageStatus(
                PipelineStage::Storyboard, $currentStage, $status,
                completed: $hasFrames,
                inProgress: $status === StoryStatus::Storyboard && ! $hasFrames,
                review: $status === StoryStatus::StoryboardReview,
            ),
            $this->resolveStageStatus(
                PipelineStage::Video, $currentStage, $status,
                completed: $hasCompletedVideo,
                inProgress: $status === StoryStatus::Producing && ! $hasCompletedVideo,
            ),
        ];
    }

    private function resolveStageStatus(
        PipelineStage $stage,
        ?PipelineStage $currentStage,
        StoryStatus $status,
        bool $completed = false,
        bool $inProgress = false,
        bool $review = false,
    ): array {
        if ($status === StoryStatus::Failed && $currentStage === $stage) {
            return ['stage' => $stage->value, 'status' => 'failed'];
        }

        if ($review) {
            return ['stage' => $stage->value, 'status' => 'review'];
        }

        if ($completed) {
            return ['stage' => $stage->value, 'status' => 'completed'];
        }

        if ($inProgress) {
            return ['stage' => $stage->value, 'status' => 'in_progress'];
        }

        return ['stage' => $stage->value, 'status' => 'pending'];
    }
}
