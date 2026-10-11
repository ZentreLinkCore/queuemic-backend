<?php

namespace App\Http\Middleware;

use App\Queries\AppVersion\GetAppVersionQuery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAppVersion
{
    /**
     * Handle an incoming request and ensure mobile app meets minimum version requirement.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientVersion = $request->header('X-App-Version');

        if ($clientVersion !== null && trim($clientVersion) !== '') {
            $clientVersion = trim($clientVersion);
            $minVersion = (string) config('app_version.minimum_supported_version');

            if (version_compare($clientVersion, $minVersion, '<')) {
                $downloadUrl = GetAppVersionQuery::downloadUrl(strtolower($request->header('X-Platform', 'android')));

                return response()->json([
                    'success' => false,
                    'message' => 'Your version of QueueMic is no longer supported. Please update to continue.',
                    'code' => 'UPDATE_REQUIRED',
                    'errors' => (object) [
                        'minimum_version' => $minVersion,
                        'current_version' => $clientVersion,
                        'download_url' => $downloadUrl,
                    ],
                ], 426);
            }
        }

        return $next($request);
    }
}
