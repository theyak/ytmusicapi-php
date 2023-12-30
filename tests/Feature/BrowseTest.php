<?php

use Ytmusicapi\YTMusic;

test('get_account()', function () {
    $yt = new YTMusic("browser.json");
    $account = $yt->get_account();

    expect($account->name)->not->toBeEmpty();
    expect($account->channelId)->not->toBeEmpty();
    expect($account->thumbnails)->toBeArray();
});

test('get_account() - error condition', function () {
    $yt = Mockery::mock(YTMusic::class, ["oauth.json"])->makePartial();
    $yt->shouldReceive("_send_request")->andReturn("");
    $yt->get_account();
})->throws("Could not find account information.");

test('get_home()', function () {
    $yt = new YTMusic("oauth.json");
    $home = $yt->get_home();

    expect($home)->toBeArray();
    foreach ($home as $row) {
        expect($row::class)->toBe("Ytmusicapi\\Shelf");
        expect($row->title)->not->toBeEmpty();
        expect($row->contents)->toBeArray();
        foreach ($row->contents as $content) {
            expect($content->title)->not->toBeEmpty();
            expect($content->resultType)->not->toBeEmpty();
            expect($content->thumbnails)->toBeArray();
        }
    }
});

test('get_song()', function () {
    $yt = new YTMusic();

    $song = $yt->get_song($this->videoId);
    expect($song->videoDetails->title)->toBe($this->videoTitle);
    expect($song->videoDetails->channelId)->toBe($this->videoArtistChannel);
    expect($song->microformat)->toBeObject();
    expect($song->microformat->microformatDataRenderer->urlCanonical)->toBe("https://music.youtube.com/watch?v={$this->videoId}");
    expect($song->playbackTracking)->toBeObject();
    expect($song->playabilityStatus)->toBeObject();
    expect($song->playabilityStatus->status)->toBe("OK");
    expect($song->streamingData)->toBeObject();
    expect(count($song->streamingData->adaptiveFormats))->toBeGreaterThan(5);
});

test('get_song_info() - music', function () {
    $yt = new YTMusic();
    $track = $yt->get_song_info($this->videoId);

    expect($track->title)->toBe($this->videoTitle);
    expect($track->author)->toBe($this->videoArtist);
    expect($track->viewCount)->toBeGreaterThan(6500000);
    expect($track->thumbnails)->toBeArray();
    expect($track->thumbnails)->toHaveCount(4);
    expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV");
    expect($track->duration_seconds)->toBe($this->videoDurationSecods);
    expect($track->duration)->toBe($this->videoDuration);
    expect($track->music)->toBe(true);
});

test('get_song_info() - music, passing in Song', function () {
    $yt = new YTMusic();
    $song = $yt->get_song($this->videoId);
    $track = $yt->get_song_info($song);

    expect($track->title)->toBe($this->videoTitle);
    expect($track->author)->toBe($this->videoArtist);
    expect($track->viewCount)->toBeGreaterThan(1500000);
    expect($track->thumbnails)->toBeArray();
    expect($track->thumbnails)->toHaveCount(4);
    expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV");
    expect($track->duration_seconds)->toBe($this->videoDurationSecods);
    expect($track->duration)->toBe($this->videoDuration);
    expect($track->music)->toBe(true);
});

test('get_song_info() - non music', function () {
    $yt = new YTMusic();
    $track = $yt->get_song_info("1stQiJEW9PE");

    expect($track->title)->toBe("Home Alone Pitch Meeting - Revisited!");
    expect($track->author)->toBe("Pitch Meeting");
    expect($track->viewCount)->toBeGreaterThan(300000);
    expect($track->thumbnails)->toHaveCount(4);
    expect($track->videoType)->toBeEmpty();
    expect($track->duration_seconds)->toBe(660);
    expect($track->duration)->toBe("11:00");
    expect($track->music)->toBe(false);
    expect($track->playbackMode)->toBeEmpty();
    expect($track->madeForKids)->toBe(false);
});

test('get_song_info() - for kids', function () {
    $yt = new YTMusic();

    $track = $yt->get_song_info("QDIWWqxqMes");
    expect($track->title)->toBe("Return to Pooh Corner");
    expect($track->playbackMode)->toBe("PLAYBACK_MODE_PAUSED_ONLY");
    expect($track->madeForKids)->toBe(true);
});

