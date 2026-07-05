<?php

namespace App\Http\Controllers;

use App\Services\SurfaceTree\SurfaceTreeReadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SurfaceTreeNodeController extends Controller
{
    public function roots(Request $request, SurfaceTreeReadModel $surfaceTree): JsonResponse
    {
        [$depthWindow, $validationError] = $this->depthWindow($request);

        if ($validationError) {
            return $validationError;
        }

        return response()->json([
            'data' => $surfaceTree->roots($depthWindow),
            'meta' => [
                'depth_window' => $depthWindow,
                'read_only' => true,
            ],
        ]);
    }

    public function children(Request $request, SurfaceTreeReadModel $surfaceTree, string $nodeKey): JsonResponse
    {
        [$depthWindow, $validationError] = $this->depthWindow($request);

        if ($validationError) {
            return $validationError;
        }

        $children = $surfaceTree->childrenFor($nodeKey, $depthWindow);

        abort_if($children === null, 404);

        return response()->json([
            'data' => $children,
            'meta' => [
                'depth_window' => $depthWindow,
                'parent_key' => $nodeKey,
                'read_only' => true,
            ],
        ]);
    }

    /**
     * @return array{0: int, 1: JsonResponse|null}
     */
    private function depthWindow(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'depth_window' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        if ($validator->fails()) {
            return [
                3,
                response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()->toArray(),
                ], 422),
            ];
        }

        $validated = $validator->validated();

        return [(int) ($validated['depth_window'] ?? 3), null];
    }
}
