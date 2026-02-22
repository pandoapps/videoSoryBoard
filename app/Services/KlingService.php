<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KlingService
{
    private const BASE_URL = 'https://api-singapore.klingai.com';
    private const TOKEN_TTL = 1800; // 30 minutes
    private const TOKEN_CACHE_KEY = 'kling_jwt_token';

    private ?string $accessKey = null;
    private ?string $secretKey = null;
    private ?int $userId = null;

    public function __construct(private ApiKeyVault $vault) {}

    public function forUser(int $userId): static
    {
        $this->userId = $userId;
        $raw = $this->vault->get('kling', $userId) ?? '';
        $parts = explode(':', $raw, 2);
        $this->accessKey = $parts[0] ?: null;
        $this->secretKey = $parts[1] ?? null;

        return $this;
    }

    /**
     * Submit an image-to-video generation task.
     *
     * @param  string[]  $imageUrls  One or two image URLs
     * @param  array     $params     Optional: prompt, duration, mode, etc.
     * @return string    The task ID
     */
    public function submitGeneration(array $imageUrls, array $params = []): string
    {
        $body = [
            'model_name' => $params['model_name'] ?? 'kling-v2-6',
            'image' => $imageUrls[0],
            'mode' => $params['mode'] ?? 'std',
            'duration' => (string) ($params['duration'] ?? '5'),
        ];

        if (isset($imageUrls[1])) {
            $body['image_tail'] = $imageUrls[1];
        }

        if (! empty($params['prompt'])) {
            $body['prompt'] = $params['prompt'];
        }

        if (! empty($params['negative_prompt'])) {
            $body['negative_prompt'] = $params['negative_prompt'];
        }

        if (! empty($params['aspect_ratio'])) {
            $body['aspect_ratio'] = $params['aspect_ratio'];
        }

        if (! empty($params['cfg_scale'])) {
            $body['cfg_scale'] = $params['cfg_scale'];
        }

        if (! empty($params['enable_audio'])) {
            $body['enable_audio'] = true;
        }

        if (! empty($params['camera_control'])) {
            $body['camera_control'] = [
                'type' => $params['camera_control'],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->getToken()}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::BASE_URL . '/v1/videos/image2video', $body);

        if (! $response->successful()) {
            Log::error('Kling submit generation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to submit video generation: ' . $response->body());
        }

        $data = $response->json();

        if (($data['code'] ?? -1) !== 0) {
            $msg = $data['message'] ?? 'Unknown error';
            Log::error('Kling API error', ['code' => $data['code'] ?? null, 'message' => $msg]);
            throw new \RuntimeException("Kling API error: {$msg}");
        }

        return $data['data']['task_id']
            ?? throw new \RuntimeException('No task_id in Kling response');
    }

    /**
     * Check the status of a generation task.
     *
     * @return array{status: string, video_url: string|null, duration: int|null, error: string|null}
     */
    public function checkStatus(string $taskId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->getToken()}",
        ])->timeout(15)->get(self::BASE_URL . "/v1/videos/image2video/{$taskId}");

        if (! $response->successful()) {
            Log::error('Kling check status failed', [
                'task_id' => $taskId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to check video status: ' . $response->body());
        }

        $data = $response->json();
        $taskData = $data['data'] ?? [];
        $taskStatus = $taskData['task_status'] ?? 'unknown';

        return match ($taskStatus) {
            'submitted', 'processing' => [
                'status' => 'processing',
                'video_url' => null,
                'duration' => null,
                'error' => null,
            ],
            'succeed' => [
                'status' => 'completed',
                'video_url' => $taskData['task_result']['videos'][0]['url'] ?? null,
                'duration' => isset($taskData['task_result']['videos'][0]['duration'])
                    ? (int) $taskData['task_result']['videos'][0]['duration']
                    : null,
                'error' => null,
            ],
            'failed' => [
                'status' => 'failed',
                'video_url' => null,
                'duration' => null,
                'error' => $taskData['task_status_msg'] ?? 'Video generation failed',
            ],
            default => [
                'status' => 'processing',
                'video_url' => null,
                'duration' => null,
                'error' => null,
            ],
        };
    }

    /**
     * Generate a JWT token for Kling API authentication.
     * Cached for 25 minutes (token valid for 30).
     */
    private function getToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY . ".{$this->userId}", 1500, function () {
            if (! $this->accessKey || ! $this->secretKey) {
                throw new \RuntimeException('Kling API credentials not configured.');
            }

            $now = time();
            $payload = [
                'iss' => $this->accessKey,
                'exp' => $now + self::TOKEN_TTL,
                'nbf' => $now - 5,
                'iat' => $now,
            ];

            return JWT::encode($payload, $this->secretKey, 'HS256');
        });
    }
}
