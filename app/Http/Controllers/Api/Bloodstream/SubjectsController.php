<?php

namespace App\Http\Controllers\Api\Bloodstream;

use App\Http\Controllers\Controller;
use App\Services\Bloodstream\BloodstreamMemoryQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectsController extends Controller
{
    public function __invoke(Request $request, BloodstreamMemoryQuery $bloodstream): JsonResponse
    {
        $filters = $request->validate([
            'contract_key' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'organ' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => $bloodstream->subjects($filters),
            'meta' => [
                'read_only' => true,
                'source' => 'bloodstream',
            ],
        ]);
    }
}
