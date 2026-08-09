<?php

use Ytmusicapi\YTMusic;

// Matches ytmusicapi
test("get_home()", function() {
    $yt = ytmusic();
    $result = $yt->get_home();
    expect(sizeof($result))->toBeGreaterThanOrEqual(2);

    $yt = ytbrowser();
    $result = $yt->get_home(limit: 20);
    expect(sizeof($result))->toBeGreaterThanOrEqual(15);

    $types = ytbrowser()->get_api_result_types();

    foreach ($result as $section) {
        foreach ($section->contents as $item) {
            if ($item && !empty($item->artists)) {
                if (!empty($item->artists[0]->id)) {
                    continue 2;
                }

                $name = strtolower($item->artists[0]->name);
                if (!in_array($name, $types)) {
                    continue 2;
                }

                expect(true)->toBe(false);
            }
        }
    }
});

test('get_account()', function () {
    $yt = ytbrowser();
    $account = $yt->get_account();

    expect($account->name)->not->toBeEmpty();
    expect($account->channelId)->not->toBeEmpty();
    expect($account->thumbnails)->toBeArray();
});

test('get_account() - with cookie authentication', function () {
    $browser = json_decode(file_get_contents("browser.json"), true);

    $yt = new YTMusic($browser['cookie'], $browser['x-goog-authuser'] ?? "0");
    $account = $yt->get_account();
    expect($account->name)->not->toBeEmpty();
    expect($account->channelId)->not->toBeEmpty();
    expect($account->thumbnails)->toBeArray();
});

test('get_account() - with manual cookie authentication', function () {
    $browser = json_decode(file_get_contents("browser.json"), true);

    $auth = (object)[
        "cookie" => $browser['cookie'],
        "x-goog-authuser" => $browser['x-goog-authuser'] ?? "0",
    ];

    $yt = new YTMusic(json_encode($auth));
    $account = $yt->get_account();
    expect($account->name)->not->toBeEmpty();
});

test('get_account() - with manual cookie object authentication', function () {
    $browser = json_decode(file_get_contents("browser.json"), true);

    $auth = (object)[
        "cookie" => $browser['cookie'],
        "x-goog-authuser" => $browser['x-goog-authuser'] ?? "0",
        "x-goog-visitor-id" => $browser['x-goog-visitor-id'] ?? ""
    ];

    $yt = new YTMusic($auth);
    $account = $yt->get_account();
    expect($account->name)->not->toBeEmpty();
});

test('get_account() - Invalid credentials', function () {
    $credentials = new YtmusicApi\OAuthCredentials(
        "abc",
        "123"
    );

    $yt = new YTMusic('oauth.json', oauth_credentials: $credentials);
    $yt->get_account();

    expect(true)->toBe(true);
})->expectException("Ytmusicapi\BadOAuthClient");

test('get_song()', function () {
    $yt = ytmusic();

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
    $yt = ytmusic();
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
    $yt = ytmusic();
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
    $yt = ytmusic();
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
    $yt = ytmusic();

    $track = $yt->get_song_info("QDIWWqxqMes");
    expect($track->title)->toBe("Return to Pooh Corner");
    expect($track->playbackMode)->toBe("PLAYBACK_MODE_PAUSED_ONLY");
    expect($track->madeForKids)->toBe(true);
});

test('get_song_info() - really long song', function () {
    $yt = ytmusic();
    $track = $yt->get_song_info("qLooSc5ewIA");
    expect($track->duration)->toBe("10:31:48");
    expect($track->duration_seconds)->toBe(37908);
});

test('get_song_info() - Invalid video ID', function () {
    $yt = ytmusic();
    $track = $yt->get_song_info("aaaaaaaaaaa");
})->throws(Exception::class);

test('get_song_info() - Invalid video ID type', function () {
    $yt = ytmusic();
    $track = $yt->get_song_info(12);
})->throws(Exception::class);

test('get_artist() shows', function () {
    $yt = ytbrowser();

    $results = $yt->get_artist("MPLAUCmMUZbaYdNH0bEd1PAlAqsA");


    // test correctness of related artists
    $related = $results->related->results;

    foreach ($related as $item) {
        expect($item)->toHaveKeys([
            "browseId",
            "subscribers",
            "title",
            "thumbnails",
            "resultType"
        ]);
    }

    foreach ($results->albums->results as $album) {
        expect($album->year)->toBeNumeric();
        if (isset($album->type)) {
            expect($album->type)->not->toMatch('/^\d+$/');
        }
    }

    foreach ($results->singles->results as $single) {
        expect($single->year)->toBeNumeric();
        if (isset($single->type)) {
            expect($single->type)->not->toMatch('/^\d+$/');
        }
    }
});

test('get_artist() - Description', function () {
    $yt = ytmusic();

    $artist = $yt->get_artist("UCJwGWV914kBlV4dKRn7AEFA");

    expect($artist->description)->toContain("under Creative Commons Attribution");
    expect($artist->descriptionRuns[0]->text)->toContain("Hatsune Miku");
    expect($artist->descriptionRuns[0]->text)->not->toContain("under Creative Commons Attribution");
    expect($artist->descriptionRuns[0])->not->toHaveProperty("url");
    expect($artist->descriptionRuns[1])->toHaveProperty("url");
});

