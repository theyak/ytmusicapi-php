<?php

use Ytmusicapi\YTMusic;

test('get_charts()', function () {
    $yt = new YTMusic();
    $charts = $yt->get_charts();
    expect(count($charts))->toBeGreaterThan(2);

    $charts = $yt->get_charts("US");
    expect(count($charts))->toBe(4); // countries, videos, genres, artists

    $charts = $yt->get_charts("BE");
    expect(count($charts))->toBe(3); // countries, videos, artists
});

test('get_charts() with browser.json', function () {
    $yt = new YTMusic("browser.json");
    $charts = $yt->get_charts();
    expect(count($charts))->toBeGreaterThan(2);

    $charts = $yt->get_charts("US");
    expect(count($charts))->toBe(5); // countries, daily, weekly, genres, artists

    $charts = $yt->get_charts("BE");
    expect(count($charts))->toBe(3); // countries, videos, artists
});