test('get_song_info() - really long song', function () {
    $yt = new YTMusic();
    $track = $yt->get_song_info("qLooSc5ewIA");
    expect($track->duration)->toBe("10:31:48");
    expect($track->duration_seconds)->toBe(37908);
});

test('get_song_info() - Invalid video ID', function () {
    $yt = new YTMusic();
    $track = $yt->get_song_info("aaaaaaaaaaa");
})->throws(Exception::class);

test('get_song_info() - Invalid video ID type', function () {
    $yt = new YTMusic();
    $track = $yt->get_song_info(12);
})->throws(Exception::class);

test('get_artist() and get_artist_albums()', function () {
    $yt = new YTMusic("oauth.json");
    $artist = $yt->get_artist($this->artistId);

    expect($artist->name)->toBe($this->artistName);
    expect($artist->description)->not->toBeEmpty();
    expect($artist->views)->not->toBeEmpty();
    expect($artist->subscribers)->not->toBeEmpty();
    expect($artist->thumbnails)->toBeArray();
    expect($artist->shuffleId)->not->toBeEmpty();
    expect($artist->radioId)->not->toBeEmpty();
    expect($artist->songs)->toHaveProperty("browseId");
    expect(count($artist->songs->results))->toBeGreaterThan(1);
    expect($artist->videos)->toHaveProperty("browseId");
    expect(count($artist->videos->results))->toBeGreaterThan(1);
    expect($artist->albums)->toHaveProperty("browseId");
    expect($artist->albums)->toHaveProperty("params");
    expect(count($artist->albums->results))->toBeGreaterThan(1);
    expect($artist->singles)->toHaveProperty("browseId");
    expect($artist->singles)->toHaveProperty("params");
    expect(count($artist->singles->results))->toBeGreaterThan(1);

    // Test get_artist_albums()
    $channelId = $artist->albums->browseId;
    $params = $artist->albums->params;
    $albums = $yt->get_artist_albums($channelId, $params);
    expect(count($albums))->toBeGreaterThan(100);
    foreach ($albums as $album) {
        expect($album->browseId)->toStartWith("MPREb_");
        expect($album->playlistId)->toStartWith("OLAK5uy_");
        expect($album->title)->not->toBeEmpty();
        expect($album->thumbnails)->toBeArray();
        expect($album->type)->toBe("Album");
    }
});

test("get_artist() with the MPLA prefix", function () {
    $yt = new YTMusic();
    $artist = $yt->get_artist("MPLA" . $this->artistId);

    expect($artist->name)->toBe($this->artistName);
});

test('get_artist_albums() - singles', function () {
    $yt = new YTMusic();
    $result = $yt->get_artist($this->artistId);
    $channelId = $result->singles->browseId;
    $params = $result->singles->params;
    $albums = $yt->get_artist_albums($channelId, $params);
    expect(count($albums))->toBeGreaterThan(10);
    expect(count($albums))->toBeLessThan(100);
    foreach ($albums as $album) {
        expect($album->browseId)->toStartWith("MPREb_");
        expect($album->playlistId)->toStartWith("OLAK5uy_");
        expect($album->title)->not->toBeEmpty();
        expect($album->thumbnails)->toBeArray();
        expect($album->type)->toBe("Single");
    }
});

test('get_artist_albums() - without the MPAD prefix', function () {
    $yt = new YTMusic("oauth.json");
    $artist = $yt->get_artist($this->artistId);
    $channelId = substr($artist->albums->browseId, 4);
    $params = $artist->albums->params;
    $albums = $yt->get_artist_albums($channelId, $params);
    expect(count($albums))->toBeGreaterThan(100);
});

test("get_album() and get_album_browse_id()", function () {
    $yt = new YTMusic();
    $result = $yt->get_album($this->albumId);

    expect($result->title)->toBe($this->albumTitle);
    expect($result->artists[0]->name)->toBe($this->albumArtist);
    expect($result->year)->toBe($this->albumYear);
    expect($result->audioPlaylistId)->toStartWith("OLAK5uy_");
    expect($result->duration)->not->toBeEmpty();
    expect($result->tracks)->toBeArray();
    expect($result->tracks)->toBeGreaterThan(0);

    $seconds = 0;
    foreach ($result->tracks as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        $seconds += (int)$track->duration_seconds;
    }

    expect($result->duration_seconds)->toBe($seconds);
    expect($result->other_versions)->toBeArray();

    // Test get_album_browse_id()
    $result = $yt->get_album_browse_id($result->audioPlaylistId);
    expect($result)->toBe($this->albumId);
});

