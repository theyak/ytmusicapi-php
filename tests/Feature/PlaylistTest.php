<?php

use Ytmusicapi\YTMusic;

//   public function get_playlist($playlistId, $limit = 100, $related = false, $suggestions_limit = 0, $get_continuations = true)

test("get_playlist() - Playlist only", function () {
    $yt = new YTMusic();
    $playlist = $yt->get_playlist($this->playlistId, limit: 1);

    expect($playlist)->not()->toBeEmpty();
    expect($playlist->title)->toBe($this->playlistTitle);
    expect($playlist->author->name)->toBe($this->playlistAuthor);
    expect((int)$playlist->year)->toBeGreaterThan(2000);
    expect($playlist->duration_seconds)->toBeGreaterThan(100);

    // Playlist return time in a human readable format,
    // such as "2 hours 30 minutes" or  "7+ hours."
    // We'll just check that it's not empty.
    expect($playlist->duration)->not->toBeEmpty();

    // There's kind of a bug in get_playlist() that always
    // returns the first set of continuations, regardless
    // of the limit passed in. Since playlist loads 100 tracks
    // at a time, and 2 pages worth are always loaded, if
    // tracks are available, we test the length to be
    // less than or equal to 200 instead of 100.
    expect(count($playlist->tracks))->toBeGreaterThan(90);
    expect(count($playlist->tracks))->toBeLessThanOrEqual(200);

    foreach ($playlist->tracks as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        if ($track->isAvailable) {
            expect($track->videoId)->toHaveLength(11);
            expect($track->thumbnails)->toBeArray();
            expect($track->duration_seconds)->toBeGreaterThan(0);
        }
        expect($track->title)->not->toBeEmpty();
    }
});

test("get_playlist() - radio", function () {
    $yt = new YTMusic();
    $playlist = $yt->get_playlist("RDCLAK5uy_kVfoKrYSsJaHx3SLO8mp3WYuRHMrS8U_Q");
    expect($playlist->title)->toBe("Classic Country");
});

test("get_playlist() - large playlist", function () {
    $yt = new YTMusic();
    $playlist = $yt->get_playlist($this->playlistId, limit: 250);

    expect($playlist)->not()->toBeEmpty();
    expect($playlist->title)->toBe($this->playlistTitle);
    expect($playlist->author->name)->toBe($this->playlistAuthor);
    expect((int)$playlist->year)->toBeGreaterThan(2000);
    expect($playlist->duration_seconds)->toBeGreaterThan(100);

    // Playlist return time in a human readable format,
    // such as "2 hours 30 minutes" or  "7+ hours."
    // We'll just check that it's not empty.
    expect($playlist->duration)->not->toBeEmpty();

    expect(count($playlist->tracks))->toBeGreaterThan(250);
    expect(count($playlist->tracks))->toBeLessThanOrEqual(400);

    foreach ($playlist->tracks as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        if ($track->isAvailable) {
            expect($track->videoId)->toHaveLength(11);
            expect($track->thumbnails)->toBeArray();
            expect($track->duration_seconds)->toBeGreaterThan(0);
        }
        expect($track->title)->not->toBeEmpty();
    }
});


test("get_playlist() - skip continuations", function () {
    $yt = new YTMusic();
    $playlist = $yt->get_playlist($this->playlistId, limit: 1, get_continuations: false);

    expect($playlist)->not()->toBeEmpty();
    expect($playlist->title)->toBe($this->playlistTitle);
    expect($playlist->author->name)->toBe($this->playlistAuthor);
    expect((int)$playlist->year)->toBeGreaterThan(2000);
    expect($playlist->duration_seconds)->toBeGreaterThan(100);
    expect($playlist->continuation)->not->toBeEmpty();

    // Playlist return time in a human readable format,
    // such as "2 hours 30 minutes" or  "7+ hours."
    // We'll just check that it's not empty.
    expect($playlist->duration)->not->toBeEmpty();

    expect(count($playlist->tracks))->toBeGreaterThan(0);
    expect(count($playlist->tracks))->toBeLessThanOrEqual(100);

    foreach ($playlist->tracks as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        if ($track->isAvailable) {
            expect($track->videoId)->toHaveLength(11);
            expect($track->thumbnails)->toBeArray();
            expect($track->duration_seconds)->toBeGreaterThan(0);
        }
        expect($track->title)->not->toBeEmpty();
    }

    $continuation = $playlist->continuation;
    $playlist = $yt->get_playlist_continuation($this->playlistId, $continuation);

    expect($playlist->id)->toBe($this->playlistId);
    expect(count($playlist->tracks))->toBeGreaterThan(0);
    expect(count($playlist->tracks))->toBeLessThanOrEqual(100);
});

