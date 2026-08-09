<?php

use Ytmusicapi\YTMusic;

test('get_mood_playlists', function () {
    $yt = ytmusic();
    $categories = $yt->get_mood_categories();
    $this->expect(count($categories))->toBeGreaterThan(0);

    $cat = array_key_first($categories);
    $this->expect(count($categories[$cat]))->toBeGreaterThan(5);

    $first = $categories[$cat][0];
    $this->expect($first)->toHaveProperties(["title", "params"]);

    $playlists = $yt->get_mood_playlists($first->params);
    $this->expect(count($playlists))->toBeGreaterThan(0);

    $first = $playlists[0];
    $this->expect($first)->toHaveProperties(["title", "playlistId", "thumbnails", "description"]);
});

test('get_explore() - Not authenticated', function () {
    $yt = ytmusic();
    $explore = $yt->get_explore();

    expect(count($explore))->toBe(5);
});

test('get_explore() - Premium User', function () {
    $yt = ytbrowser();
    $explore = $yt->get_explore();

    expect(count($explore))->toBeGreaterThan(5);

    foreach ($explore["new_releases"] as $item) {
        expect($item->audioPlaylistId)->toStartWith("OLA");
    }

    foreach ($explore["top_songs"]["items"] as $item) {
        $has_video_id = !empty($item->videoId);
        $has_views_or_album = !empty($item->views) || !empty($item->album);
        if ($has_video_id) {
            expect($has_views_or_album)->toBeTrue();
        }
    }

    foreach ($explore["trending"]["items"] as $item) {
        expect($item->videoId)->not->toBeEmpty();
        foreach ($item->artists as $artist) {
            expect($artist->name)->not->toBeEmpty();
        }
    }

    foreach ($explore["trending"]["items"] as $item) {
        expect($item->videoId)->not->toBeEmpty();
    }

    foreach ($explore["top_episodes"] as $item) {
        expect($item->duration)->not->toBeEmpty();
        expect($item->podcast->id)->not->toBeEmpty();
        expect($item->podcast->name)->not->toBeEmpty();
    }
});

