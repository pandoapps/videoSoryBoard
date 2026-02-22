<?php

namespace App\Services;

use App\Models\Story;
use App\Models\Video;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoConcatenationService
{
    public function __construct(private MediaStorageService $mediaStorage) {}

    /**
     * Concatenate all completed mini-videos for a story into a single final video.
     *
     * Algorithm:
     *  1. Collect all completed mini-videos ordered by sequence_number
     *  2. Download each clip to a temp directory
     *  3. Probe each clip to discover resolution, fps, codec
     *  4. Pick a uniform target format (highest resolution, common fps)
     *  5. Re-encode each clip to the target format using ffmpeg
     *     (uniform codec, resolution, fps, pixel format)
     *  6. Concatenate all normalised clips using ffmpeg concat demuxer
     *  7. Get final duration via ffprobe
     *  8. Upload the result to storage (S3 or local)
     *  9. Cleanup temp files
     *
     * @return array{path: string, url: string, duration: int}
     */
    public function concatenate(Story $story): array
    {
        $miniVideos = $story->miniVideos()
            ->where('status', 'completed')
            ->whereNotNull('video_url')
            ->orderBy('sequence_number')
            ->get();

        if ($miniVideos->isEmpty()) {
            throw new \RuntimeException('No completed mini-videos to concatenate.');
        }

        if ($miniVideos->count() === 1) {
            return $this->handleSingleVideo($story, $miniVideos->first());
        }

        $tempDir = storage_path("app/temp/concat_{$story->id}_" . time());

        try {
            $this->ensureTempDir($tempDir);

            // Step 1-2: Download all clips
            Log::info('Concatenation: downloading clips', [
                'story_id' => $story->id,
                'clip_count' => $miniVideos->count(),
            ]);
            $downloadedFiles = $this->downloadClips($miniVideos, $tempDir);

            // Step 3: Probe each clip
            $probes = [];
            foreach ($downloadedFiles as $file) {
                $probes[$file] = $this->probeVideo($file);
            }

            // Step 4: Determine target format
            $target = $this->determineTargetFormat($probes);

            Log::info('Concatenation: target format', [
                'story_id' => $story->id,
                'width' => $target['width'],
                'height' => $target['height'],
                'fps' => $target['fps'],
            ]);

            // Step 5: Re-encode each clip to uniform format
            $normalisedFiles = [];
            foreach ($downloadedFiles as $i => $file) {
                $normalisedPath = "{$tempDir}/norm_{$i}.mp4";
                $this->normaliseClip($file, $normalisedPath, $target);
                $normalisedFiles[] = $normalisedPath;
            }

            // Step 6: Concatenate to a temp file
            $outputPath = "{$tempDir}/final_{$story->id}.mp4";
            $this->concatClips($normalisedFiles, $outputPath, $tempDir);

            // Step 7: Get duration
            $duration = $this->getVideoDuration($outputPath);

            // Step 8: Upload to storage
            $stored = $this->mediaStorage->storeFromPath($outputPath, "stories/{$story->id}/videos", 'mp4');

            Log::info('Concatenation: success', [
                'story_id' => $story->id,
                'duration' => $duration,
                'path' => $stored['path'],
            ]);

            return [
                'path' => $stored['path'],
                'url' => $stored['url'],
                'duration' => $duration,
            ];
        } finally {
            // Step 9: Cleanup
            $this->cleanupTempDir($tempDir);
        }
    }

    /**
     * When there's only one clip, just re-upload it as the final video (no concat needed).
     */
    private function handleSingleVideo(Story $story, Video $video): array
    {
        $response = Http::timeout(120)->get($video->video_url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download video {$video->id}: HTTP {$response->status()}");
        }

        $tempPath = storage_path("app/temp/single_{$story->id}_" . time() . '.mp4');
        $tempDir = dirname($tempPath);

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        file_put_contents($tempPath, $response->body());

        try {
            $duration = $video->duration_seconds ?? $this->getVideoDuration($tempPath);
            $stored = $this->mediaStorage->storeFromPath($tempPath, "stories/{$story->id}/videos", 'mp4');

            return [
                'path' => $stored['path'],
                'url' => $stored['url'],
                'duration' => $duration,
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    // ─── Download ────────────────────────────────────────────────

    /**
     * Download each mini-video URL to temp files.
     *
     * @return string[] Paths to downloaded files, in order.
     */
    private function downloadClips(Collection $miniVideos, string $tempDir): array
    {
        $files = [];

        foreach ($miniVideos->values() as $i => $video) {
            $filepath = "{$tempDir}/clip_{$i}.mp4";

            $response = Http::timeout(120)->get($video->video_url);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "Failed to download mini-video {$video->id} (seq {$video->sequence_number}): HTTP {$response->status()}"
                );
            }

            file_put_contents($filepath, $response->body());

            if (filesize($filepath) === 0) {
                throw new \RuntimeException("Downloaded empty file for mini-video {$video->id}");
            }

            $files[] = $filepath;
        }

        return $files;
    }

    // ─── Probe ───────────────────────────────────────────────────

    /**
     * Probe a video file to get resolution, fps, codec, pixel_format.
     *
     * @return array{width: int, height: int, fps: float, codec: string, pix_fmt: string}
     */
    private function probeVideo(string $filePath): array
    {
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height,r_frame_rate,codec_name,pix_fmt',
            '-of', 'json',
            $filePath,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('ffprobe failed, using defaults', [
                'file' => basename($filePath),
                'stderr' => $process->getErrorOutput(),
            ]);

            return [
                'width' => 1280,
                'height' => 720,
                'fps' => 30.0,
                'codec' => 'unknown',
                'pix_fmt' => 'yuv420p',
            ];
        }

        $json = json_decode($process->getOutput(), true);
        $stream = $json['streams'][0] ?? [];

        // Parse r_frame_rate like "30/1" or "24000/1001"
        $fps = 30.0;
        if (! empty($stream['r_frame_rate'])) {
            $parts = explode('/', $stream['r_frame_rate']);
            if (count($parts) === 2 && (int) $parts[1] > 0) {
                $fps = round((int) $parts[0] / (int) $parts[1], 2);
            }
        }

        return [
            'width' => (int) ($stream['width'] ?? 1280),
            'height' => (int) ($stream['height'] ?? 720),
            'fps' => $fps,
            'codec' => $stream['codec_name'] ?? 'unknown',
            'pix_fmt' => $stream['pix_fmt'] ?? 'yuv420p',
        ];
    }

    // ─── Target Format ───────────────────────────────────────────

    /**
     * Given probe data from all clips, pick a uniform target:
     *  - Resolution: the most common (mode) among clips
     *  - FPS: the most common (mode) among clips
     *  - Codec: always h264 (widest compatibility)
     *  - Pixel format: always yuv420p
     */
    private function determineTargetFormat(array $probes): array
    {
        $widths = [];
        $fpsValues = [];

        foreach ($probes as $probe) {
            $key = "{$probe['width']}x{$probe['height']}";
            $widths[$key] = ($widths[$key] ?? 0) + 1;
            $fpsValues[(string) $probe['fps']] = ($fpsValues[(string) $probe['fps']] ?? 0) + 1;
        }

        // Pick the most common resolution
        arsort($widths);
        $topRes = array_key_first($widths);
        [$w, $h] = explode('x', $topRes);

        // Pick the most common fps
        arsort($fpsValues);
        $topFps = (float) array_key_first($fpsValues);

        // Ensure even dimensions (required by h264)
        $w = (int) $w;
        $h = (int) $h;
        if ($w % 2 !== 0) {
            $w++;
        }
        if ($h % 2 !== 0) {
            $h++;
        }

        return [
            'width' => $w,
            'height' => $h,
            'fps' => $topFps,
            'codec' => 'libx264',
            'pix_fmt' => 'yuv420p',
        ];
    }

    // ─── Normalise ───────────────────────────────────────────────

    /**
     * Re-encode a single clip to the uniform target format.
     * Scales/pads to target resolution (letterbox), sets fps and codec.
     */
    private function normaliseClip(string $inputPath, string $outputPath, array $target): void
    {
        $w = $target['width'];
        $h = $target['height'];
        $fps = $target['fps'];

        // scale + pad filter: fit inside target size and pad to exact target
        $filterComplex = "scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps={$fps}";

        $process = new Process([
            'ffmpeg', '-y',
            '-i', $inputPath,
            '-vf', $filterComplex,
            '-c:v', $target['codec'],
            '-preset', 'fast',
            '-crf', '23',
            '-pix_fmt', $target['pix_fmt'],
            '-c:a', 'aac',
            '-b:a', '128k',
            '-ar', '44100',
            '-ac', '2',
            '-movflags', '+faststart',
            '-shortest',
            $outputPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            // Fallback: try without audio stream (some clips may be silent)
            $fallback = new Process([
                'ffmpeg', '-y',
                '-i', $inputPath,
                '-vf', $filterComplex,
                '-c:v', $target['codec'],
                '-preset', 'fast',
                '-crf', '23',
                '-pix_fmt', $target['pix_fmt'],
                '-an',
                '-movflags', '+faststart',
                $outputPath,
            ]);
            $fallback->setTimeout(120);
            $fallback->run();

            if (! $fallback->isSuccessful()) {
                Log::error('ffmpeg normalisation failed', [
                    'input' => basename($inputPath),
                    'stderr' => $fallback->getErrorOutput(),
                ]);
                throw new ProcessFailedException($fallback);
            }
        }
    }

    // ─── Concat ──────────────────────────────────────────────────

    /**
     * Concatenate normalised clips using the ffmpeg concat demuxer.
     * Since all clips are now uniform, -c copy is safe.
     */
    private function concatClips(array $normalisedFiles, string $outputPath, string $tempDir): void
    {
        // Build concat list
        $listPath = "{$tempDir}/concat_list.txt";
        $listContent = '';
        foreach ($normalisedFiles as $file) {
            $escaped = str_replace("'", "'\\''", $file);
            $listContent .= "file '{$escaped}'\n";
        }
        file_put_contents($listPath, $listContent);

        $process = new Process([
            'ffmpeg', '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listPath,
            '-c', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('ffmpeg concat failed', [
                'stderr' => $process->getErrorOutput(),
            ]);
            throw new ProcessFailedException($process);
        }
    }

    // ─── Duration ────────────────────────────────────────────────

    private function getVideoDuration(string $filePath): int
    {
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $filePath,
        ]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            return (int) round((float) trim($process->getOutput()));
        }

        return 0;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function ensureTempDir(string $tempDir): void
    {
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    }

    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = glob("{$dir}/*");
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        @rmdir($dir);
    }
}
