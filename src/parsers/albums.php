<?php

namespace Ytmusicapi;

/**
 * Parse Album inforomation from get_album() or get_library_upload_album()
 *
 * @param object $response
 * @return Album
 */
function parse_album_header($response)
{
    $header = nav($response, HEADER_DETAIL);
    $album = new Album();
    $album->title = nav($header, TITLE_TEXT);
    $album->type = nav($header, SUBTITLE);
    $album->thumbnails = nav($header, THUMBNAIL_CROPPED);
    $album->isExplicit = !!nav($header, SUBTITLE_BADGE_LABEL, true);

    if (isset($header->description)) {
        $album->description = $header->description->runs[0]->text;
    }

    $album_info = parse_song_runs(array_slice($header->subtitle->runs, 2));
    object_merge($album, $album_info);

    if (count($header->secondSubtitle->runs) > 1) {
        $album->trackCount = (int)($header->secondSubtitle->runs[0]->text);
        $album->duration = $header->secondSubtitle->runs[2]->text;
    } else {
        $album->duration = $header->secondSubtitle->runs[0]->text;
    }

    // add to library/uploaded
    $menu = nav($header, MENU);
    $toplevel = $menu->topLevelButtons;
    $album->audioPlaylistId = nav($toplevel, join("0.buttonRenderer", NAVIGATION_WATCH_PLAYLIST_ID), true);
    if (!$album->audioPlaylistId) {
        $album->audioPlaylistId = nav($toplevel, join("0.buttonRenderer", NAVIGATION_PLAYLIST_ID), true);
    }

    $service = nav($toplevel, "1.buttonRenderer.defaultServiceEndpoint", true);
    if ($service) {
        $album->likeStatus = parse_like_status($service);
    }

    return $album;
}
