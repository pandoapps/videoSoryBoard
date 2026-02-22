<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryboardFrame extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'sequence_number',
        'scene_description',
        'prompt',
        'image_path',
        'image_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
