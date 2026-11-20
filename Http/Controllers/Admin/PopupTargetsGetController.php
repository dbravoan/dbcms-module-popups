<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Modules\Popups\Services\PopupTargetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PopupTargetsGetController
{
    public function __invoke(Request $request, PopupTargetReader $reader): JsonResponse
    {
        $input = $request->validate([
            'type' => 'required|string|max:100', 'market' => 'required|string|max:50', 'locale' => 'required|string|max:10',
            'search' => 'nullable|string|max:200', 'offset' => 'nullable|integer|min:0', 'limit' => 'nullable|integer|between:1,50',
            'ids' => 'sometimes|array|max:50', 'ids.*' => 'required|string|max:255',
        ]);

        return response()->json($reader->search($input['type'], $input['market'], $input['locale'], (string) ($input['search'] ?? ''), (int) ($input['offset'] ?? 0), (int) ($input['limit'] ?? 20), array_values($input['ids'] ?? [])))
            ->header('Cache-Control', 'private, no-store');
    }
}
