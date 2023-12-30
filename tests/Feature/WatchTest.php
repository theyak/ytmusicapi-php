<?php

use Ytmusicapi\YTMusic;

test('get_watch_playlist() - radio, standard number of tracks', function () {
    $yt = new YTMusic();

    $playlist = $yt->get_watch_playlist(playlistId: $this->watchPlaylistId, radio: true);
    expect(count($playlist->tracks))->toBe(25);
});

test('get_watch_playlist() - based on track', function () {
    $yt = new YTMusic();

    $playlist = $yt->get_watch_playlist($this->videoId, limit: 50);
    expect(count($playlist->tracks))->toBeGreaterThanOrEqual(45);
});

test("get_watch_playlist() - radio, lots of tracks", function () {
    $yt = new YTMusic();

    $playlist = $yt->get_watch_playlist(playlistId: $this->watchPlaylistId, radio: true, limit: 90);
    expect(count($playlist->tracks))->toBeGreaterThanOrEqual(90);
});

test("get_watch_playlist() - suffled album", function () {
    $yt = new YTMusic();

    $playlist = $yt->get_watch_playlist(playlistId: "OLAK5uy_lCl8VFn-xBO9PlDF2E0FXSjhaU0dLJP9I", shuffle: true);
    expect(count($playlist->tracks))->toBe(12);
});

test('get_track() - Unauthorized', function () {
    $yt = new YTMusic();
    $track = $yt->get_track("JTvNVxk1WnU");

    expect($track::class)->toBe("Ytmusicapi\\WatchTrack");
    expect($track->title)->toBe("Closer");
    expect($track->artists)->toHaveCount(1);
    expect($track->artists[0]->name)->toBe("Nine Inch Nails");
    expect($track->artists[0]->id)->toBe("UC8txE2ZyN2Sh8XxH5OkHLSw");
    expect($track->album->name)->toBe("The Downward Spiral");
    expect($track->album->id)->toBe("MPREb_meSRZ5P9CD1");
    expect($track->likeStatus)->toBeNull();
    expect($track->inLibrary)->toBe(false);
    expect($track->thumbnails)->toHaveCount(6);
    expect($track->isAvailable)->toBe(true);
    expect($track->isExplicit)->toBe(true);
    expect($track->duration_seconds)->toBe(373);
    expect($track->duration)->toBe("6:13");
    expect($track->length)->toBe("6:13");
    expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV");
    expect($track->feedbackTokens)->toBeNull();
    expect($track->playlistId)->toBe("RDAMVMJTvNVxk1WnU");
    expect($track->lyrics)->toBe("MPLYt_meSRZ5P9CD1-5");
    expect($track->related)->toBe("MPTRt_meSRZ5P9CD1-5");
});

test('get_track() - Authorized', function () {
    $yt = new YTMusic("oauth.json");
    $track = $yt->get_track("JTvNVxk1WnU");

    expect($track::class)->toBe("Ytmusicapi\\WatchTrack");
    expect($track->title)->toBe("Closer");
    expect($track->artists)->toHaveCount(1);
    expect($track->artists[0]->name)->toBe("Nine Inch Nails");
    expect($track->artists[0]->id)->toBe("UC8txE2ZyN2Sh8XxH5OkHLSw");
    expect($track->album->name)->toBe("The Downward Spiral");
    expect($track->album->id)->toBe("MPREb_meSRZ5P9CD1");
    expect($track->likeStatus)->not->toBeEmpty();
    expect($track->thumbnails)->toHaveCount(6);
    expect($track->isAvailable)->toBe(true);
    expect($track->isExplicit)->toBe(true);
    expect($track->duration_seconds)->toBe(373);
    expect($track->duration)->toBe("6:13");
    expect($track->length)->toBe("6:13");
    expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV");
    expect($track->feedbackTokens)->toHaveProperty("add");
    expect($track->feedbackTokens)->toHaveProperty("remove");
    expect($track->feedbackTokens->add)->not->toBeEmpty();
    expect($track->feedbackTokens->remove)->not->toBeEmpty();
    expect($track->playlistId)->toBe("RDAMVMJTvNVxk1WnU");
    expect($track->lyrics)->toBe("MPLYt_meSRZ5P9CD1-5");
    expect($track->related)->toBe("MPTRt_meSRZ5P9CD1-5");
});
