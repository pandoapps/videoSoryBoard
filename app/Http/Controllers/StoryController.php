<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use App\Http\Requests\StoreStoryRequest;
use App\Models\Story;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoryController extends Controller
{
    public function index(Request $request): Response
    {
        $stories = $request->user()
            ->stories()
            ->latest()
            ->paginate(12);

        return Inertia::render('Stories/Index', [
            'stories' => $stories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Stories/Create');
    }

    public function store(StoreStoryRequest $request)
    {
        $story = $request->user()->stories()->create([
            'title' => $request->title,
            'synopsis' => $request->synopsis,
            'status' => StoryStatus::Pending,
        ]);

        return redirect()->route('stories.show', $story)
            ->with('success', 'Story created successfully.');
    }

    public function show(Request $request, Story $story): Response
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $story->load(['characters', 'storyboardFrames', 'latestVideo', 'miniVideos.frameFrom', 'miniVideos.frameTo']);

        return Inertia::render('Stories/Show', [
            'story' => $story,
            'usageSummary' => $story->apiUsageSummary(),
        ]);
    }

    public function destroy(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        $story->delete();

        return redirect()->route('stories.index')
            ->with('success', 'Story deleted successfully.');
    }
}
