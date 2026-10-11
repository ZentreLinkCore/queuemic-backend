<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Concerns\InteractsWithRooms;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    use InteractsWithRooms;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_version_check_returns_valid_response(): void
    {
        Config::set('app_version.latest_version', '1.1.0');
        Config::set('app_version.minimum_supported_version', '1.0.0');
        Config::set('app_version.release_notes', 'New features and improvements.');

        $response = $this->getJson('/api/v1/app/version?platform=android&current_version=1.0.0');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latest_version', '1.1.0')
            ->assertJsonPath('data.minimum_supported_version', '1.0.0')
            ->assertJsonPath('data.update_type', 'optional')
            ->assertJsonPath('data.release_notes', 'New features and improvements.');
    }

    public function test_version_check_returns_required_when_below_minimum(): void
    {
        Config::set('app_version.latest_version', '1.2.0');
        Config::set('app_version.minimum_supported_version', '1.1.0');

        $response = $this->getJson('/api/v1/app/version?platform=android&current_version=1.0.0');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.update_type', 'required');
    }

    public function test_version_check_returns_none_when_on_latest(): void
    {
        Config::set('app_version.latest_version', '1.0.0');
        Config::set('app_version.minimum_supported_version', '1.0.0');

        $response = $this->getJson('/api/v1/app/version?platform=android&current_version=1.0.0');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.update_type', 'none');
    }

    public function test_version_check_rejects_invalid_semver_with_422(): void
    {
        $response = $this->getJson('/api/v1/app/version?current_version=invalid-format!!');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_middleware_blocks_sub_minimum_client_version_with_426(): void
    {
        Config::set('app_version.minimum_supported_version', '1.1.0');

        $response = $this->postJson(
            '/api/v1/rooms',
            ['name' => 'Test Party', 'host_nickname' => 'DJ'],
            ['X-App-Version' => '1.0.0']
        );

        $response->assertStatus(426)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'UPDATE_REQUIRED')
            ->assertJsonPath('errors.minimum_version', '1.1.0')
            ->assertJsonPath('errors.current_version', '1.0.0');
    }

    public function test_version_check_is_not_blocked_for_outdated_clients(): void
    {
        Config::set('app_version.latest_version', '1.2.0');
        Config::set('app_version.minimum_supported_version', '1.1.0');

        $response = $this->getJson(
            '/api/v1/app/version?platform=android&current_version=1.0.0',
            ['X-App-Version' => '1.0.0']
        );

        $response->assertOk()->assertJsonPath('data.update_type', 'required');
    }

    public function test_version_check_requires_platform_and_version(): void
    {
        $this->getJson('/api/v1/app/version?platform=android&current_version=bad')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid semver string format.')
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->getJson('/api/v1/app/version')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->getJson('/api/v1/app/version?platform=bogus&current_version=1.0.0')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_middleware_allows_supported_client_version(): void
    {
        Config::set('app_version.minimum_supported_version', '1.0.0');

        $response = $this->postJson(
            '/api/v1/rooms',
            ['room_name' => 'Party Room', 'nickname' => 'Singer'],
            ['X-App-Version' => '1.0.0']
        );

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
