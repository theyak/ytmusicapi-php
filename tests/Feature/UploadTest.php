<?php

use Ytmusicapi\YTMusic;

/**
 * Uploads are especially hard to test because they require time to be processed
 * before anything can be done on the uploaded song. For that reason we mark
 * the upload_song() and delete_upload_entity() tests as skipped by default.
 * You can change upload_song() to ->only() and a bit later, change it back
 * to ->skip() and change delete_upload_entity() to ->only() to test the deletion.
 */

test('upload_song()', function () {
    $yt = new YTMusic('browser.json');

    $result = $yt->upload_song('tests/the-shortest-song.mp3');
    expect($result)->toBe("STATUS_SUCCEEDED");
})->skip("Run manually.");

test('delete_upload_entity()', function () {
    $yt = new YTMusic('browser.json');

    $results = $yt->get_library_upload_songs();
    $response = $yt->delete_upload_entity($results[0]->entityId);
    expect($response)->toBe("STATUS_SUCCEEDED");
})->skip("Run manually after upload_song() test.");

test('get_library_upload_albums', function () {
    $yt = new YTMusic('browser.json');

    $results = $yt->get_library_upload_albums();
    expect($results)->toBeArray();
    foreach ($results as $album) {
        expect($album::class)->toBe("Ytmusicapi\\AlbumInfo");
        expect($album->title)->toBeString();
        expect($album->browseId)->toBeString();
        // Everything else is optional so we don't check it.
    }

    // Get all uploaded tracks from the first album
    $browseId = $results[0]->browseId;
    $album = $yt->get_library_upload_album($browseId);
    expect($album::class)->toBe("Ytmusicapi\\Album");
    foreach ($album->tracks as $track) {
        expect($track::class)->toBe("Ytmusicapi\\UploadTrack");
        expect($track->entityId)->toBeString();
        expect($track->videoId)->toBeString()->toHaveLength(11);
        expect($track->title)->toBeString();
        expect($track->duration)->toBeString();
        expect($track->duration_seconds)->toBeInt();
        expect($track->likeStatus)->toBeString();
    }
});

test('get_libraray_upload_artists', function () {
    $yt = new YTMusic('browser.json');

    $results = $yt->get_library_upload_artists();
    expect($results)->toBeArray();
    foreach ($results as $artist) {
        expect($artist::class)->toBe("Ytmusicapi\\ArtistInfo");
        expect($artist->artist)->toBeString();
        expect($artist->browseId)->toBeString();
        // Everything else is optional so we don't check it.
    }

    // Get all uploaded tracks from the first artist
    $browseId = $results[0]->browseId;
    $results = $yt->get_library_upload_artist($browseId);
    expect($results)->toBeArray();
    foreach ($results as $track) {
        expect($track::class)->toBe("Ytmusicapi\\UploadTrack");
        expect($track->entityId)->toBeString();
        expect($track->videoId)->toBeString()->toHaveLength(11);
        expect($track->title)->toBeString();
        expect($track->duration)->toBeString();
        expect($track->duration_seconds)->toBeInt();
        expect($track->likeStatus)->toBeString();
    }
});