test("Get own playlist + suggestions + related", function () {
    $yt = new YTMusic("oauth.json");

    $playlist = $yt->get_playlist($this->ownPlaylistId, related: true, suggestions_limit: 30);

    expect($playlist->suggestions)->toBeArray();
    expect(count($playlist->suggestions))->toBeGreaterThan(0);

    foreach ($playlist->suggestions as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        if ($track->isAvailable) {
            expect($track->videoId)->toHaveLength(11);
            expect($track->thumbnails)->toBeArray();
            expect($track->duration_seconds)->toBeGreaterThan(0);
        }
        expect($track->title)->not->toBeEmpty();
    }

    expect($playlist->related)->toBeArray();
    foreach ($playlist->related as $related) {
        expect($related::class)->toBe("Ytmusicapi\\PlaylistInfo");
        expect($related->title)->not->toBeEmpty();
        expect($related->playlistId)->not->toBeEmpty();
    }
})->skip(getenv("OWN_PLAYLIST_ID") === false, "OWN_PLAYLIST_ID not set in environment variables");

test("Get liked music", function () {
    $yt = new YTMusic("oauth.json");
    $playlist = $yt->get_playlist("LM");

    expect($playlist)->toHaveProperty('id');
    expect($playlist->id)->toBe("LM");
    expect($playlist->title)->toBe("Liked Music");
    expect($playlist->privacy)->toBe("PRIVATE"); // This differs from Python version
    expect($playlist->thumbnails)->toBeArray();
    expect($playlist->tracks)->toBeArray();
    expect($playlist->description)->not->toBeEmpty();
    expect($playlist->tracks)->toBeArray();
    foreach ($playlist->tracks as $track) {
        expect($track->videoId)->toHaveLength(11);
        expect($track->title)->not->toBeEmpty();
        expect($track->likeStatus)->toBeIn(["LIKE", "DISLIKE", "INDIFFERENT"]);
        expect($track->isAvailable)->toBeBool();
        expect($track->isExplicit)->toBeBool();
        expect($track->inLibrary)->toBeBool();
        expect($track->duration)->not->toBeEmpty();
        expect($track->duration_seconds)->toBeInt();
        expect($track->videoType)->toBeIn(["MUSIC_VIDEO_TYPE_ATV", "MUSIC_VIDEO_TYPE_OMV", "MUSIC_VIDEO_TYPE_UGC"]);
        expect($track->artists)->toBeArray();
        expect($track->thumbnails)->toBeArray();
        expect($track)->toHaveProperty('album');
        expect($track)->toHaveProperty('feedbackTokens');

        if ($track->album) {
            expect($track->album)->toHaveProperty('name');
            expect($track->album)->toHaveProperty('id');
        }
        if ($track->feedbackTokens) {
            expect($track->feedbackTokens)->toHaveProperty("add");
            expect($track->feedbackTokens)->toHaveProperty("remove");
        }
    }
});

