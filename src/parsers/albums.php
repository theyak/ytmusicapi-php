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

function parse_album_header_2024($response) {
    $header = nav($response, join(TWO_COLUMN_RENDERER, TAB_CONTENT, SECTION_LIST_ITEM, RESPONSIVE_HEADER));
    $album = new Album();
    $album->title = nav($header, TITLE_TEXT);
    $album->type = nav($header, SUBTITLE);
    $album->thumbnails = nav($header, THUMBNAILS);
    $album->isExplicit = !!nav($header, SUBTITLE_BADGE_LABEL, true);

    $album->description = nav($header, join("description", DESCRIPTION_SHELF, DESCRIPTION), true);

    $album_info = parse_song_runs(array_slice($header->subtitle->runs, 2));
    $album_info->artists = [parse_base_header($header)->author];
    object_merge($album, $album_info);

    if (count($header->secondSubtitle->runs) > 1) {
        $album->trackCount = (int)($header->secondSubtitle->runs[0]->text);
        $album->duration = $header->secondSubtitle->runs[2]->text;
    } else {
        $album->duration = $header->secondSubtitle->runs[0]->text;
    }

    // add to library/uploaded
    $buttons = $header->buttons;
    $album->audioPlaylistId = nav(
        $buttons, join("1.musicPlayButtonRenderer.playNavigationEndpoint", WATCH_PLAYLIST_ID), true
    );

    $service = nav($buttons, join("0.toggleButtonRenderer.defaultServiceEndpoint"), true);
    if ($service) {
        $album->likeStatus = parse_like_status($service);
    }

    return $album;
}