test('get_artist_two_column_layout', function () {
    $mockResponse = loadJsonFixture('2026_07_get_artist_two_column');

    $yt = Mockery::mock(YTMusic::class)->makePartial();
    $yt->shouldReceive("_send_request")->andReturn($mockResponse);

    $result = $yt->get_artist("UCTestChannelId");
    expect($result->name)->toBe("Test Artist");
    expect($result->songs->results)->toBeEmpty();
    expect(count($result->albums->results))->toBe(1);
    expect($result->albums->results[0]->title)->toBe("Test Album");
    expect($result->albums->results[0]->browseId)->toBe("MPREb_test123");
});

test('get_artist() and get_artist_albums()', function () {
    $yt = ytbrowser();

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
    $albums = $yt->get_artist_albums($channelId, $params, null);
    expect(count($albums))->toBeGreaterThan(100);
    foreach ($albums as $album) {
        expect($album->browseId)->toStartWith("MPREb_");
        expect($album->playlistId)->toStartWith("OLAK5uy_");
        expect($album->title)->not->toBeEmpty();
        expect($album->thumbnails)->toBeArray();
        expect($album->type)->toBe("Album");
    }
});

test("get_artist() - Artist with no songs and no subscribe", function () {
    $yt = ytmusic();

    $artist = $yt->get_artist("UCK3inMNRNAVUleEbpDU1k2g");

    expect($artist->name)->toBe("SEB");
    expect($artist->thumbnails)->toBeArray();
    expect($artist->shuffleId)->toBeEmpty();
    expect($artist->radioId)->toBeEmpty();
    expect($artist->videos)->toHaveProperty("browseId");
    expect(count($artist->videos->results))->toBeGreaterThan(1);
    expect($artist->playlists)->toHaveProperty("browseId");
    expect($artist->playlists)->toHaveProperty("params");
    expect(count($artist->playlists->results))->toBeGreaterThan(1);
});

test("get_artist() with the MPLA prefix", function () {
    $yt = ytmusic();
    $artist = $yt->get_artist("MPLA" . $this->artistId);

    expect($artist->name)->toBe($this->artistName);
});