test("Edit playlist", function () {
    $yt = new YTMusic("oauth.json");

    $playlist = $yt->get_playlist($this->ownPlaylistId);

    $response = $yt->edit_playlist(
        $this->ownPlaylistId,
        title: "New title",
        description: "New description",
        privacyStatus: "PRIVATE",
        moveItem: [$playlist->tracks[1]->setVideoId, $playlist->tracks[0]->setVideoId],
        addToTop: true,
    );

    expect($response)->toBe("STATUS_SUCCEEDED");

    sleep(5); // Wait for changes to take effect

    $updated_list = $yt->get_playlist($this->ownPlaylistId);

    expect($updated_list->title)->toBe("New title");
    expect($updated_list->description)->toBe("New description");
    expect($updated_list->privacy)->toBe("PRIVATE");
    expect($playlist->tracks[0]->title)->toBe($updated_list->tracks[1]->title);
    expect($playlist->tracks[1]->title)->toBe($updated_list->tracks[0]->title);

    // Revert changes
    $response = $yt->edit_playlist(
        $this->ownPlaylistId,
        title: $playlist->title,
        description: $playlist->description,
        privacyStatus: $playlist->privacy,
        moveItem: [$playlist->tracks[0]->setVideoId, $playlist->tracks[1]->setVideoId],
        addToTop: false,
    );
})->skip(getenv("OWN_PLAYLIST_ID") === false, "OWN_PLAYLIST_ID not set in environment variables");

test("What happens if I send in an invalid privacy status?", function () {
    $yt = new YTMusic("oauth.json");
    $yt->edit_playlist(
        $this->playlistId,
        privacyStatus: "INVALID",
    );
})->throws(\Exception::class);

test("Big create, add to, and delete test of library", function () {
    $yt = new YTMusic("oauth.json");

    // Carin Leon - Colmillo de Leche, 16 tracks
    $colmillo = "OLAK5uy_lhHr2ATl41N4kOuCcPc3wo1nRYtakCqFc";

    // Morgan Wallen - If I Know Me, 14 tracks
    $ifIKnowMe = "OLAK5uy_kakaXXhttloKxThsJH1B6xGeoh6Ja3HYg";

    // Darell - La Verdadera Vuelta, 11 tracks
    $verdadera = "OLAK5uy_kEzCarG9kWxiRbDeqGvD94tD4d4h6O_A8";

    // Harry Styles - Harry Styles, 10 tracks
    $harryStyles = "OLAK5uy_nY8rMT2-JM5ftt_M8I6uoTcDrsASzjV7w";

    $playlistId = $yt->create_playlist("test", "test description", "PRIVATE", null, $colmillo);

    $yt->edit_playlist($playlistId, addToTop: true);

    $response = $yt->add_playlist_items(
        $playlistId,
        [$this->videoId, $this->videoId],
        source_playlist: $ifIKnowMe,
        duplicates: true,
    );
    expect($response->status)->toBe("STATUS_SUCCEEDED");
    expect($response->playlistEditResults)->toBeArray();
    expect(count($response->playlistEditResults))->toBeGreaterThan(0);

    // add_playlist_items() with only a source playlist, no videos
    $response = $yt->add_playlist_items(
        $playlistId,
        [],
        source_playlist: $harryStyles,
        duplicates: true,
    );
    expect($response->status)->toBe("STATUS_SUCCEEDED");

    $yt->edit_playlist($playlistId, addPlaylistId: $verdadera);

    sleep(2); // Wait for changes to take effect

    $yt->edit_playlist($playlistId, addToTop: false);
    $playlist = $yt->get_playlist($playlistId);
    expect(count($playlist->tracks))->toBe(53);

    // This checks that added tracks were added to top:
    expect($playlist->tracks[0]->videoId)->toBe("FBxJC58N1vs");

    $response = $yt->remove_playlist_items($playlistId, $playlist->tracks);
    expect($response)->toBe("STATUS_SUCCEEDED");

    sleep(2); // Wait for changes to take effect

    $playlist = $yt->get_playlist($playlistId);
    expect(count($playlist->tracks))->toBe(0);

    $yt->delete_playlist($playlistId);

    sleep(2);

    // Playlist no longer exists. Should throw an exception.
    expect(fn() => $yt->get_playlist($playlistId))->toThrow(Exception::class);
});

