<?php

use Ytmusicapi\YTMusic;

test('get_charts()', function () {
    $yt = ytmusic();

    $charts = $yt->get_charts();
    expect(count($charts))->toBeGreaterThan(2);

    $charts = $yt->get_charts("US");
    expect(count($charts))->toBe(4); // countries, videos, genres, artists

    $charts = $yt->get_charts("BE");
    expect(count($charts))->toBe(3); // countries, videos, artists
});

test('get_charts() with browser.json', function () {
    $yt = ytbrowser();

    $charts = $yt->get_charts();
    expect(count($charts))->toBeGreaterThan(2);

    // Count depends on if premium account is testing or not. Annoying.
    $charts = $yt->get_charts("US");
    expect(count($charts))->toBeGreaterThanOrEqual(4);

    $charts = $yt->get_charts("BE");
    expect(count($charts))->toBe(3); // countries, videos, artists

    $charts = $yt->get_charts("IN");
    expect(count($charts))->toBeGreaterThanOrEqual(4); // countries, videos, languages, artists
});



