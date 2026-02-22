<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NanaBananaService
{
    private const BASE_URL = 'https://api.nanobananaapi.ai/api/v1/nanobanana';

    private string $apiKey = '';

    public function __construct(private ApiKeyVault $vault) {}

    public function forUser(int $userId): static
    {
        $this->apiKey = $this->vault->get('nano_banana', $userId) ?? '';

        return $this;
    }

    /**
     * Generate a character image using the Pro endpoint.
     */
    public function generateCharacterImage(string $prompt, string $resolution = '2K'): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::BASE_URL . '/generate-pro', [
            'prompt' => $prompt,
            'resolution' => $resolution,
        ]);

        if (! $response->successful()) {
            Log::error('Nano Banana generate-pro failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to generate character image: ' . $response->body());
        }

        $data = $response->json();

        if (! in_array($data['code'] ?? 0, [0, 200], true)) {
            $msg = $data['msg'] ?? 'Unknown API error';
            Log::error('Nano Banana generate-pro API error', ['code' => $data['code'], 'msg' => $msg]);
            throw new \RuntimeException("Nano Banana API error: {$msg}");
        }

        return $data['data']['taskId'] ?? throw new \RuntimeException('No taskId in response');
    }

    /**
     * Generate a storyboard frame image with character reference images.
     */
    public function generateStoryboardFrame(string $prompt, array $referenceImageUrls = [], string $resolution = '2K'): string
    {
        $body = [
            'prompt' => $prompt,
            'resolution' => $resolution,
        ];

        if (! empty($referenceImageUrls)) {
            $body['imageUrls'] = $referenceImageUrls;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::BASE_URL . '/generate-pro', $body);

        if (! $response->successful()) {
            Log::error('Nano Banana generate-pro (storyboard) failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to generate storyboard frame: ' . $response->body());
        }

        $data = $response->json();

        if (! in_array($data['code'] ?? 0, [0, 200], true)) {
            $msg = $data['msg'] ?? 'Unknown API error';
            Log::error('Nano Banana generate-pro (storyboard) API error', ['code' => $data['code'], 'msg' => $msg]);
            throw new \RuntimeException("Nano Banana API error: {$msg}");
        }

        return $data['data']['taskId'] ?? throw new \RuntimeException('No taskId in response');
    }

    /**
     * Check the status of a generation task.
     *
     * @return array{status: string, image_url: string|null, error: string|null}
     */
    public function getTaskStatus(string $taskId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->timeout(15)->get(self::BASE_URL . '/record-info', [
            'taskId' => $taskId,
        ]);

        if (! $response->successful()) {
            Log::error('Nano Banana record-info failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to check task status: ' . $response->body());
        }

        $data = $response->json('data');
        $successFlag = $data['successFlag'] ?? 0;

        return match ($successFlag) {
            0 => ['status' => 'generating', 'image_url' => null, 'error' => null],
            1 => [
                'status' => 'success',
                'image_url' => $data['response']['resultImageUrl'] ?? $data['response']['originImageUrl'] ?? null,
                'error' => null,
            ],
            2 => ['status' => 'failed', 'image_url' => null, 'error' => $data['errorMessage'] ?? 'Creation failed'],
            3 => ['status' => 'failed', 'image_url' => null, 'error' => $data['errorMessage'] ?? 'Generation failed'],
            default => ['status' => 'unknown', 'image_url' => null, 'error' => 'Unknown status flag: ' . $successFlag],
        };
    }
}
