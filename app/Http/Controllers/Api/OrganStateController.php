<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrganState\OrganStateSummaryService;
use Illuminate\Http\JsonResponse;

class OrganStateController extends Controller
{
    public function __invoke(OrganStateSummaryService $organs): JsonResponse
    {
        return response()->json([
            'data' => $organs->all(),
            'meta' => [
                'read_only' => true,
                'source' => 'organ_databases',
            ],
        ]);
    }
}
