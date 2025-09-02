<?php

use Ytmusicapi\YTMusic;

test('Search should throw exceptions ', function () {
    $yt = ytmusic();

    $query = "have fun storming the castle";

    expect(fn () => $yt->search($query, "song"))->toThrow(\Exception::class);
    expect(fn () => $yt->search($query, scope: "upload"))->toThrow(\Exception::class);
    expect(fn () => $yt->search($query, scope: "uploads", filter: "songs"))->toThrow(\Exception::class);
    expect(fn () => $yt->search($query, scope: "library", filter: "community_playlists"))->toThrow(\Exception::class);
});

test('Search should allow filter', function () {
    $yt = ytmusic();

    $songs = $yt->search("Let It Be", "songs", limit: 50);
    expect($songs)->toBeArray();
    foreach ($songs as $song) {
        expect($song->resultType)->toBe("song");
    }
});

test('Search with filter playlists has special handling', function () {
    $yt = ytmusic();

    $playlists = $yt->search("Toy Story", "playlists");
    expect($playlists)->toBeArray();
    foreach ($playlists as $playlist) {
        expect($playlist->resultType)->toBe("playlist");
    }
});

test('Search top result video', function () {
    $yt = ytmusic();
    $results = $yt->search("Fuel Eminem");
    expect($results[0]->category)->toBe("Top result");
    expect($results[0]->resultType)->toBe("video");
    expect($results[0]->videoId)->toBe("t5H_CewqpKA");
    expect($results[0]->artists)->toMatchArray([
        (object)["name" => "Eminem", "id" => "UCedvOgsKFzcK3hA5taf3KoQ"], 
        (object)["name" => "JID", "id" => "UCRlGNubLJBgW9VRCuiUnuYw"]]
    );
});

test('Search uploads', function () {
    $yt = ytbrowser();
    $songs = $yt->search("Almost There", null, "uploads");

    // Probably empty, but at least it's an array
    expect($songs)->toBeArray();
});

// This function doesn't really work for library search.
test('Search library', function () {
    $yt = ytbrowser();
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
    $yt = ytmusic();

    $suggestions = $yt->get_search_suggestions("Monekes");
    expect($suggestions)->toBeArray();
    expect($suggestions[0])->toBe("monkees");
});
