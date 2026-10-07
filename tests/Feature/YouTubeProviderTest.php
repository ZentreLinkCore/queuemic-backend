<?php

namespace Tests\Feature;

use App\Services\KaraokeProvider\YouTubeKaraokeProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class YouTubeProviderTest extends TestCase
{
    private const KEY = 'AIza-test-key-never-logged';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['karaoke.youtube_api_key' => self::KEY]);
    }

    public function test_search_normalizes_results_with_real_lengths_and_sends_the_key_only_in_a_header(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response(['items' => [
                ['id' => ['videoId' => 'vidA'], 'snippet' => ['title' => 'Song A &amp; B Karaoke', 'channelTitle' => 'Chan']],
                ['id' => ['videoId' => 'vidB'], 'snippet' => ['title' => 'Song B Karaoke', 'channelTitle' => 'Chan']],
            ]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                ['id' => 'vidA', 'contentDetails' => ['duration' => 'PT4M13S']],
                ['id' => 'vidB', 'contentDetails' => ['duration' => 'PT1H2M3S']],
            ]]),
        ]);

        $results = (new YouTubeKaraokeProvider())->search('song', 2)->keyBy('external_video_id');

        $this->assertSame('Song A & B Karaoke', $results['vidA']['title']);
        $this->assertSame([253, '04:13'], [$results['vidA']['duration_seconds'], $results['vidA']['duration_formatted']]);
        $this->assertSame([3723, '1:02:03'], [$results['vidB']['duration_seconds'], $results['vidB']['duration_formatted']]);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', self::KEY)
            && !str_contains($request->url(), 'key='));
    }

    public function test_api_rejections_are_logged_with_googles_reason(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([
            'error' => ['message' => 'The request cannot be completed because you have exceeded your quota.', 'errors' => [['reason' => 'quotaExceeded']]],
        ], 403)]);
        Log::spy();

        $this->assertTrue((new YouTubeKaraokeProvider())->search('zombie')->isEmpty());

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => $message === 'YouTube Data API search rejected'
            && $context['status'] === 403
            && $context['reason'] === 'quotaExceeded')->once();
    }

    public function test_network_errors_are_logged_without_the_key(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out for https://www.googleapis.com/youtube/v3/search?q=x&key=' . self::KEY
        ));
        Log::spy();

        (new YouTubeKaraokeProvider())->search('zombie');

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_starts_with($message, 'YouTube Data API search failed')
            && str_contains($message, 'key=***')
            && !str_contains($message, self::KEY))->once();
    }

    public function test_get_details_returns_real_metadata_or_null(): void
    {
        Http::fake(['www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
            ['id' => 'vidZ', 'snippet' => ['title' => 'Zombie', 'channelTitle' => 'Chan'], 'contentDetails' => ['duration' => 'PT5M6S']],
        ]])]);

        $details = (new YouTubeKaraokeProvider())->getDetails('vidZ');
        $this->assertSame([306, '05:06'], [$details['duration_seconds'], $details['duration_formatted']]);

        config(['karaoke.youtube_api_key' => null]);
        $this->assertNull((new YouTubeKaraokeProvider())->getDetails('vidZ'));
    }
}
