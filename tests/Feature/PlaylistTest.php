<?php

use Ytmusicapi\YTMusic;
use Pest\Exceptions\SkipException;

use Ytmusicapi\ResponseStatus;
use Ytmusicapi\PlaylistSortOrder;

//   public function get_playlist($playlistId, $limit = 100, $related = false, $suggestions_limit = 0, $get_continuations = true)

/**
 * Create a playlist, skipping the test while YTM gates creation for the account.
 *
 * @param YTMusic $yt;
 * @param array ...$args
 */
function create_playlist($yt, ...$args) {
    $playlist_id = "";
    try {
        $playlist_id = $yt->create_playlist(...$args);
    } catch (\Ytmusicapi\YTMusicGatedError $e) {
        test()->markTestSkipped($e->getMessage());
    } catch (\Ytmusicapi\YTMusicUserError $e) {
        test()->markTestSkipped($e->getMessage());
    }

    expect($playlist_id)->toBeString();
    expect($playlist_id)->toStartWith("PL");

    return $playlist_id;
}


/**
 * Run the first edit of a freshly created playlist.
 * YTM rejects these (409 Conflict, or 400 Precondition for collaboration) for up to ~20s
 * after creation, until the new playlist has settled server-side.
 *
 * @param callable $edit
 * @param int $attempts
 * @param int $delay
 */
function retry_playlist_edit($edit, $attempts = 8, $delay = 5) {
    while ($attempts--) {
        try {
            return $edit();
        } catch (\Ytmusicapi\YTMusicServerError $e) {
            if ($attempts <= 0) {
                throw $e;
            }
        }

        sleep($delay);
    }
}

test("get_playlist() - Playlist only", function () {
    $yt = ytmusic();
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
    $yt = ytmusic();
    $playlist = $yt->get_playlist("RDCLAK5uy_kVfoKrYSsJaHx3SLO8mp3WYuRHMrS8U_Q");
    expect($playlist->title)->toBe("Classic Country");
});

