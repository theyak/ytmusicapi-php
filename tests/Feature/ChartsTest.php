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
    $account = $yt->get_account();

    $charts = $yt->get_charts();
    expect(count($charts))->toBeGreaterThan(2);

    // Count depends on if premium account is testing or not. Annoying.
    $charts = $yt->get_charts("US");
    if ($account->is_premium) {
        expect(count($charts))->toBe(5);
        expect(array_keys($charts))->toBe(["countries", "daily", "weekly", "genres", "artists"]);
    } else {
        expect(count($charts))->toBe(4);
        expect(array_keys($charts))->toBe(["countries", "videos", "genres", "artists"]);
    }

    $charts = $yt->get_charts("BE");
    expect(count($charts))->toBe(3); // countries, videos, artists

    $charts = $yt->get_charts("IN");
    if ($account->is_premium) {
        expect(count($charts))->toBe(5);
        expect(array_keys($charts))->toBe(["countries", "daily", "weekly", "genres", "artists"]);
    } else {
        expect(count($charts))->toBe(4);
        expect(array_keys($charts))->toBe(["countries", "videos", "languages", "artists"]);
    }
})->only();



