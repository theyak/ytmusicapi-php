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

/**
 * Note: Schema for artist has changed as of 1.11.0
 */
function parse_album_header_2024($response) {
    $header = nav($response, join(TWO_COLUMN_RENDERER, TAB_CONTENT, SECTION_LIST_ITEM, RESPONSIVE_HEADER));
    $album = new Album();
    $album->title = nav($header, TITLE_TEXT);
    $album->type = nav($header, SUBTITLE);
    $album->thumbnails = nav($header, THUMBNAILS);
    $album->isExplicit = !!nav($header, SUBTITLE_BADGE_LABEL, true);

    $album->description = nav($header, join("description", DESCRIPTION_SHELF, DESCRIPTION), true);

    $album_info = parse_song_runs(array_slice($header->subtitle->runs, 2));
    $album_info['artists'] = [parse_base_header($header)->author ?? null];
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
        find_object_by_key($buttons, "musicPlayButtonRenderer"),
        join("musicPlayButtonRenderer", "playNavigationEndpoint", WATCH_PID),
        true
    );

    # remove this once A/B testing is finished and it is no longer covered
    if (empty($album->audioPlaylistId)) {
        $album->audioPlaylistId = nav(
            find_object_by_key($buttons, "musicPlayButtonRenderer"),
            join("musicPlayButtonRenderer", "playNavigationEndpoint", WATCH_PLAYLIST_ID),
            true
        );
    }

    $service = nav(
        find_object_by_key($buttons, "toggleButtonRenderer"),
        join("toggleButtonRenderer", "defaultServiceEndpoint"),
        true
    );
    $album->likeStatus = "INDIFFERENT";
    if ($service) {
        $album->likeStatus = parse_like_status($service);
    }

    return $album;
}

/**
 * the content of the data changes based on whether the user is authenticated or not
 */
function parse_album_playlistid_if_exists($data): string | null
{
    if ($data) {
        return nav($data, WATCH_PID, true) ?? nav($data, WATCH_PLAYLIST_ID, true);
    }

    return null;
}
