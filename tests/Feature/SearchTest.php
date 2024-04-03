<?php

use Ytmusicapi\YTMusic;

test('Search should throw exceptions ', function () {
    $yt = new YTMusic();

    $query = "have fun storming the castle";

    expect(fn () => $yt->search($query, "song"))->toThrow(\Exception::class);
    expect(fn () => $yt->search($query, scope: "upload"))->toThrow(\Exception::class);
    expect(fn () => $yt->search($query, scope: "uploads", filter: "songs"))->toThrow(\Exception::class);
    expect(fn () => $yt->search($query, scope: "library", filter: "community_playlists"))->toThrow(\Exception::class);
});

test('Search should return results for random queries', function () {
    $yt = new YTMusic();

    $queries = ["Monekes", "qllwlwl", "heun"];

    foreach ($queries as $q) {
        $results = $yt->search($q);
        expect(count($results))->toBeGreaterThanOrEqual(3);
    }

    $results = $yt->search("Martin Stig Andersen - Deteriation");
    expect(count($results))->toBeGreaterThan(0);
});

test('Search should allow filter', function () {
    $yt = new YTMusic();

    $songs = $yt->search("Let It Be", "songs", limit: 50);
    expect($songs)->toBeArray();
    foreach ($songs as $song) {
        expect($song->resultType)->toBe("song");
    }
});

test('Search with filter playlists has special handling', function () {
    $yt = new YTMusic();

    $playlists = $yt->search("Toy Story", "playlists");
    expect($playlists)->toBeArray();
    foreach ($playlists as $playlist) {
        expect($playlist->resultType)->toBe("playlist");
    }
});

test('Search uploads', function () {
    $yt = new YTMusic("oauth.json");
    $songs = $yt->search("Almost There", null, "uploads");

    // Probably empty, but at least it's an array
    expect($songs)->toBeArray();
});

// This function doesn't really work for library search.
test('Search library', function () {
    $yt = new YTMusic("oauth.json");
    $songs = $yt->search("Almost There", null, "library");
    expect($songs)->toBeArray();
});

test('No search results should return empty array', function () {
    $return = (object)['context' => []];

    $yt = Mockery::mock(YTMusic::class)->makePartial();
    $yt->shouldReceive("_send_request")->andReturn($return);
    $songs = $yt->search("Should throw");
    expect($songs)->toBeArray();
    expect($songs)->toBeEmpty();
});

test('get_search_suggestions()', function () {
    $yt = new YTMusic();

    $suggestions = $yt->get_search_suggestions("Monekes");
    expect($suggestions)->toBeArray();
    expect($suggestions[0])->toBe("monkees");
});
