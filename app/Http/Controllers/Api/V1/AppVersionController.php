<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AppVersionRequest;
use App\Queries\AppVersion\GetAppVersionQuery;
use Illuminate\Http\JsonResponse;
use Exception;

class AppVersionController extends Controller
{
    /**
     * Get application version details and update requirements.
     */
    public function show(AppVersionRequest $request): JsonResponse
    {
        try {
            $data = GetAppVersionQuery::execute($request->validated());

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Version check completed.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'VERSION_CHECK_FAILED',
                'errors' => (object) [],
            ], 500);
        }
    }
}
