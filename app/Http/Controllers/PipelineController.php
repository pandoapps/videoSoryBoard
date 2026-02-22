<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStage;
use App\Enums\StoryStatus;
use App\Jobs\ConcatenateVideos;
use App\Jobs\RegenerateCharacterImage;
use App\Jobs\RegenerateMiniVideo;
use App\Jobs\RegenerateStoryboardFrame;
use App\Models\Character;
use App\Models\Story;
use App\Models\StoryboardFrame;
use App\Models\Video;
use App\Services\ApiKeyVault;
use App\Services\MediaStorageService;
use App\Services\PipelineOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct(
        private PipelineOrchestrator $orchestrator,
        private ApiKeyVault $vault,
    ) {}

    public function show(Request $request, Story $story): Response
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $story->load(['chatMessages', 'characters', 'storyboardFrames', 'miniVideos.frameFrom', 'miniVideos.frameTo', 'finalVideo']);

        return Inertia::render('Pipeline/PipelineView', [
            'story' => $story,
            'stages' => $this->orchestrator->getStageStatus($story),
            'klingConfigured' => $this->vault->has('kling', $request->user()->id),
        ]);
    }

    public function start(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($story->full_script !== null, 422, 'Script must be finalized first.');

        $this->orchestrator->startPipeline($story);

        return redirect()->route('stories.pipeline', $story)
            ->with('success', 'Pipeline started!');
    }

    public function status(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $story->load(['chatMessages', 'characters', 'storyboardFrames', 'miniVideos.frameFrom', 'miniVideos.frameTo', 'finalVideo']);

        return response()->json([
            'story' => $story,
            'stages' => $this->orchestrator->getStageStatus($story),
            'klingConfigured' => $this->vault->has('kling', $request->user()->id),
        ]);
    }

    public function approveCharacters(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($story->status === StoryStatus::CharacterReview, 422, 'Characters are not awaiting review.');

        $this->orchestrator->advanceToStage($story, PipelineStage::Storyboard);

        return redirect()->route('stories.pipeline', $story)
            ->with('success', 'Characters approved! Starting storyboard generation.');
    }

    public function approveStoryboard(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($story->status === StoryStatus::StoryboardReview, 422, 'Storyboard is not awaiting review.');
        abort_unless($this->vault->has('kling', $request->user()->id), 422, 'Kling API key not configured. Add it in Settings before starting video generation.');

        $this->orchestrator->advanceToStage($story, PipelineStage::Video);

        return redirect()->route('stories.pipeline', $story)
            ->with('success', 'Storyboard approved! Starting video generation.');
    }

    public function revertToStage(Request $request, Story $story, string $stage)
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $targetStage = PipelineStage::tryFrom($stage);
        abort_unless($targetStage !== null, 404, 'Invalid pipeline stage.');
        abort_unless($targetStage !== PipelineStage::Video, 422, 'Cannot revert to the final stage.');

        // Ensure no stage is currently processing
        $stageStatuses = $this->orchestrator->getStageStatus($story);
        $hasInProgress = collect($stageStatuses)->contains('status', 'in_progress');
        abort_if($hasInProgress, 422, 'Cannot revert while a stage is in progress.');

        // Ensure the story is beyond the target stage
        $stageOrder = [
            PipelineStage::Script->value => 0,
            PipelineStage::Characters->value => 1,
            PipelineStage::Storyboard->value => 2,
            PipelineStage::Video->value => 3,
        ];

        $targetOrder = $stageOrder[$targetStage->value];
        $targetStageStatus = collect($stageStatuses)->firstWhere('stage', $targetStage->value);
        abort_unless(
            $targetStageStatus && $targetStageStatus['status'] === 'completed',
            422,
            'Can only revert stages that have been completed.'
        );

        $this->orchestrator->revertToStage($story, $targetStage);

        if ($targetStage === PipelineStage::Script) {
            return redirect()->route('stories.chat', $story)
                ->with('success', 'Reverted to script editing. Continue the conversation to refine your script.');
        }

        return redirect()->route('stories.pipeline', $story)
            ->with('success', "Reverted to {$targetStage->value} stage.");
    }

    public function regenerateCharacter(Request $request, Story $story, Character $character): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($character->story_id === $story->id, 404);

        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $metadata = $character->metadata ?? [];
        unset($metadata['error']);
        $metadata['regenerating'] = true;

        $character->update([
            'description' => $request->input('prompt'),
            'metadata' => $metadata,
        ]);

        RegenerateCharacterImage::dispatch($character, $request->input('prompt'))->onQueue('pipeline');

        return response()->json(['message' => 'Character regeneration started.']);
    }

    public function createCharacter(Request $request, Story $story): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($story->status === StoryStatus::CharacterReview, 422, 'Characters are not awaiting review.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
        ]);

        $character = $story->characters()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'metadata' => ['regenerating' => true],
        ]);

        RegenerateCharacterImage::dispatch($character, $validated['description'])->onQueue('pipeline');

        return response()->json(['character' => $character], 201);
    }

    public function updateCharacter(Request $request, Story $story, Character $character): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($story->status === StoryStatus::CharacterReview, 422, 'Characters are not awaiting review.');
        abort_unless($character->story_id === $story->id, 404);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
        ]);

        $character->update($validated);

        return response()->json(['character' => $character]);
    }

    public function deleteCharacter(Request $request, Story $story, Character $character): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($story->status === StoryStatus::CharacterReview, 422, 'Characters are not awaiting review.');
        abort_unless($character->story_id === $story->id, 404);

        $character->delete();

        return response()->json(['message' => 'Character deleted.']);
    }

    public function regenerateMiniVideo(Request $request, Story $story, Video $video): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($video->story_id === $story->id, 404);
        abort_unless(! $video->is_final && $video->sequence_number !== null, 422, 'Can only regenerate mini-videos.');

        $request->validate([
            'prompt' => 'nullable|string|max:2000',
            'duration' => 'nullable|string|in:5,10',
            'model_name' => 'nullable|string|in:kling-v2-6,kling-v2-5-turbo,kling-v2-1-master,kling-v2-master,kling-v1-6,kling-v1',
            'mode' => 'nullable|string|in:std,pro',
            'camera_control' => 'nullable|string|in:simple,down_back,forward_up,right_turn_forward,left_turn_forward',
        ]);

        $klingParams = array_filter([
            'duration' => $request->input('duration'),
            'model_name' => $request->input('model_name'),
            'mode' => $request->input('mode'),
            'camera_control' => $request->input('camera_control'),
        ]);

        RegenerateMiniVideo::dispatch($video, $request->input('prompt'), $klingParams)->onQueue('pipeline');

        return response()->json(['message' => 'Mini-video regeneration started.']);
    }

    public function concatenateVideos(Request $request, Story $story): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $miniVideos = $story->miniVideos()->get();
        abort_if($miniVideos->isEmpty(), 422, 'No mini-videos to concatenate.');

        $allCompleted = $miniVideos->every(fn ($v) => $v->status === 'completed');
        abort_unless($allCompleted, 422, 'All mini-videos must be completed before concatenation.');

        ConcatenateVideos::dispatch($story)->onQueue('pipeline');

        return response()->json(['message' => 'Video concatenation started.']);
    }

    public function uploadCharacterImage(Request $request, Story $story, Character $character): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($character->story_id === $story->id, 404);

        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $mediaStorage = app(MediaStorageService::class);
        $stored = $mediaStorage->storeFromPath(
            $request->file('image')->getRealPath(),
            "stories/{$story->id}/characters",
            $request->file('image')->guessExtension() ?? 'jpg',
        );

        $character->update([
            'image_path' => $stored['path'],
            'image_url' => $stored['url'],
        ]);

        return response()->json(['message' => 'Image uploaded.', 'image_url' => $character->image_url]);
    }

    public function uploadClipVideo(Request $request, Story $story, Video $video): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($video->story_id === $story->id, 404);

        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/webm|max:102400',
        ]);

        $mediaStorage = app(MediaStorageService::class);
        $stored = $mediaStorage->storeFromPath(
            $request->file('video')->getRealPath(),
            "stories/{$story->id}/videos",
            $request->file('video')->guessExtension() ?? 'mp4',
        );

        $video->update([
            'video_path' => $stored['path'],
            'video_url' => $stored['url'],
            'status' => 'completed',
        ]);

        // Delete existing final video since a clip changed
        $story->videos()->where('is_final', true)->delete();

        return response()->json(['message' => 'Video uploaded.', 'video_url' => $video->video_url]);
    }

    public function uploadFrameImage(Request $request, Story $story, StoryboardFrame $frame): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($frame->story_id === $story->id, 404);

        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $mediaStorage = app(MediaStorageService::class);
        $stored = $mediaStorage->storeFromPath(
            $request->file('image')->getRealPath(),
            "stories/{$story->id}/storyboard-frames",
            $request->file('image')->guessExtension() ?? 'jpg',
        );

        $frame->update([
            'image_path' => $stored['path'],
            'image_url' => $stored['url'],
        ]);

        $this->invalidateAffectedClips($frame->id);

        return response()->json(['message' => 'Image uploaded.', 'image_url' => $frame->image_url]);
    }

    public function deleteFrame(Request $request, Story $story, StoryboardFrame $frame): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($frame->story_id === $story->id, 404);

        if ($frame->image_path) {
            app(MediaStorageService::class)->delete($frame->image_path);
        }

        $frame->delete();

        // Re-sequence remaining frames to eliminate gaps
        $story->storyboardFrames()
            ->orderBy('sequence_number')
            ->get()
            ->values()
            ->each(fn ($f, $i) => $f->update(['sequence_number' => $i + 1]));

        // Rebuild clip chain since frame pairs changed
        $this->rebuildClipsAfterFrameDelete($story);

        return response()->json(['message' => 'Frame deleted.']);
    }

    /**
     * Mark mini-video clips that reference the given frame as stale.
     * The clips are preserved but flagged so the user knows they may be outdated.
     */
    private function invalidateAffectedClips(int $frameId): void
    {
        $clips = Video::where(function ($q) use ($frameId) {
            $q->where('frame_from_id', $frameId)
              ->orWhere('frame_to_id', $frameId);
        })->where('is_final', false)->get();

        foreach ($clips as $clip) {
            $metadata = $clip->metadata ?? [];
            $metadata['stale'] = true;
            $clip->update(['metadata' => $metadata]);
        }
    }

    /**
     * After a frame is deleted, reconcile the clip chain:
     * keep clips whose frame pairs still match, create new ones for new pairs, remove orphans.
     */
    private function rebuildClipsAfterFrameDelete(Story $story): void
    {
        $frames = $story->storyboardFrames()->orderBy('sequence_number')->get();

        // Index existing clips by "fromId-toId" for fast lookup
        $existingClips = $story->miniVideos()->get()->keyBy(fn ($v) => $v->frame_from_id . '-' . $v->frame_to_id);
        $usedKeys = [];

        if ($frames->count() >= 2) {
            for ($i = 0; $i < $frames->count() - 1; $i++) {
                $fromId = $frames[$i]->id;
                $toId = $frames[$i + 1]->id;
                $key = "{$fromId}-{$toId}";

                if ($existingClips->has($key)) {
                    // Pair unchanged — keep clip, just update sequence_number
                    $existingClips[$key]->update(['sequence_number' => $i + 1]);
                } else {
                    // New pair — create clip
                    $story->videos()->create([
                        'sequence_number' => $i + 1,
                        'is_final' => false,
                        'prompt' => "Smooth cinematic transition from frame {$frames[$i]->sequence_number} to frame {$frames[$i + 1]->sequence_number}",
                        'frame_from_id' => $fromId,
                        'frame_to_id' => $toId,
                        'status' => 'queued',
                    ]);
                }

                $usedKeys[] = $key;
            }
        }

        // Delete orphaned clips (pairs that no longer exist)
        foreach ($existingClips as $key => $clip) {
            if (! in_array($key, $usedKeys)) {
                $clip->delete();
            }
        }

        // Invalidate final video since clip chain changed
        $story->videos()->where('is_final', true)->delete();
    }

    public function regenerateFrame(Request $request, Story $story, StoryboardFrame $frame): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        abort_unless($frame->story_id === $story->id, 404);

        $request->validate([
            'prompt' => 'required|string|max:2000',
        ]);

        $prompt = $request->input('prompt');

        $metadata = $frame->metadata ?? [];
        unset($metadata['error']);
        $metadata['regenerating'] = true;

        $frame->update([
            'prompt' => $prompt,
            'metadata' => $metadata,
        ]);

        RegenerateStoryboardFrame::dispatch($frame, $prompt)->onQueue('pipeline');

        $this->invalidateAffectedClips($frame->id);

        return response()->json(['message' => 'Frame regeneration started.']);
    }
}
