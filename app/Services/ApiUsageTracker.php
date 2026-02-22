<?php

namespace App\Services;

use App\Models\ApiUsage;

class ApiUsageTracker
{
    // Anthropic pricing for claude-sonnet-4-20250514 (per million tokens)
    private const ANTHROPIC_INPUT_PRICE_PER_M = 3.00;
    private const ANTHROPIC_OUTPUT_PRICE_PER_M = 15.00;

    public function recordAnthropic(
        ?int $storyId,
        string $operation,
        int $inputTokens,
        int $outputTokens,
        ?string $model = null,
    ): ApiUsage {
        $costCents = $this->calculateAnthropicCostCents($inputTokens, $outputTokens);

        return ApiUsage::create([
            'story_id' => $storyId,
            'service' => 'anthropic',
            'operation' => $operation,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'metadata' => $model ? ['model' => $model] : null,
        ]);
    }

    public function recordApiCall(
        ?int $storyId,
        string $service,
        string $operation,
        array $metadata = [],
    ): ApiUsage {
        return ApiUsage::create([
            'story_id' => $storyId,
            'service' => $service,
            'operation' => $operation,
            'metadata' => ! empty($metadata) ? $metadata : null,
        ]);
    }

    private function calculateAnthropicCostCents(int $inputTokens, int $outputTokens): int
    {
        $inputCost = ($inputTokens / 1_000_000) * self::ANTHROPIC_INPUT_PRICE_PER_M;
        $outputCost = ($outputTokens / 1_000_000) * self::ANTHROPIC_OUTPUT_PRICE_PER_M;

        return (int) round(($inputCost + $outputCost) * 100);
    }
}
