<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsage extends Model
{
    public $timestamps = false;

    protected $table = 'api_usage';

    protected $fillable = [
        'story_id',
        'service',
        'operation',
        'input_tokens',
        'output_tokens',
        'cost_cents',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function scopeForStory(Builder $query, int $storyId): Builder
    {
        return $query->where('story_id', $storyId);
    }

    public function scopeForService(Builder $query, string $service): Builder
    {
        return $query->where('service', $service);
    }

    public function scopeAnthropic(Builder $query): Builder
    {
        return $query->where('service', 'anthropic');
    }

    public function scopeNanoBanana(Builder $query): Builder
    {
        return $query->where('service', 'nano_banana');
    }

    public function scopeKling(Builder $query): Builder
    {
        return $query->where('service', 'kling');
    }

    public function getCostDollarsAttribute(): ?float
    {
        return $this->cost_cents !== null ? $this->cost_cents / 100 : null;
    }
}
