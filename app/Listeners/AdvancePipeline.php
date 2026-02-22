<?php

namespace App\Listeners;

use App\Events\StoryStageCompleted;
use App\Services\PipelineOrchestrator;

class AdvancePipeline
{
    public function __construct(private PipelineOrchestrator $orchestrator) {}

    public function handle(StoryStageCompleted $event): void
    {
        $nextStage = $event->completedStage->next();

        if ($nextStage) {
            $this->orchestrator->advanceToStage($event->story, $nextStage);
        }
    }
}
