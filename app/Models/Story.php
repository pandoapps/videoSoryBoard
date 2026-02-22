<?php

namespace App\Models;

use App\Enums\PipelineStage;
use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'synopsis',
        'full_script',
        'status',
        'current_stage',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoryStatus::class,
            'current_stage' => PipelineStage::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function storyboardFrames(): HasMany
    {
        return $this->hasMany(StoryboardFrame::class)->orderBy('sequence_number');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function latestVideo(): HasOne
    {
        return $this->hasOne(Video::class)->latestOfMany();
    }

    public function miniVideos(): HasMany
    {
        return $this->hasMany(Video::class)
            ->whereNotNull('sequence_number')
            ->where('is_final', false)
            ->orderBy('sequence_number');
    }

    public function finalVideo(): HasOne
    {
        return $this->hasOne(Video::class)->where('is_final', true)->latestOfMany();
    }

    public function apiUsage(): HasMany
    {
        return $this->hasMany(ApiUsage::class);
    }

    public function apiUsageSummary(): array
    {
        $rows = $this->apiUsage()
            ->select(
                'service',
                DB::raw('COUNT(*) as call_count'),
                DB::raw('COALESCE(SUM(input_tokens), 0) as total_input_tokens'),
                DB::raw('COALESCE(SUM(output_tokens), 0) as total_output_tokens'),
                DB::raw('COALESCE(SUM(cost_cents), 0) as total_cost_cents'),
            )
            ->groupBy('service')
            ->get();

        $summary = [
            'anthropic' => ['call_count' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'cost_cents' => 0],
            'nano_banana' => ['call_count' => 0, 'cost_cents' => 0],
            'kling' => ['call_count' => 0, 'cost_cents' => 0],
            'total_cost_cents' => 0,
        ];

        foreach ($rows as $row) {
            if ($row->service === 'anthropic') {
                $summary['anthropic'] = [
                    'call_count' => (int) $row->call_count,
                    'input_tokens' => (int) $row->total_input_tokens,
                    'output_tokens' => (int) $row->total_output_tokens,
                    'cost_cents' => (int) $row->total_cost_cents,
                ];
                $summary['total_cost_cents'] += (int) $row->total_cost_cents;
            } elseif ($row->service === 'nano_banana') {
                $callCount = (int) $row->call_count;
                $costCents = (int) round($callCount * 9);
                $summary['nano_banana'] = [
                    'call_count' => $callCount,
                    'cost_cents' => $costCents,
                ];
                $summary['total_cost_cents'] += $costCents;
            } elseif ($row->service === 'kling') {
                $callCount = (int) $row->call_count;
                $costCents = (int) round($callCount * 50);
                $summary['kling'] = [
                    'call_count' => $callCount,
                    'cost_cents' => $costCents,
                ];
                $summary['total_cost_cents'] += $costCents;
            } else {
                $summary[$row->service] = [
                    'call_count' => (int) $row->call_count,
                ];
            }
        }

        return $summary;
    }
}
