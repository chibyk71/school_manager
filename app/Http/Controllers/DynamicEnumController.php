<?php

/**
 * Temporary Phase 1 stub for the legacy Dynamic Enum admin/API controller.
 *
 * Phase 1 establishes the normalized schema only. Admin CRUD, option editing,
 * and option listing belong to Phase 4. This controller remains routable so the
 * application boots, but every action returns HTTP 501 with a clear message
 * instead of calling removed model methods (visibleToSchool, options JSON, etc.).
 */

namespace App\Http\Controllers;

use App\Models\DynamicEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicEnumController extends Controller
{
    private const PHASE_MESSAGE = 'Dynamic Enum administration is unavailable until Phase 4. Phase 1 only establishes the domain schema foundation.';

    public function index(Request $request): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateMetadata(Request $request, DynamicEnum $dynamicEnum): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateOptions(Request $request, DynamicEnum $dynamicEnum): JsonResponse
    {
        return $this->notImplemented();
    }

    public function options(string $appliesTo, string $name): JsonResponse
    {
        return $this->notImplemented();
    }

    private function notImplemented(): JsonResponse
    {
        return response()->json([
            'message' => self::PHASE_MESSAGE,
        ], 501);
    }
}
