<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $stories = $user->stories()
            ->with('finalVideo')
            ->latest()
            ->limit(5)
            ->get()
            ->each(function ($story) {
                $story->total_cost_cents = $story->apiUsageSummary()['total_cost_cents'];
            });

        $stats = [
            'total' => $user->stories()->count(),
            'in_progress' => $user->stories()->whereNotIn('status', [
                StoryStatus::Completed->value,
                StoryStatus::Failed->value,
                StoryStatus::Pending->value,
            ])->count(),
            'completed' => $user->stories()->where('status', StoryStatus::Completed->value)->count(),
        ];

        return Inertia::render('Dashboard', [
            'recentStories' => $stories,
            'stats' => $stats,
        ]);
    }
}
