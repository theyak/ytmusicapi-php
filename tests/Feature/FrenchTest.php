<?php

use Ytmusicapi\YTMusic;

test('get_song_info() - for kids, French', function () {
    $yt = new YTMusic(null, language: "fr");
    $track = $yt->get_song_info("QDIWWqxqMes");
    expect($track->title)->toBe("Return to Pooh Corner");
    expect($track->playbackMode)->toBe("PLAYBACK_MODE_PAUSED_ONLY");
    expect($track->madeForKids)->toBe(true);
});
