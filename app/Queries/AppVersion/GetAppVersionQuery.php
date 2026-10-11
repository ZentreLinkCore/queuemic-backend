<?php

namespace App\Queries\AppVersion;

use Illuminate\Support\Facades\Cache;

class GetAppVersionQuery
{
    /**
     * @param array $params Validated by AppVersionRequest: platform, current_version, current_build
     */
    public static function execute(array $params): array
    {
        $platform = $params['platform'];
        $currentVersion = $params['current_version'];

        $config = Cache::remember('app_version_config', 60, function () {
            return [
                'latest_version' => (string) config('app_version.latest_version'),
                'minimum_supported_version' => (string) config('app_version.minimum_supported_version'),
                'release_notes' => (string) config('app_version.release_notes'),
                'is_ota_compatible' => (bool) config('app_version.is_ota_compatible'),
                'published_at' => config('app_version.published_at'),
            ];
        });

        $updateType = 'none';
        if (version_compare($currentVersion, $config['minimum_supported_version'], '<')) {
            $updateType = 'required';
        } elseif (version_compare($currentVersion, $config['latest_version'], '<')) {
            $updateType = 'optional';
        }

        return [
            'latest_version' => $config['latest_version'],
            'minimum_supported_version' => $config['minimum_supported_version'],
            'release_notes' => $config['release_notes'],
            'update_type' => $updateType,
            'download_url' => self::downloadUrl($platform),
            'is_ota_compatible' => $config['is_ota_compatible'],
            'published_at' => $config['published_at'],
        ];
    }

    /**
     * Store page for a platform; shared with EnforceAppVersion so both answers point at the same place.
     */
    public static function downloadUrl(string $platform): string
    {
        return (string) config($platform === 'ios' ? 'app_version.download_url_ios' : 'app_version.download_url_android');
    }
}