test("create_playlist() - Using video ids", function () {
    $yt = new YTMusic("oauth.json");
    $playlistId = $yt->create_playlist("test", "test description", "PRIVATE", [$this->videoId]);

    sleep(2);

    $playlist = $yt->get_playlist($playlistId);
    expect($playlist->title)->toBe("test");
    expect($playlist->description)->toBe("test description");
    expect($playlist->privacy)->toBe("PRIVATE");
    expect(count($playlist->tracks))->toBe(1);

    $yt->delete_playlist($playlistId);
});

test("Bad remove_playlist_items() parameter - no setVideoId", function () {
    $yt = new YTMusic("oauth.json");
    $bad_delete = [
        (object)["videoId" => "aaaaaaaaaaa", "setVideoId" => ""],
    ];
    $yt->remove_playlist_items($this->playlistId, $bad_delete);
})->throws(\Exception::class);

test("create_playlist() - fail", function () {
    $yt = Mockery::mock(YTMusic::class, ["oauth.json"])->makePartial();
    $yt->shouldReceive("_send_request")->andReturn("");
    $yt->create_playlist("test", "", source_playlist: "aaaaaaaaaaa");
})->throws(\Exception::class, "Failed to create playlist");

test("create_playlist() - should fail sending in both video_ids and source_playlist", function () {
    $yt = new YTMusic("oauth.json");
    $yt->create_playlist("test", "", source_playlist: "aaaaaaaaaaa", video_ids: ["aaaaaaaaaaa"]);
})->throws(\Exception::class, "You can't specify both video_ids and source_playlist");

test("create_playlist() - should fail sending in invalid privacy status", function () {
    $yt = new YTMusic("oauth.json");
    $yt->create_playlist("test", "", "BLAH");
})->throws(\Exception::class, "Invalid privacy status, must be one of PUBLIC, PRIVATE, or UNLISTED");

test("add_playlist_items() - should fail when not sending in video_ids or source_playlist", function () {
    $yt = new YTMusic("oauth.json");
    $yt->add_playlist_items($this->playlistId, []);
})->throws(\Exception::class, "You must provide either videoIds or a source_playlist to add to the playlist");

test("remove_playlist_items() - Provide empty list of videos", function () {
    $yt = new YTMusic("oauth.json");
    $yt->remove_playlist_items($this->playlistId, []);
})->throws(\Exception::class, "Cannot remove songs, because setVideoId is missing. Do you own this playlist?");

test("remove_playlist_items() - Provide playlist no owned by user", function () {
    $yt = new YTMusic("oauth.json");

    $playlist = $yt->get_playlist($this->playlistId);
    $yt->remove_playlist_items($this->albumPlaylistId, $playlist->tracks);
})->throws(\Exception::class, "Cannot remove songs, because setVideoId is missing. Do you own this playlist?");

test("remove_playlist_items() - Invalid status response", function () {
    $videos = [
        (object)["videoId" => "aaaaaaaaaaa", "setVideoId" => "aaaaaaaaaaa"],
    ];
    $yt = Mockery::mock(YTMusic::class, ["oauth.json"])->makePartial();
    $yt->shouldReceive("_send_request")->andReturn((object)["context" => "test"]);
    $response = $yt->remove_playlist_items($this->playlistId, $videos);

    expect($response->context)->toBe("test");
});

test("add_playlist_items() - Invalid response", function () {
    $yt = Mockery::mock(YTMusic::class, ["oauth.json"])->makePartial();
    $yt->shouldReceive("_send_request")->andReturn((object)["context" => "test"]);
    $response = $yt->add_playlist_items($this->playlistId, [$this->videoId]);
    expect($response->context)->toBe("test");
});

test("create_playlist() - Invalid response", function () {
    $yt = Mockery::mock(YTMusic::class, ["oauth.json"])->makePartial();
    $yt->shouldReceive("_send_request")->andReturn((object)["context" => "test"]);
    $response = $yt->create_playlist("test", "", "PRIVATE", [$this->videoId]);
    expect($response->context)->toBe("test");
});
