<?php

use Ytmusicapi\YTMusic;

it("should have library playlists w/browser authentication", function () {
    $yt = new YTMusic("browser.json");
    $library_playlists = $yt->get_library_playlists(30);
    expect($library_playlists)->toBeArray();
    expect(count($library_playlists))->toBeGreaterThan(0);
    foreach ($library_playlists as $playlist) {
        expect($playlist::class)->toBe("Ytmusicapi\\PlaylistInfo");
        expect($playlist->title)->not->toBeEmpty();
        expect($playlist->playlistId)->not->toBeEmpty();
        expect($playlist->thumbnails)->toBeArray();
        expect($playlist->count)->toBeInt();
        expect($playlist->description)->not->toBeEmpty();
        expect($playlist->author)->toBeArray();
    }
});

it("should have liked songs w/cookie authentication", function () {
    $browser = json_decode(file_get_contents("browser.json"), true);

    $yt = new YTMusic($browser['cookie'], $browser['x-goog-authuser'] ?? "0");
    $playlist = $yt->get_liked_songs();

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

test("get_library_songs() without continuation", function () {
    $yt = new YTMusic("oauth.json");
    $songs = $yt->get_library_songs(20, false, 'a_to_z');

    expect(count($songs))->toBeGreaterThan(0);
    expect(count($songs))->toBeLessThanOrEqual(25);

    foreach ($songs as $song) {
        expect($song::class)->toBe("Ytmusicapi\\Track");
    }

    foreach ($songs as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        expect($track->artists)->toBeArray();
        if ($track->album) {
            expect($track->album->name)->not->toBeEmpty();
            expect($track->album->id)->not->toBeEmpty();
        }
        expect($track->thumbnails)->toBeArray();
        expect($track->likeStatus)->not->toBeEmpty();
        expect($track->inLibrary)->toBeTrue();
        expect($track->isAvailable)->toBeBool();
        expect($track->isExplicit)->toBeBool();
        expect($track->duration)->not->toBeEmpty();
        expect($track->duration_seconds)->toBeInt();
        expect($track->setVideoId)->toBeEmpty(); // Not used for library songs
        expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV"); // No videos in library songs
        if ($track->feedbackTokens) {
            expect($track->feedbackTokens)->toHaveProperty("add");
            expect($track->feedbackTokens)->toHaveProperty("remove");
        }
    }
});

test("get_library_songs() with continuation", function () {
    $yt = new YTMusic("oauth.json");
    $songs = $yt->get_library_songs(200, false, 'z_to_a');

    expect(count($songs))->toBeGreaterThan(25);

    foreach ($songs as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        expect($track->artists)->toBeArray();
        if ($track->album) {
            expect($track->album->name)->not->toBeEmpty();
            expect($track->album->id)->not->toBeEmpty();
        }
        expect($track->thumbnails)->toBeArray();
        expect($track->likeStatus)->not->toBeEmpty();
        expect($track->inLibrary)->toBeTrue();
        expect($track->isAvailable)->toBeBool();
        expect($track->isExplicit)->toBeBool();
        expect($track->duration)->not->toBeEmpty();
        expect($track->duration_seconds)->toBeInt();
        expect($track->setVideoId)->toBeEmpty(); // Not used for library songs
        expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV"); // No videos in library songs
        if ($track->feedbackTokens) {
            expect($track->feedbackTokens)->toHaveProperty("add");
            expect($track->feedbackTokens)->toHaveProperty("remove");
        }
    }
});

test("get_library_songs() with verification but without continuation", function () {
    $yt = new YTMusic("oauth.json");
    $songs = $yt->get_library_songs(20, true, 'a_to_z');

    expect(count($songs))->toBeGreaterThan(0);
    expect(count($songs))->toBeLessThanOrEqual(25);

    foreach ($songs as $song) {
        expect($song::class)->toBe("Ytmusicapi\\Track");
    }

    foreach ($songs as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        expect($track->artists)->toBeArray();
        if ($track->album) {
            expect($track->album->name)->not->toBeEmpty();
            expect($track->album->id)->not->toBeEmpty();
        }
        expect($track->thumbnails)->toBeArray();
        expect($track->likeStatus)->not->toBeEmpty();
        expect($track->inLibrary)->toBeTrue();
        expect($track->isAvailable)->toBeBool();
        expect($track->isExplicit)->toBeBool();
        expect($track->duration)->not->toBeEmpty();
        expect($track->duration_seconds)->toBeInt();
        expect($track->setVideoId)->toBeEmpty(); // Not used for library songs
        expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV"); // No videos in library songs
        if ($track->feedbackTokens) {
            expect($track->feedbackTokens)->toHaveProperty("add");
            expect($track->feedbackTokens)->toHaveProperty("remove");
        }
    }
});

test("get_library_songs() with continuation and verification", function () {
    $yt = new YTMusic("oauth.json");
    $songs = $yt->get_library_songs(200, true, 'z_to_a');

    expect(count($songs))->toBeGreaterThan(25);

    foreach ($songs as $track) {
        expect($track::class)->toBe("Ytmusicapi\\Track");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        expect($track->artists)->toBeArray();
        if ($track->album) {
            expect($track->album->name)->not->toBeEmpty();
            expect($track->album->id)->not->toBeEmpty();
        }
        expect($track->thumbnails)->toBeArray();
        expect($track->likeStatus)->not->toBeEmpty();
        expect($track->inLibrary)->toBeTrue();
        expect($track->isAvailable)->toBeBool();
        expect($track->isExplicit)->toBeBool();
        expect($track->duration)->not->toBeEmpty();
        expect($track->duration_seconds)->toBeInt();
        expect($track->setVideoId)->toBeEmpty(); // Not used for library songs
        expect($track->videoType)->toBe("MUSIC_VIDEO_TYPE_ATV"); // No videos in library songs
        if ($track->feedbackTokens) {
            expect($track->feedbackTokens)->toHaveProperty("add");
            expect($track->feedbackTokens)->toHaveProperty("remove");
        }
    }
});

test("get_library_song() - bad parameters", function () {
    $yt = new YTMusic("oauth.json");
    $yt->get_library_songs(null, true);
})->throws(\Exception::class);

test("get_library_albums()", function () {
    $yt = new YTMusic("oauth.json");
    $albums = $yt->get_library_albums(30, "a_to_z");

    foreach ($albums as $album) {
        expect($album::class)->toBe("Ytmusicapi\\AlbumInfo");
        expect($album->browseId)->not->toBeEmpty();
        expect($album->playlistId)->not->toBeEmpty();
        expect($album->title)->not->toBeEmpty();
        expect($album->thumbnails)->toBeArray();
        expect($album->type)->toBeIn(["Single", "Album"]);
        expect($album->artists)->not->toBeEmpty();
        expect($album->year)->not->toBeEmpty();
        expect($album->isExplicit)->toBeBool();
    }
});

test("get_library_artists()", function () {
    $yt = new YTMusic("oauth.json");
    $artists = $yt->get_library_artists(30, "a_to_z");
    expect(count($artists))->toBeGreaterThan(0);

    foreach ($artists as $artist) {
        expect($artist::class)->toBe("Ytmusicapi\\ArtistInfo");
        expect($artist->browseId)->not->toBeEmpty();
        expect($artist->browseId)->toStartWith("MPLAUC");
        expect($artist->artist)->not->toBeEmpty();
        expect($artist->songs)->not->toBeEmpty();
        expect($artist->thumbnails)->toBeArray();
    }
});

test("get_library_artists() with continuation", function () {
    $yt = new YTMusic("oauth.json");
    $artists = $yt->get_library_artists(limit: 30);
    expect(count($artists))->toBeGreaterThan(25);

    foreach ($artists as $artist) {
        expect($artist::class)->toBe("Ytmusicapi\\ArtistInfo");
        expect($artist->browseId)->not->toBeEmpty();
        expect($artist->browseId)->toStartWith("MPLAUC");
        expect($artist->artist)->not->toBeEmpty();
        expect($artist->songs)->not->toBeEmpty();
        expect($artist->thumbnails)->toBeArray();
    }
});

test("get_library_subscriptions()", function () {
    $yt = new YTMusic("oauth.json");
    $subscriptions = $yt->get_library_subscriptions(30, "a_to_z");

    foreach ($subscriptions as $artist) {
        expect($artist::class)->toBe("Ytmusicapi\\ArtistInfo");
        expect($artist->browseId)->not->toBeEmpty();
        expect($artist->browseId)->toStartWith("UC");
        expect($artist->artist)->not->toBeEmpty();
        expect($artist->songs)->toBeNull();
        expect($artist->subscribers)->not->toBeEmpty();
        expect($artist->thumbnails)->toBeArray();
    }
});

test("add_history_item() and get_history()", function () {

    $yt = new YTMusic("oauth.json");
    $yt->add_history_item($this->videoId);

    sleep(2);

    $history = $yt->get_history();

    $first = reset($history);
    expect($first->videoId)->toBe($this->videoId);

    foreach ($history as $track) {
        expect($track::class)->toBe("Ytmusicapi\\HistoryTrack");
        expect($track->title)->not->toBeEmpty();
        expect($track->videoId)->not->toBeEmpty();
        expect($track->artists)->toBeArray();
        if ($track->album) {
            expect($track->album->name)->not->toBeEmpty();
            expect($track->album->id)->not->toBeEmpty();
        }
        expect($track->thumbnails)->toBeArray();
        expect($track->inLibrary)->toBeBool();
        expect($track->isAvailable)->toBeBool();
        expect($track->isExplicit)->toBeBool();
        // expect($track->duration)->not->toBeEmpty(); // Fun fact, not all history items have duration
        // expect($track->likeStatus)->not->toBeEmpty(); // If track isn't available it won't have a like status
        if ($track->duration) {
            expect($track->duration_seconds)->toBeInt();
        }
        expect($track->videoType)->toBeIn(["MUSIC_VIDEO_TYPE_PODCAST_EPISODE", "MUSIC_VIDEO_TYPE_ATV", "MUSIC_VIDEO_TYPE_OMV", "MUSIC_VIDEO_TYPE_UGC"]);
        expect($track->feedbackToken)->not->toBeEmpty();
        if ($track->feedbackTokens) {
            expect($track->feedbackTokens)->toHaveProperty("add");
            expect($track->feedbackTokens)->toHaveProperty("remove");
        }
        expect($track)->not->toHaveProperty("setVideoId");
    }

    $yt->remove_history_items($first->feedbackToken);

    // Problem, it doesn't remove right away, so we introduce an artifical delay.
    // Even this doesn't guarantee it will be enough time for it to be removed.
    sleep(10);

    $history = $yt->get_history();
    $first = reset($history);
    expect($first->videoId)->not->toBe($this->videoId);
});

test("rate_song()", function () {
    $yt = new YTMusic("oauth.json");
    $response = $yt->rate_song($this->videoId, "LIKE");
    expect($response)->toHaveProperty("actions");
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationActionRenderer.responseText.runs.0.text", null);
    expect($text)->toBe("Saved to liked music");

    $response = $yt->rate_song($this->videoId, "INDIFFERENT");
    expect($response)->toHaveProperty("actions");
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationTextRenderer.successResponseText.runs.0.text", null);
    expect($text)->toBe("Removed from liked music");
});

test("edit_song_library_status()", function () {
    $yt = new YTMusic("oauth.json");
    $album = $yt->get_album($this->albumId);

    // Add to libraray
    $add = $album->tracks[0]->feedbackTokens->add;
    expect($add)->not->toBeEmpty();
    $response = $yt->edit_song_library_status($add);
    expect($response)->toHaveProperty("actions");
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationActionRenderer.responseText.runs.0.text", null);
    expect($text)->toBe("Added to library");
    expect($response->feedbackResponses[0]->isProcessed)->toBeTrue();

    // Get album again to check if it's in library
    $album = $yt->get_album($this->albumId);
    expect($album->tracks[0]->inLibrary)->toBeTrue();

    // Remove from library
    $remove = $album->tracks[0]->feedbackTokens->remove;
    $response = $yt->edit_song_library_status($remove);
    expect($response)->toHaveProperty("actions");
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationActionRenderer.responseText.runs.0.text", null);
    expect($text)->toBe("Removed from library");

    // Get album one last time to check that track is not in library
    $album = $yt->get_album($this->albumId);
    expect($album->tracks[0]->inLibrary)->toBeFalse();
    expect($response->feedbackResponses[0]->isProcessed)->toBeTrue();
});

test("rate_playlist()", function () {
    $yt = new YTMusic("oauth.json");
    $response = $yt->rate_playlist($this->playlistId, "LIKE");
    expect($response)->toHaveProperty("actions");
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationActionRenderer.responseText.runs.0.text", null);
    expect($text)->toBe("Saved to library");

    $response = $yt->rate_playlist($this->playlistId, "INDIFFERENT");
    expect($response)->toHaveProperty("actions");
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationTextRenderer.successResponseText.runs.0.text", null);
    expect($text)->toBe("Removed from library");
});

test("subscribe_artists() - pass in array", function () {
    $yt = new YTMusic("oauth.json");
    $response = $yt->subscribe_artists([$this->artistId]);
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationTextRenderer.successResponseText.runs.0.text", null);
    expect($text)->toBe("Subscribed to ");

    $response = $yt->unsubscribe_artists([$this->artistId]);
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationTextRenderer.successResponseText.runs.0.text", null);
    expect($text)->toBe("Unsubscribed from ");
});

test("subscribe_artists() - pass in string", function () {
    $yt = new YTMusic("oauth.json");
    $response = $yt->subscribe_artists($this->artistId);
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationTextRenderer.successResponseText.runs.0.text", null);
    expect($text)->toBe("Subscribed to ");

    $response = $yt->unsubscribe_artists($this->artistId);
    $text = Ytmusicapi\nav($response, "actions.0.addToToastAction.item.notificationTextRenderer.successResponseText.runs.0.text", null);
    expect($text)->toBe("Unsubscribed from ");
});

test("get_history() throws exception with bad data", function () {

    $str = "contents.singleColumnBrowseResultsRenderer.tabs.0.tabRenderer.content.sectionListRenderer.contents.0.itemSectionRenderer.contents";
    $return = Ytmusicapi\denav($str);

    $yt = Mockery::mock(YTMusic::class, ["oauth.json"])->makePartial();
    $yt->shouldReceive("_send_request")->andReturn($return);
    $yt->get_history();
})->throws(\Exception::class);

test("get_library_podcasts", function () {
    $yt = new YTMusic("oauth.json");
    $podcasts = $yt->get_library_podcasts(50, "a_to_z");
    expect(count($podcasts))->toBeGreaterThan(1);

    foreach ($podcasts as $podcast) {
        expect($podcast::class)->toBe("Ytmusicapi\\PodcastShelfItem");
        expect($podcast->title)->not->toBeEmpty();
        expect($podcast->channel)->not->toBeEmpty();
        expect($podcast->browseId)->not->toBeEmpty();
        expect($podcast->podcastId)->not->toBeEmpty();
        expect($podcast->thumbnails)->toBeArray();
    }
});

test("get_library_podcasts - throws when unauthorized", function () {
    $yt = new YTMusic();
    $podcasts = $yt->get_library_podcasts(50, "a_to_z");
    expect(count($podcasts))->toBe(1);
})->throws(\Exception::class);

test("get_library_channels", function () {
    $yt = new YTMusic("oauth.json");
    $channels = $yt->get_library_channels(50, "a_to_z");
    expect(count($channels))->toBeGreaterThan(0);
});

test("get_library_channels - throws when unauthorized", function () {
    $yt = new YTMusic();
    $channels = $yt->get_library_channels(50, "a_to_z");
    expect(count($channels))->toBe(0);
})->throws(\Exception::class);

test("get_account_info", function () {
    $yt = new YTMusic("oauth.json");
    $info = $yt->get_account_info();

    expect($info::class)->toBe("Ytmusicapi\\AccountInfo");
    expect($info->accountName)->not->toBeEmpty();
    expect($info->channelHandle)->not->toBeEmpty();
    expect($info->accountPhotoUrl)->not->toBeEmpty();
});
