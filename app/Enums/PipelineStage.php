<?php

namespace App\Enums;

enum PipelineStage: string
{
    case Script = 'script';
    case Characters = 'characters';
    case Storyboard = 'storyboard';
    case Video = 'video';

    public function next(): ?self
    {
        return match ($this) {
            self::Script => self::Characters,
            self::Characters => self::Storyboard,
            self::Storyboard => self::Video,
            self::Video => null,
        };
    }

    public function storyStatus(): StoryStatus
    {
        return match ($this) {
            self::Script => StoryStatus::Scripting,
            self::Characters => StoryStatus::Characters,
            self::Storyboard => StoryStatus::Storyboard,
            self::Video => StoryStatus::Producing,
        };
    }
}
