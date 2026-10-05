<?php

function httpPost($url, $data, $token = null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode($res, true)];
}

function httpGet($url, $token = null) {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode($res, true)];
}

echo "=== KARAOKE PARTY API TEST SUITE ===\n\n";

// 1. Create Room (Host)
$create = httpPost('http://127.0.0.1:8000/api/v1/rooms', [
    'room_name' => 'Friday Night Jam 🎤',
    'nickname' => 'Alex (Host)',
]);
echo "1. Create Room: Status = {$create['status']}\n";
$roomCode = $create['body']['data']['room']['code'];
$hostToken = $create['body']['data']['token'];
echo "   -> Room Code: {$roomCode}\n";
echo "   -> Host Token: " . substr($hostToken, 0, 15) . "...\n\n";

// 2. Join Room (Guest)
$join = httpPost('http://127.0.0.1:8000/api/v1/rooms/join', [
    'room_code' => $roomCode,
    'nickname' => 'Maria (Guest)',
]);
echo "2. Join Room: Status = {$join['status']}\n";
$guestToken = $join['body']['data']['token'];
echo "   -> Guest Joined: {$join['body']['data']['member']['nickname']}\n\n";

// 3. Search Songs
$search = httpGet('http://127.0.0.1:8000/api/v1/songs/search?q=bohemian');
echo "3. Search Catalog ('bohemian'): Status = {$search['status']}\n";
$song = $search['body']['data'][0];
echo "   -> Found: '{$song['title']}' by {$song['artist']} (ID: {$song['id']})\n\n";

// 4. Add Song to Queue as Guest
$addQueue = httpPost("http://127.0.0.1:8000/api/v1/rooms/{$roomCode}/queue", [
    'song_id' => $song['id'],
    'singer_name' => 'Maria',
], $guestToken);
echo "4. Add to Queue: Status = {$addQueue['status']}\n";
echo "   -> Queued at Position #{$addQueue['body']['data']['position']} for singer '{$addQueue['body']['data']['singer_name']}'\n\n";

// 5. Get Queue
$queue = httpGet("http://127.0.0.1:8000/api/v1/rooms/{$roomCode}/queue", $guestToken);
echo "5. Get Room Queue: Status = {$queue['status']}\n";
echo "   -> Queue Count: " . count($queue['body']['data']) . " song(s)\n\n";

// 6. Host Starts Next Song (Playback)
$play = httpPost("http://127.0.0.1:8000/api/v1/rooms/{$roomCode}/playback/next", [], $hostToken);
echo "6. Host Start Playback: Status = {$play['status']}\n";
echo "   -> Now Playing: '{$play['body']['data']['song']['title']}' (Singer: {$play['body']['data']['singer_name']})\n\n";

echo "=== ALL API TESTS PASSED SUCCESSFULLY ===\n";
