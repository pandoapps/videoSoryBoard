<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Pending = 'pending';
    case Scripting = 'scripting';
    case Characters = 'characters';
    case CharacterReview = 'character_review';
    case Storyboard = 'storyboard';
    case StoryboardReview = 'storyboard_review';
    case Producing = 'producing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Scripting => 'Writing Script',
            self::Characters => 'Generating Characters',
            self::CharacterReview => 'Reviewing Characters',
            self::Storyboard => 'Creating Storyboard',
            self::StoryboardReview => 'Reviewing Storyboard',
            self::Producing => 'Producing Video',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