test("get_playlist() - large playlist", function () {
    $yt = ytmusic();
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

test("get_playlist() Audio book", function($playlist_id) {
    $yt = ytmusic();

    $playlist = $yt->get_playlist($playlist_id);


    foreach ($playlist->tracks as $track) {
        expect($track->album->id)->not->toBeEmpty();
        expect($track->album->name)->toBe($playlist->title);
    }
})->with(
    [
        "OLAK5uy_nT1mL8aZvxqfIRFN9L8FgIzfvk6HUkd0I",  // Show
        "OLAK5uy_ksLYkcnrOSKYl62uxB3ga2zfBZfCuvnJ4",  // Audiobook
    ]
);

test("get_playlist() - skip continuations", function () {
    $yt = ytmusic();
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

    $track_count = sizeof($playlist->tracks);

    expect($track_count)->toBeGreaterThan(0);
    expect($track_count)->toBeLessThanOrEqual(100);

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

test("get_playlist_author", function () {
    $yt = ytmusic();
    $playlist = $yt->get_playlist("PL9tY0BWXOZFu4vlBOzIOmvT6wjYb2jNiV");

    expect($playlist->artists)->toBeEmpty();
    expect($playlist->author->name)->toBe("Vevo");
    expect($playlist->author->id)->toBe("UC2pmfLm7iq6Ov1UwYrWYkZA");

    $playlist = $yt->get_playlist("RDCLAK5uy_l2pHac-aawJYLcesgTf67gaKU-B9ekk1o");
    expect($playlist->author->name)->toBe("YouTube Music");
    expect($playlist->author->id)->toBeNull();
});

test("Get own playlist + suggestions + related", function () {
    $yt = ytbrowser();

    $playlist = $yt->get_playlist(getenv("OWN_PLAYLIST_ID"), related: true, suggestions_limit: 30);

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
    $yt = ytbrowser();
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

test("get_playlist() with votes", function($playlist_id, $has_vote) {
    $yt = ytbrowser();

    $playlist = $yt->get_playlist($playlist_id);
    $tracks = $playlist->tracks;
    expect(sizeof($tracks))->toBeGreaterThan(0);

    if (!$has_vote) {
        foreach ($tracks as $track) {
            expect($track->communityVoteStatus)->toBe(null);
        }

        return;
    }

    foreach ($tracks as $track) {
        $vote_status = $track->communityVoteStatus;
        expect($vote_status)->not->toBeEmpty();
        expect((int)$vote_status->netVoteValue)->toBeGreaterThan(0);
        expect($vote_status->status)->toBeInstanceOf(\Ytmusicapi\VoteStatus::class);
    }
})->with(
    [
        // Settings:
        // Title: "Playlist with votes"
        // Description: ""
        // Privacy: unlisted
        // Voting: Everyone
        // Collaboration: On
        // Allow new collaborators: Off
        // 2 videos with id: HDTvoFuHtN0, QD3vEctbWGg
        ["PLa90Y86mjW3fKMrV_EPZ2-WZH8a50ss-b", true],
        // Settings:
        // Title: "Playlist without votes"
        // Description: ""
        // Privacy: unlisted
        // Voting: Voting off
        // Collaboration: On
        // Allow new collaborators: Off
        // 2 videos with id: HDTvoFuHtN0, QD3vEctbWGg
        ["PLa90Y86mjW3d57WTbI8aBp6Cgx9MHOuHD", false],
    ]
);

test("Edit playlist", function () {
    $yt = ytbrowser();

    $playlist = $yt->get_playlist(getenv("OWN_PLAYLIST_ID"));

    $response = $yt->edit_playlist(
        playlistId: getenv("OWN_PLAYLIST_ID"),
        title: "New title",
        description: "New description",
        privacyStatus: "PRIVATE",
        moveItem: [$playlist->tracks[1]->setVideoId, $playlist->tracks[0]->setVideoId],
        addToTop: true,
    );

    expect($response)->toBe("STATUS_SUCCEEDED");

    sleep(5); // Wait for changes to take effect

    $updated_list = $yt->get_playlist(getenv("OWN_PLAYLIST_ID"));

    expect($updated_list->title)->toBe("New title");
    expect($updated_list->description)->toBe("New description");
    expect($updated_list->privacy)->toBe("PRIVATE");
    expect($playlist->tracks[0]->title)->toBe($updated_list->tracks[1]->title);
    expect($playlist->tracks[1]->title)->toBe($updated_list->tracks[0]->title);

    // Revert changes
    $response = $yt->edit_playlist(
        playlistId: getenv("OWN_PLAYLIST_ID"),
        title: $playlist->title,
        description: $playlist->description,
        privacyStatus: $playlist->privacy,
        moveItem: [$playlist->tracks[0]->setVideoId, $playlist->tracks[1]->setVideoId],
        addToTop: false,
    );
})->skip(getenv("OWN_PLAYLIST_ID") === false, "OWN_PLAYLIST_ID not set in environment variables");

test("What happens if I send in an invalid privacy status?", function () {
    $yt = ytbrowser();
    $yt->edit_playlist(
        $this->playlistId,
        privacyStatus: "INVALID",
    );
})->throws(\Exception::class);

test("Big create, add to, and delete test of library", function () {
    $yt = ytbrowser();

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
    expect(fn () => $yt->get_playlist($playlistId))->toThrow(Exception::class);
})->skip();

test("create_playlist() - Using video ids", function () {
    $yt = ytbrowser();

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
    $yt = ytbrowser();
    $bad_delete = [
        (object)["videoId" => "aaaaaaaaaaa", "setVideoId" => ""],
    ];
    $yt->remove_playlist_items($this->playlistId, $bad_delete);
})->throws(\Exception::class);

test("create_playlist() - fail", function () {
    $credentials = new YtmusicApi\OAuthCredentials(
        "abc",
        "123"
    );
    $yt = Mockery::mock(YTMusic::class, ["oauth.json", null, null, null, null, null, $credentials])->makePartial();

    $yt->shouldReceive("_send_request")->andReturn("");
    $yt->create_playlist("test", "", source_playlist: "aaaaaaaaaaa");
})->throws(\Exception::class, "Failed to create playlist");

test("create_playlist() - should fail sending in both video_ids and source_playlist", function () {
    $yt = ytbrowser();
    $yt->create_playlist("test", "", source_playlist: "aaaaaaaaaaa", video_ids: ["aaaaaaaaaaa"]);
})->throws(\Exception::class, "You can't specify both video_ids and source_playlist");

test("create_playlist() - should fail sending in invalid privacy status", function () {
    $yt = ytbrowser();
    $yt->create_playlist("test", "", "BLAH");
})->throws(\Exception::class, "Invalid privacy status, must be one of PUBLIC, PRIVATE, or UNLISTED");

test("add_playlist_items() - should fail when not sending in video_ids or source_playlist", function () {
    $yt = ytbrowser();
    $yt->add_playlist_items($this->playlistId, []);
})->throws(\Exception::class, "You must provide either videoIds or a source_playlist to add to the playlist");

test("remove_playlist_items() - Provide empty list of videos", function () {
    $yt = ytbrowser();
    $yt->remove_playlist_items($this->playlistId, []);
})->throws(\Exception::class, "Cannot remove songs, because setVideoId is missing. Do you own this playlist?");

test("remove_playlist_items() - Provide playlist no owned by user", function () {
    $yt = ytbrowser();

    $playlist = $yt->get_playlist($this->playlistId);
    $yt->remove_playlist_items($this->albumPlaylistId, $playlist->tracks);
})->throws(\Exception::class, "Cannot remove songs, because setVideoId is missing. Do you own this playlist?");

test("remove_playlist_items() - Invalid status response", function () {
    $videos = [
        (object)["videoId" => "aaaaaaaaaaa", "setVideoId" => "aaaaaaaaaaa"],
    ];

    $credentials = new YtmusicApi\OAuthCredentials(
        "abc",
        "123"
    );
    $yt = Mockery::mock(YTMusic::class, ["oauth.json", null, null, null, null, null, $credentials])->makePartial();

    $yt->shouldReceive("_send_request")->andReturn((object)["context" => "test"]);
    $response = $yt->remove_playlist_items($this->playlistId, $videos);

    expect($response->context)->toBe("test");
});

test("add_playlist_items() - Invalid response", function () {
    $credentials = new YtmusicApi\OAuthCredentials(
        "abc",
        "123"
    );
    $yt = Mockery::mock(YTMusic::class, ["oauth.json", null, null, null, null, null, $credentials])->makePartial();

    $yt->shouldReceive("_send_request")->andReturn((object)["context" => "test"]);
    $response = $yt->add_playlist_items($this->playlistId, [$this->videoId]);
    expect($response->context)->toBe("test");
});

test("create_playlist() - Invalid response", function () {
    $credentials = new YtmusicApi\OAuthCredentials(
        "abc",
        "123"
    );
    $yt = Mockery::mock(YTMusic::class, ["oauth.json", null, null, null, null, null, $credentials])->makePartial();

    $yt->shouldReceive("_send_request")->andReturn((object)["context" => "test"]);
    $response = $yt->create_playlist("test", "", "PRIVATE", [$this->videoId]);
    expect($response->context)->toBe("test");
});


test("long playlist", function () {
    $yt = ytbrowser();

    $playlist_id = create_playlist(
        $yt,
        title: "test long list",
        description: "a long list",
        privacy_status: "UNLISTED"
    );

    try {
        $response = retry_playlist_edit(
            fn () => $yt->edit_playlist($playlist_id, collaboration: true, sortOrder: PlaylistSortOrder::TOP_VOTED)
        );
        expect($response->status)->toBe(ResponseStatus::SUCCEEDED);

        $track_ids = array_fill(0, 100, "lYBUbBu4W08");
        $response = $yt->add_playlist_items($playlist_id, $track_ids, duplicates: true);
        expect($response->status)->toBe(ResponseStatus::SUCCEEDED);

        $track_ids = ["lYBUbBu4W08"];
        $response = $yt->add_playlist_items($playlist_id, $track_ids, duplicates: true);
        expect($response->status)->toBe(ResponseStatus::SUCCEEDED);

        // For some reason a collaborative playlist isn't allowing all 101 tracks
        $playlist = $yt->get_playlist($playlist_id, limit: null);
        expect(count($playlist->tracks))->toBe(101);
    } finally {
        sleep(3);
        echo "Deleting playlist\n";
        $yt->delete_playlist($playlist_id);
    }
})->only();

test("edit_playlist_collaboration", function () {
    $yt = ytbrowser();

    $playlist_id = create_playlist($yt, "test collaboriation", "", privacy_status: "UNLISTED");
    echo "Playlist: " . $playlist_id . "\n";

    try {
        $response = retry_playlist_edit(
            fn () => $yt->edit_playlist($playlist_id, collaboration: true, sortOrder: PlaylistSortOrder::TOP_VOTED)
        );

        expect($response->status)->toBe(ResponseStatus::SUCCEEDED);

        $join_collaboration_token = $response->joinCollaborationToken;
        expect($join_collaboration_token)->not->toBeEmpty();

        $track_ids = array_fill(0, 101, "lYBUbBu4W08");
        $response = $yt->add_playlist_items(
            $playlist_id, $track_ids, duplicates: true
        );

        expect($response->status)->toBe(ResponseStatus::SUCCEEDED);

        sleep(15); // wait for collaboration to be enabled

        // TODO: Join another account with join_collaborative_playlist

        $playlist = $yt->get_playlist($playlist_id, limit: null);
        expect(count($playlist->collaborators->avatars))->toBe(1);

        expect($playlist->author)->toBeEmpty();

        // we should have continuations for large vote-sorted playlists
        expect(count($playlist->tracks))->toBe(101);

        // Disable collaboration
        $result = $yt->edit_playlist($playlist_id, collaboration: false);
        expect($result)->toBe(ResponseStatus::SUCCEEDED);

        sleep(3);

        $playlist = $yt->get_playlist($playlist_id);
        expect($playlist->collaborators)->toBeEmpty();
        expect($playlist->author)->not->toBeEmpty();
    } finally {
        $yt->delete_playlist($playlist_id);
    }
})->skip();