test('get_artist_albums() - singles', function () {
    $yt = ytmusic();
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

test('get_artist_albums() - Without prefix', function () {
    $yt = ytbrowser();

    $artist = $yt->get_artist($this->artistId);
    $channelId = substr($artist->albums->browseId, 4);
    $params = $artist->albums->params;
    $albums = $yt->get_artist_albums($channelId, $params, null);
    expect(count($albums))->toBeGreaterThan(100);
});

test("get_album() and get_album_browse_id()", function () {
    $yt = ytmusic();
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
        expect($track::class)->toBe("Ytmusicapi\\AlbumTrack");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        expect($track->views)->not->toBeEmpty();
        $seconds += (int)$track->duration_seconds;
    }

    expect($result->duration_seconds)->toBe($seconds);
    expect($result->other_versions)->toBeArray();

    // Test get_album_browse_id() - this is not working as expected.
    $result = $yt->get_album_browse_id($result->audioPlaylistId);
    expect($result)->toBe($this->albumId);
});

test("get_album() with bad album ID", function () {
    $yt = ytmusic();
    $yt->get_album($this->albumId . "AAA");
})->throws(Exception::class);

test("get_album() with description containing link", function () {
    $mock_response = loadJsonFixture('2026_05_get_album');
    $expected_output = loadJsonFixture('expected_output/2026_05_get_album');

    $yt = Mockery::mock(YTMusic::class)->makePartial();
    $yt->shouldReceive("_send_request")->andReturn($mock_response);

    /** @var \Ytmusicapi\YTMusic $yt */
    $yt->_prepare_session(true);
    $browseId = $yt->get_album_browse_id("OLAK5uy_kW9hN-oBmekJ06jhhfStpwRd5pcRKIztY");
    $result = $yt->get_album($browseId);

    expect($result->description)->toEqual($expected_output->description);
    expect($result->descriptionRuns)->toEqual($expected_output->descriptionRuns);
});

test("get_album() without artist", function () {
    $yt = ytmusic();
    $album = $yt->get_album("MPREb_n1AxZ9F8rF7"); // soundtrack album with no artist info
    expect($album->artists)->toBeEmpty();
    expect($album->audioPlaylistId)->not->toBeEmpty();
    expect(sizeof($album->tracks))->toBe(11);
});


test("get_album() other versions", function () {
    $yt = ytmusic();

    // Eminem - Curtain Call: The Hits (Explicit Variant)
    $album = $yt->get_album("MPREb_LQCAymzbaKJ");
    $variants = $album->other_versions;
    expect(sizeof($variants))->toBeGreaterThan(0);

    $variant = $variants[0];
    expect($variant->type)->toBe("Album");
    expect($variant->title)->toBe($album->title);
    expect(sizeof($variant->artists))->toBe(1);
    expect($variant->artists[0])->toEqual((object)[
        "name" => "Eminem",
        "id" => "UCedvOgsKFzcK3hA5taf3KoQ",
    ]);
    expect($variant->audioPlaylistId)->not->toBeEmpty();
});

test("get_album() other versions - multi artist, single, and clean", function () {
    $yt = ytmusic();

    // Cassö & RAYE - Prada
    $album = $yt->get_album("MPREb_of3qfisa0yU");
    expect($album->isExplicit)->toBe(false);
    expect($album->artists)->toEqual([
        (object)["name" => "cassö", "id" => "UCGWMNnI1Ky5bMcRlr73Cj2Q"],
        (object)["name" => "RAYE", "id" => "UCvyjk7zKlaFyNIPZ-Pyvkng"],
    ]);

    $variant = $album->other_versions[0];
    expect($variant->type)->toBe("Single");
    expect($variant->title)->toBe("Prada");
    expect($variant->isExplicit)->toBe(true);
    expect(sizeof($variant->artists))->toBe(3);
    expect($variant->artists[0]->id)->toBe("UCGWMNnI1Ky5bMcRlr73Cj2Q");
    expect($variant->artists[1]->name)->toBe("RAYE");
    expect($variant->artists[2])->toEqual((object)[
        "id" => "UCb7jnkQW94hzOoWkG14zs4w",
        "name" => "D-Block Europe",
    ]);
    expect($variant->audioPlaylistId)->not->toBeEmpty();
});

test("get_user() and get_user_playlists()", function () {
    $yt = ytmusic();

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
    $yt = ytbrowser();

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
    $yt = ytbrowser();

    $profile = $yt->get_tasteprofile();
    $artists = array_slice(array_keys($profile), 0, 5);
    $yt->set_tasteprofile($artists);
})->throwsNoExceptions();

test("set_tasteprofile() - invalid artist", function () {
    $yt = ytbrowser();

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
    $yt = ytmusic();

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
    foreach ($related as $section) {
        expect($section::class)->toBe("Ytmusicapi\\Shelf");

        if (is_string($section->contents)) {
            continue;
        }

        foreach ($section->contents as $item) {
            if (empty($item->videoId)) {
                continue;
            }

            $value = $item->views ?? $item->album ?? null;
            expect($value)->not->toBeNull();
            expect($item)->not->toBeNull();
            expect($item->title)->not->toBeEmpty();
            expect($item->thumbnails)->toBeArray();
        }
    }
});

test("get_lyrics() - TimedLyrics", function () {
    $yt = ytmusic();

    $playlist = $yt->get_watch_playlist("hpSrLjc5SMs");
    expect($playlist)->not->toBeEmpty();
    expect($playlist->related)->toBeString();

    $lyrics = $yt->get_lyrics($playlist->lyrics, true);
    expect($lyrics->lyrics)->toBeArray();
    expect($lyrics->source)->not->toBeEmpty();
});

test("get_transcript() - TimedLyrics", function () {
    $yt = ytmusic();
    $lyrics = $yt->get_transcript("hpSrLjc5SMs");
    expect($lyrics)->toBeArray();
    expect($lyrics[1]->start)->not->toBeEmpty();
    expect($lyrics[1]->duration)->not->toBeEmpty();
    expect($lyrics[1]->text)->not->toBeEmpty();
});

test("get_song_related() and get_lyrics() exceptions", function () {
    $yt = ytmusic();
    expect(fn () => $yt->get_lyrics(null))->toThrow(\Exception::class);
    expect(fn () => $yt->get_song_related(null))->toThrow(\Exception::class);
});

test("get_user_videos()", function () {
    $channel = "UCus8EVJ7Oc9zINhs-fg8l1Q"; // Turbo

    $yt = ytmusic();
    $user = $yt->get_user($channel);
    $results = $yt->get_user_videos($channel, $user->videos->params);
    expect(count($results))->toBeGreaterThan(100);

    // The python library runs the query again, but expects zero results.
    // I'm not sure why it does this. I must be missing something.
});

test("get_song_credits()", function () {
    $yt = ytmusic();
    $credits = $yt->get_song_credits($this->sample_credits);

    $sections = ["performed_by", "written_by", "produced_by", "music_metadata_provided_by"];

    foreach ($sections as $section) {
        expect(array_key_exists($section, $credits))->toBeTrue();
        expect($credits[$section])->not->toBeNull();
        expect(is_string($credits[$section]->localized_title))->toBeTrue();
        expect(count($credits[$section]->data))->toBeGreaterThan(0);
        foreach ($credits[$section]->data as $item) {
            expect(is_string($item) && !empty($item))->toBeTrue();
            expect(strpos($item, "\n"))->toBeFalse();
        }
    }

    expect(count($credits["performed_by"]->data))->toBe(12);
    expect($credits["performed_by"]->data[0])->toBe("KANGTA");
    expect($credits["written_by"]->data[4])->toBe("Eirik Røland");
    expect($credits["produced_by"]->data[1])->toBe("David Zandén");
    expect($credits["music_metadata_provided_by"]->data[0])->toBe("SM Entertainment");
    expect(count($credits["other_sections"]))->toBe(0);
});