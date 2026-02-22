<?php

namespace App\Http\Controllers;

use App\Models\ApiUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CostsController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        // Per-story breakdown
        $storyUsage = DB::table('api_usage')
            ->join('stories', 'api_usage.story_id', '=', 'stories.id')
            ->where('stories.user_id', $userId)
            ->select(
                'stories.id as story_id',
                'stories.title as story_title',
                'api_usage.service',
                DB::raw('COUNT(*) as call_count'),
                DB::raw('COALESCE(SUM(api_usage.input_tokens), 0) as total_input_tokens'),
                DB::raw('COALESCE(SUM(api_usage.output_tokens), 0) as total_output_tokens'),
                DB::raw('COALESCE(SUM(api_usage.cost_cents), 0) as total_cost_cents'),
            )
            ->groupBy('stories.id', 'stories.title', 'api_usage.service')
            ->orderBy('stories.id', 'desc')
            ->get();

        // Reshape into per-story objects
        $stories = [];
        foreach ($storyUsage as $row) {
            if (! isset($stories[$row->story_id])) {
                $stories[$row->story_id] = [
                    'story_id' => $row->story_id,
                    'story_title' => $row->story_title,
                    'anthropic' => ['call_count' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'cost_cents' => 0],
                    'nano_banana' => ['call_count' => 0, 'cost_cents' => 0],
                    'kling' => ['call_count' => 0, 'cost_cents' => 0],
                    'total_cost_cents' => 0,
                ];
            }

            if ($row->service === 'anthropic') {
                $stories[$row->story_id]['anthropic'] = [
                    'call_count' => (int) $row->call_count,
                    'input_tokens' => (int) $row->total_input_tokens,
                    'output_tokens' => (int) $row->total_output_tokens,
                    'cost_cents' => (int) $row->total_cost_cents,
                ];
                $stories[$row->story_id]['total_cost_cents'] += (int) $row->total_cost_cents;
            } elseif ($row->service === 'nano_banana') {
                // 18 tokens per call, $5 per 1000 tokens → $0.09 per call → 9 cents
                $callCount = (int) $row->call_count;
                $costCents = (int) round($callCount * 9);
                $stories[$row->story_id]['nano_banana'] = [
                    'call_count' => $callCount,
                    'cost_cents' => $costCents,
                ];
                $stories[$row->story_id]['total_cost_cents'] += $costCents;
            } elseif ($row->service === 'kling') {
                // ~$0.50 per call → 50 cents
                $callCount = (int) $row->call_count;
                $costCents = (int) round($callCount * 50);
                $stories[$row->story_id]['kling'] = [
                    'call_count' => $callCount,
                    'cost_cents' => $costCents,
                ];
                $stories[$row->story_id]['total_cost_cents'] += $costCents;
            } else {
                $stories[$row->story_id][$row->service] = [
                    'call_count' => (int) $row->call_count,
                ];
            }
        }

        // Global totals
        $totals = DB::table('api_usage')
            ->join('stories', 'api_usage.story_id', '=', 'stories.id')
            ->where('stories.user_id', $userId)
            ->select(
                DB::raw('COALESCE(SUM(CASE WHEN api_usage.service = \'anthropic\' THEN api_usage.input_tokens ELSE 0 END), 0) as total_input_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN api_usage.service = \'anthropic\' THEN api_usage.output_tokens ELSE 0 END), 0) as total_output_tokens'),
                DB::raw('COALESCE(SUM(CASE WHEN api_usage.service = \'anthropic\' THEN api_usage.cost_cents ELSE 0 END), 0) as total_cost_cents'),
                DB::raw('SUM(CASE WHEN api_usage.service = \'anthropic\' THEN 1 ELSE 0 END) as anthropic_calls'),
                DB::raw('SUM(CASE WHEN api_usage.service = \'nano_banana\' THEN 1 ELSE 0 END) as nano_banana_calls'),
                DB::raw('SUM(CASE WHEN api_usage.service = \'kling\' THEN 1 ELSE 0 END) as kling_calls'),
            )
            ->first();

        return Inertia::render('Costs/Index', [
            'stories' => array_values($stories),
            'totals' => [
                'anthropic' => [
                    'call_count' => (int) ($totals->anthropic_calls ?? 0),
                    'input_tokens' => (int) ($totals->total_input_tokens ?? 0),
                    'output_tokens' => (int) ($totals->total_output_tokens ?? 0),
                    'cost_cents' => (int) ($totals->total_cost_cents ?? 0),
                ],
                'nano_banana' => [
                    'call_count' => (int) ($totals->nano_banana_calls ?? 0),
                    'cost_cents' => (int) round(($totals->nano_banana_calls ?? 0) * 9),
                ],
                'kling' => [
                    'call_count' => (int) ($totals->kling_calls ?? 0),
                    'cost_cents' => (int) round(($totals->kling_calls ?? 0) * 50),
                ],
                'total_cost_cents' => (int) ($totals->total_cost_cents ?? 0) + (int) round(($totals->nano_banana_calls ?? 0) * 9) + (int) round(($totals->kling_calls ?? 0) * 50),
            ],
        ]);
    }
}
