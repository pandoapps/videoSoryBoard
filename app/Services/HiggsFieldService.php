<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HiggsFieldService
{
    private const BASE_URL = 'https://cloud.higgsfield.ai/v1';

    private string $apiKey = '';

    public function __construct(private ApiKeyVault $vault) {}

    public function forUser(int $userId): static
    {
        $this->apiKey = $this->vault->get('higgsfield', $userId) ?? '';

        return $this;
    }

    /**
     * Submit a video generation job.
     *
     * @return string The generation ID
     */
    public function submitGeneration(array $imageUrls, array $params = []): string
    {
        $body = array_merge([
            'image_urls' => $imageUrls,
            'type' => 'image_to_video',
        ], $params);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::BASE_URL . '/generations', $body);

        if (! $response->successful()) {
            Log::error('Higgsfield submit generation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to submit video generation: ' . $response->body());
        }

        $data = $response->json();

        return $data['id'] ?? throw new \RuntimeException('No generation ID in response');
    }

    /**
     * Check the status of a generation job.
     *
     * @return array{status: string, video_url: string|null, duration: int|null, error: string|null}
     */
    public function checkStatus(string $generationId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->timeout(15)->get(self::BASE_URL . "/generations/{$generationId}");

        if (! $response->successful()) {
            Log::error('Higgsfield check status failed', [
                'generation_id' => $generationId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to check video status: ' . $response->body());
        }

        $data = $response->json();
        $status = $data['status'] ?? 'unknown';

        return match ($status) {
            'pending', 'processing' => [
                'status' => 'processing',
                'video_url' => null,
                'duration' => null,
                'error' => null,
            ],
            'completed', 'done', 'success' => [
                'status' => 'completed',
                'video_url' => $data['output']['video_url'] ?? $data['video_url'] ?? null,
                'duration' => $data['output']['duration'] ?? $data['duration'] ?? null,
                'error' => null,
            ],
            'failed', 'error' => [
                'status' => 'failed',
                'video_url' => null,
                'duration' => null,
                'error' => $data['error'] ?? $data['message'] ?? 'Video generation failed',
            ],
            default => [
                'status' => 'processing',
                'video_url' => null,
                'duration' => null,
                'error' => null,
            ],
        };
    }
}
