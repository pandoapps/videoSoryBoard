<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\StoryboardFrame;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3 {--dry-run : Show what would be moved without making changes}';
    protected $description = 'Reorganize S3 files into per-story folders and update DB references';

    private int $moved = 0;
    private int $skipped = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $s3 = Storage::disk('s3');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        $this->info('=== Migrating Characters ===');
        Character::whereNotNull('image_path')->with('story')->chunkById(100, function ($characters) use ($s3, $dryRun) {
            foreach ($characters as $character) {
                $oldPath = $character->image_path;
                $newPath = "stories/{$character->story_id}/characters/" . basename($oldPath);

                if ($oldPath === $newPath) {
                    $this->skipped++;
                    continue;
                }

                $this->moveAndUpdate($s3, $oldPath, $newPath, $character, 'image_path', 'image_url', $dryRun);
            }
        });

        $this->info('=== Migrating Storyboard Frames ===');
        StoryboardFrame::whereNotNull('image_path')->with('story')->chunkById(100, function ($frames) use ($s3, $dryRun) {
            foreach ($frames as $frame) {
                $oldPath = $frame->image_path;
                $newPath = "stories/{$frame->story_id}/storyboard-frames/" . basename($oldPath);

                if ($oldPath === $newPath) {
                    $this->skipped++;
                    continue;
                }

                $this->moveAndUpdate($s3, $oldPath, $newPath, $frame, 'image_path', 'image_url', $dryRun);
            }
        });

        $this->info('=== Migrating Videos ===');
        Video::whereNotNull('video_path')->chunkById(100, function ($videos) use ($s3, $dryRun) {
            foreach ($videos as $video) {
                $oldPath = $video->video_path;
                $newPath = "stories/{$video->story_id}/videos/" . basename($oldPath);

                if ($oldPath === $newPath) {
                    $this->skipped++;
                    continue;
                }

                $this->moveAndUpdate($s3, $oldPath, $newPath, $video, 'video_path', 'video_url', $dryRun);
            }
        });

        $this->newLine();
        $this->info("Done! Moved: {$this->moved}, Skipped: {$this->skipped}, Errors: {$this->errors}");

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function moveAndUpdate($s3, string $oldPath, string $newPath, $model, string $pathColumn, string $urlColumn, bool $dryRun): void
    {
        if ($dryRun) {
            $this->line("  [dry-run] {$oldPath} -> {$newPath}");
            $this->moved++;
            return;
        }

        if (! $s3->exists($oldPath)) {
            $this->warn("  [missing] {$oldPath} — not found on S3, updating DB path only");
            $model->update([
                $pathColumn => $newPath,
                $urlColumn => $s3->url($newPath),
            ]);
            $this->errors++;
            return;
        }

        try {
            $s3->copy($oldPath, $newPath);

            $model->update([
                $pathColumn => $newPath,
                $urlColumn => $s3->url($newPath),
            ]);

            $s3->delete($oldPath);

            $this->line("  [moved] {$oldPath} -> {$newPath}");
            $this->moved++;
        } catch (\Throwable $e) {
            $this->error("  [error] {$oldPath}: {$e->getMessage()}");
            $this->errors++;
        }
    }
}
