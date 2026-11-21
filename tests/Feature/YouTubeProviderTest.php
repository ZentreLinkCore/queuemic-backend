<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Queries\Song\SearchKaraokeSongsQuery;
use App\Services\KaraokeProvider\YouTubeKaraokeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class YouTubeProviderTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'AIza-test-key-never-logged';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        config(['karaoke.driver' => 'youtube', 'karaoke.youtube_api_key' => self::KEY]);
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

    public function test_quota_exceeded_sets_circuit_breaker_and_short_circuits_further_calls(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([
            'error' => ['message' => 'Quota exceeded.', 'errors' => [['reason' => 'quotaExceeded']]],
        ], 403)]);

        $provider = new YouTubeKaraokeProvider();
        $this->assertTrue($provider->search('bohemian rhapsody')->isEmpty());
        $this->assertTrue(YouTubeKaraokeProvider::isQuotaExhausted());

        // Second call must short-circuit without hitting YouTube again
        $this->assertTrue($provider->search('hotel california')->isEmpty());
        Http::assertSentCount(1);
    }

    public function test_search_query_canonicalizes_cache_key_for_equivalent_queries(): void
    {
        Http::fake([
            'www.googleapis.com/youtube/v3/search*' => Http::response(['items' => [
                ['id' => ['videoId' => 'vidMW'], 'snippet' => ['title' => 'My Way Karaoke', 'channelTitle' => 'SingKing']],
            ]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
                ['id' => 'vidMW', 'contentDetails' => ['duration' => 'PT4M35S']],
            ]]),
        ]);

        $first = SearchKaraokeSongsQuery::execute('My Way', 20);
        $second = SearchKaraokeSongsQuery::execute('  my   way   karaoke  ', 20);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame('vidMW', $second->first()['external_video_id']);
        // Only one YouTube search + one videos duration lookup across both calls
        Http::assertSentCount(2);
    }

    public function test_search_query_falls_back_to_local_songs_table_when_quota_is_exhausted(): void
    {
        Song::create([
            'external_video_id' => 'localVid1',
            'title' => 'Bohemian Rhapsody Karaoke',
            'artist' => 'Queen',
            'duration_formatted' => '05:55',
            'duration_seconds' => 355,
            'thumbnail_url' => 'https://img.youtube.com/vi/localVid1/hqdefault.jpg',
            'source_provider' => 'youtube',
        ]);

        Http::fake(['www.googleapis.com/*' => Http::response([
            'error' => ['message' => 'Quota exceeded.', 'errors' => [['reason' => 'quotaExceeded']]],
        ], 403)]);

        $results = SearchKaraokeSongsQuery::execute('bohemian', 20);

        $this->assertCount(1, $results);
        $this->assertSame('localVid1', $results->first()['external_video_id']);
        $this->assertTrue(YouTubeKaraokeProvider::isQuotaExhausted());

        // Subsequent search uses local catalog directly without calling YouTube
        $second = SearchKaraokeSongsQuery::execute('queen', 20);
        $this->assertCount(1, $second);
        $this->assertSame('localVid1', $second->first()['external_video_id']);
        Http::assertSentCount(1);
    }
}
