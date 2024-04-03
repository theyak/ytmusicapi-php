<?php

namespace Ytmusicapi;

function parse_playlist_header($response)
{
    $playlist = new \stdClass();
    $own_playlist = !empty($response->header->musicEditablePlaylistDetailHeaderRenderer);

    if ($own_playlist) {
        $header = $response->header->musicEditablePlaylistDetailHeaderRenderer;
        $playlist->privacy = $header->editHeader->musicPlaylistEditHeaderRenderer->privacy;
        $header = $header->header->musicDetailHeaderRenderer;
    } else {
        $header = $response->header->musicDetailHeaderRenderer;
        $playlist->privacy = "PUBLIC";
    }
    $playlist->owned = $own_playlist;

    $playlist->title = nav($header, TITLE_TEXT);
    $playlist->thumbnails = nav($header, THUMBNAIL_CROPPED);
    $playlist->description = nav($header, DESCRIPTION, true);
    $run_count = count(nav($header, SUBTITLE_RUNS));
    if ($run_count > 1) {
        $playlist->author = (object)[
            "name" => nav($header, SUBTITLE2),
            "id" => nav($header, join(SUBTITLE_RUNS, 2, NAVIGATION_BROWSE_ID), true),
        ];
        if ($run_count === 5) {
            $playlist->year = nav($header, SUBTITLE3);
        }
    }

    $playlist->views = null;
    $playlist->duration = null;
    $playlist->trackCount = null;
    if (isset($header->secondSubtitle->runs)) {
        $second_subtitle_runs = $header->secondSubtitle->runs;
        $has_views = (count($second_subtitle_runs) > 3) * 2;
        $playlist->views = $has_views ? (int)($second_subtitle_runs[0]->text) : null;
        $has_duration = (count($second_subtitle_runs) > 1) * 2;
        $playlist->duration = null;
        $playlist->duration = $has_duration ? $second_subtitle_runs[$has_views + $has_duration]->text : null;
        $song_count = explode(" ", $second_subtitle_runs[$has_views]->text);
        $song_count = count($song_count) > 1 ? (int)($song_count[0]) : 0;

        // Track count is approximate. If tracks have been removed, they won't load,
        // but will still be included in this count, because YouTube is funny that way.
        $playlist->trackCount = $song_count;
    }

    return $playlist;
}

/**
 * Known differences from Python verions:
 *   - Looks in additional place for video type
 *   - Returns play count for album playlists
 *
 * @param mixed $results
 * @param mixed $menu_entries
 * @return Track[]|AlbumTrack[]
 */
function parse_playlist_items($results, $menu_entries = null, $is_album = false)
{
    $songs = [];

    foreach ($results as $result) {
        if (!isset($result->musicResponsiveListItemRenderer)) {
            continue;
        }
        $data = $result->musicResponsiveListItemRenderer;

        $song = parse_playlist_item($data, $menu_entries, $is_album);
        if ($song) {
            $songs[] = $song;
        }

    }

    return $songs;
}