test("get_album() with bad album ID", function () {
    $yt = new YTMusic();
    $result = $yt->get_album($this->albumId . "AAA");
})->throws(Exception::class);

test("get_user() and get_user_playlists()", function () {
    // For some reason get_user_playlists() is requiring authentication.
    // Not sure why as this works fine in Python without authentication.
    // Someone please figure this out for me. I've spent way too mcuh
    // time on this.
    $yt = new YTMusic("oauth.json");
    $user = $yt->get_user($this->userChannel);

    expect($user->channelId)->toBe($this->userChannel);
    expect($user->name)->toBe($this->userChannelName);
    expect($user)->toHaveProperty("playlists");
    expect($user)->toHaveProperty("albums");
    expect($user)->toHaveProperty("singles");
    expect($user)->toHaveProperty("videos");
    expect($user)->toHaveProperty("related");

    // I think this data is only available if this user has more than 10 playlists
    if ($user->playlists->browseId && $user->playlists->params) {
        $playlists = $yt->get_user_playlists($user->playlists->browseId, $user->playlists->params);
        expect(count($playlists))->toBeGreaterThan(10);
        foreach ($playlists as $playlist) {
            expect($playlist::class)->toBe("Ytmusicapi\\PlaylistInfo");
            expect($playlist->title)->not->toBeEmpty();
            expect($playlist->playlistId)->not->toBeEmpty();
            expect($playlist->thumbnails)->toBeArray();
            expect($playlist->count)->toBeGreaterThan(0);
            expect($playlist->author[0]->name)->toBe($this->userChannelName);
            expect($playlist->author[0]->id)->toBe($this->userChannel);
        }
    }
});

test("get_tasteprofile() and set_tasteprofile()", function () {
    $yt = new YTMusic("oauth.json");

    $profile = $yt->get_tasteprofile();
    foreach ($profile as $taste) {
        expect($taste->selectionValue)->not->toBeEmpty();
        expect($taste->impressionValue)->not->toBeEmpty();
        expect($taste->thumbnails)->toBeArray();
        expect($taste::class)->toBe("Ytmusicapi\\TasteProfile");
    }

    $artists = array_slice(array_keys($profile), 0, 5);

    // I guess we just expect this to not throw an exception
    $yt->set_tasteprofile($artists, $profile);
});

test("set_tasteprofile() - without sending in tasteprofile", function () {
    $yt = new YTMusic("oauth.json");

    $profile = $yt->get_tasteprofile();
    $artists = array_slice(array_keys($profile), 0, 5);
    $yt->set_tasteprofile($artists);
})->throwsNoExceptions();

test("set_tasteprofile() - invalid artist", function () {
    $yt = new YTMusic("oauth.json");

    $profile = $yt->get_tasteprofile();
    $artists = ["invalid artist"];

    $profile = [
        "Michael Jackson" => (object)[
            "impressionValue" => null,
            "selectionValue" => null,
        ]
    ];

    expect(fn () => $yt->set_tasteprofile(["John Denver"], $profile))->toThrow(Exception::class);
    expect(fn () => $yt->set_tasteprofile(["Michael Jackson"], $profile))->toThrow(Exception::class);
});

test("get_song_related() and get_lyrics()", function () {
    $yt = new YTMusic();
    $playlist = $yt->get_watch_playlist($this->videoId);

    expect($playlist)->not->toBeEmpty();
    expect($playlist->related)->toBeString();

    $lyrics = $yt->get_lyrics($playlist->lyrics);
    expect($lyrics->lyrics)->not->toBeEmpty();
    expect($lyrics->source)->not->toBeEmpty();

    // Hard to really test fully because responses can vary
    $related = $yt->get_song_related($playlist->related);
    expect($related)->toBeArray();
    expect($related)->not->toBeEmpty();
    foreach ($related as $shelf) {
        expect($shelf::class)->toBe("Ytmusicapi\\Shelf");
    }
});

test("get_song_related() and get_lyrics() exceptions", function () {
    $yt = new YTMusic();
    expect(fn () => $yt->get_lyrics(null))->toThrow(\Exception::class);
    expect(fn () => $yt->get_song_related(null))->toThrow(\Exception::class);
});
