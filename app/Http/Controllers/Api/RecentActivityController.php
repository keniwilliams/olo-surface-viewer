<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecentActivity\RecentActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentActivityController extends Controller
{
    public function __invoke(Request $request, RecentActivityFeedService $feed): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => $feed->all($validated['limit'] ?? null),
            'meta' => [
                'read_only' => true,
                'source' => 'surface_viewer_observation_state',
            ],
        ]);
    }
}