function parse_playlist_item($data, $menu_entries = null, $is_album = false)
{
    $videoId = null;
    $setVideoId = null;
    $like = null;
    $feedback_tokens = null;
    $library_status = false;

    // if the item has a menu, find its setVideoId
    if (isset($data->menu)) {
        foreach ($data->menu->menuRenderer->items as $item) {
            if (isset($item->menuServiceItemRenderer)) {
                $menu_service = $item->menuServiceItemRenderer->serviceEndpoint;
                if (isset($menu_service->playlistEditEndpoint)) {
                    $setVideoId = $menu_service->playlistEditEndpoint->actions[0]->setVideoId ?? null;
                    $videoId = $menu_service->playlistEditEndpoint->actions[0]->removedVideoId ?? null;
                }
            }

            if (isset($item->toggleMenuServiceItemRenderer)) {
                $feedback_tokens = parse_song_menu_tokens($item);
                $library_status = parse_song_library_status($item);
            }
        }
    }

    // if item is not playable, the videoId was retrieved above
    if (nav($data, PLAY_BUTTON, true)) {
        if (nav($data, join(PLAY_BUTTON, 'playNavigationEndpoint'), true)) {
            $videoId = nav($data, join(PLAY_BUTTON, 'playNavigationEndpoint.watchEndpoint.videoId'), true);

            if (isset($data->menu)) {
                $like = nav($data, MENU_LIKE_STATUS, true);
            }
        }
    }

    $title = get_item_text($data, 0);

    // I have lots of deleted songs in my playlists but
    // they never appear in my playlist, so I'm not sure
    // if this ever gets triggered. I'm also not a big
    // fan of the idea of returning here as $videoId and
    // associated video data may still be available.
    if ($title === 'Song deleted') {
        return null;
    }

    $flex_column_count = count($data->flexColumns);

    $artists = parse_song_artists($data, 1);

    // Last item is album? - Not true for podcasts
    $album = !$is_album ? parse_song_album($data, $flex_column_count - 1) : null;

    $views = null;
    if ($flex_column_count === 4 || $is_album) {
        $views = get_item_text($data, 2);
    }

    $duration = null;
    if (isset($data->fixedColumns)) {
        $text = get_fixed_column_item($data, 0)->text;
        if (isset($text->simpleText)) {
            $duration = $text->simpleText;
        } else {
            $duration = $text->runs[0]->text;
        }
    }

    $thumbnails = nav($data, THUMBNAILS, true);

    $isAvailable = true;
    if (isset($data->musicItemRendererDisplayPolicy)) {
        $isAvailable = $data->musicItemRendererDisplayPolicy !== 'MUSIC_ITEM_RENDERER_DISPLAY_POLICY_GREY_OUT';
    }

    $isExplicit = nav($data, BADGE_LABEL, true) !== null;

    $videoType = nav($data, join(MENU_ITEMS, '0', MNIR, 'navigationEndpoint', NAVIGATION_VIDEO_TYPE), true);
    if (!$videoType) {
        // [PHP Only] This is a fallback for when the videoType is not found in the first place
        $videoType = nav($data, join(PLAY_BUTTON, "playNavigationEndpoint", NAVIGATION_VIDEO_TYPE), true);
    }

    $track = $is_album ? new AlbumTrack() : new Track();
    $track->videoId = $videoId;
    $track->title = $title;
    $track->artists = $artists;
    $track->album = $album;
    $track->likeStatus = $like;
    $track->inLibrary = $library_status;
    $track->thumbnails = $thumbnails;
    $track->isAvailable = $isAvailable;
    $track->isExplicit = $isExplicit;
    $track->videoType = $videoType;
    $track->duration = 0;
    $track->duration_seconds = "";
    $track->setVideoId = '';
    $track->feedbackTokens = [];
    $track->views = $views;

    if ($is_album) {
        $track->trackNumber = null;
        $track_idx_found = nav($data, ["index", "runs", 0, "text"], true);
        $track->trackNumber = $track_idx_found ? (int)$track_idx_found : null;
        $track->playCount = get_item_text($data, 2);
    }

    if ($duration) {
        $track->duration = $duration;
        $track->duration_seconds = parse_duration($duration);
    }

    if ($setVideoId) {
        $track->setVideoId = $setVideoId;
    }

    if ($feedback_tokens) {
        $track->feedbackTokens = $feedback_tokens;
    }

    if ($menu_entries) {
        // Generally feedbackToken and used for history items
        foreach ($menu_entries as $menu_entry) {
            if (is_string($menu_entry)) {
                $menu_entry = explode('.', $menu_entry);
            }
            $pos = end($menu_entry);
            $track->{$pos} = nav($data, join(MENU_ITEMS, join($menu_entry)));
        }
    }

    return $track;
}

function validate_playlist_id($playlistId)
{
    if (!str_starts_with($playlistId, "VL")) {
        return $playlistId;
    }

    return substr($playlistId, 2);
}
