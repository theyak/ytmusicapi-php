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
    // When logged in with premium account: countries, songs, videos, artists
    // When not logged in or no premium account: countries, videos, artists

    $yt = new YTMusic();
    $charts = $yt->get_charts();

    if (count($charts) === 3) {
        $this->expect(array_keys($charts))->toContain("countries", "videos", "artists");
    } else if (count($charts) === 4) {
        $this->expect(array_keys($charts))->toContain("countries", "songs", "videos", "artists");
        $this->expect(count($charts["songs"]["items"]))->toBeGreaterThan(30);
    }
    $this->expect(count($charts["videos"]["items"]))->toBeGreaterThan(30);
    $this->expect(count($charts["artists"]["items"]))->toBeGreaterThan(30);
});

test('get_charts_us', function () {
    $yt = new YTMusic();
    $charts = $yt->get_charts(country: "US");
    if (count($charts) === 4) {
        $this->expect(array_keys($charts))->toContain("countries", "videos", "artists", "genres");
    } else if (count($charts) === 5) {
        $this->expect(array_keys($charts))->toContain("countries", "songs", "videos", "artists", "genres");
        $this->expect(count($charts["songs"]["items"]))->toBeGreaterThan(30);
    }
    $this->expect(array_keys($charts))->toContain("videos", "artists", "genres");
});

test('get_charts_outside_us', function () {
    $yt = new YTMusic();
    $charts = $yt->get_charts(country: "BE");


    if (count($charts) === 3) {
        $this->expect(array_keys($charts))->toContain("countries", "videos", "artists");
    } else if (count($charts) === 4) {
        $this->expect(array_keys($charts))->toContain("countries", "songs", "videos", "artists");
        $this->expect(count($charts["songs"]["items"]))->toBeGreaterThan(30);
    }

    $this->expect(array_keys($charts))->not()->toContain("genres");
});
