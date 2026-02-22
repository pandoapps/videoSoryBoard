<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default');
    }

    /**
     * Download a remote file and store it.
     *
     * @return array{path: string, url: string}
     */
    public function download(string $remoteUrl, string $directory, ?string $extension = null): array
    {
        $extension ??= $this->guessExtension($remoteUrl);
        $filename = Str::uuid() . '.' . $extension;
        $relativePath = "{$directory}/{$filename}";

        $response = Http::timeout(120)->get($remoteUrl);

        if (! $response->successful()) {
            Log::error('Failed to download media file', [
                'url' => $remoteUrl,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException("Failed to download media from: {$remoteUrl}");
        }

        Storage::disk($this->disk)->put($relativePath, $response->body());

        return [
            'path' => $relativePath,
            'url' => Storage::disk($this->disk)->url($relativePath),
        ];
    }

    /**
     * Store raw content (e.g. a concatenated video file).
     *
     * @return array{path: string, url: string}
     */
    public function store(string $content, string $directory, string $extension): array
    {
        $filename = Str::uuid() . '.' . $extension;
        $relativePath = "{$directory}/{$filename}";

        Storage::disk($this->disk)->put($relativePath, $content);

        return [
            'path' => $relativePath,
            'url' => Storage::disk($this->disk)->url($relativePath),
        ];
    }

    /**
     * Store a file from a local path.
     *
     * @return array{path: string, url: string}
     */
    public function storeFromPath(string $localPath, string $directory, ?string $extension = null): array
    {
        $extension ??= pathinfo($localPath, PATHINFO_EXTENSION) ?: 'bin';
        $filename = Str::uuid() . '.' . $extension;
        $relativePath = "{$directory}/{$filename}";

        Storage::disk($this->disk)->put($relativePath, file_get_contents($localPath));

        return [
            'path' => $relativePath,
            'url' => Storage::disk($this->disk)->url($relativePath),
        ];
    }

    /**
     * Get the public URL for a stored file.
     */
    public function url(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Delete a stored file.
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    private function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            return $ext;
        }

        if (in_array($ext, ['mp4', 'webm', 'mov'])) {
            return $ext;
        }

        // Default based on common API outputs
        return 'jpg';
    }
}
