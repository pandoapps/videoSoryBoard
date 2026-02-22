<?php

namespace App\Events;

use App\Enums\PipelineStage;
use App\Models\Story;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryStageCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Story $story,
        public PipelineStage $completedStage,
    ) {}
}
