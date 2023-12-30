<?php

use Ytmusicapi\YTMusic;

test('get_mood_playlists', function () {
    $yt = new YTMusic();
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

test('get_charts', function () {
    $yt = new YTMusic();
    $charts = $yt->get_charts();
    $this->expect($charts)->toHaveCount(2);
    $this->expect(array_keys($charts))->toContain("videos", "artists");
});

test('get_charts_us', function () {
    $yt = new YTMusic();
    $charts = $yt->get_charts(country: "US");
    $this->expect(array_keys($charts))->toContain("videos", "artists", "genres", "trending");
});

test('get_charts_outside_us', function () {
    $yt = new YTMusic();
    $charts = $yt->get_charts(country: "BE");
    $this->expect(array_keys($charts))->toContain("videos", "artists", "trending");
    $this->expect(array_keys($charts))->not()->toContain("genres");
});
