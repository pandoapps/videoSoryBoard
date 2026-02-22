<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'sequence_number',
        'is_final',
        'prompt',
        'frame_from_id',
        'frame_to_id',
        'external_job_id',
        'status',
        'video_path',
        'video_url',
        'duration_seconds',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function frameFrom(): BelongsTo
    {
        return $this->belongsTo(StoryboardFrame::class, 'frame_from_id');
    }

    public function frameTo(): BelongsTo
    {
        return $this->belongsTo(StoryboardFrame::class, 'frame_to_id');
    }

    public function scopeMiniVideos(Builder $query): Builder
    {
        return $query->where('is_final', false)->whereNotNull('sequence_number');
    }

    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('is_final', true);
    }
}
