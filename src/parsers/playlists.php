<?php

namespace Ytmusicapi;

function parse_playlist_header($response)
{
    $playlist = new \stdClass();

    $editable_header = nav($response, join(HEADER, EDITABLE_PLAYLIST_DETAIL_HEADER), true);
    $playlist->owned = !!$editable_header;
    $playlist->privacy = "PUBLIC";
    if ($playlist->owned) {
        $header = nav($response, HEADER_DETAIL);
        $playlist->privacy = $editable_header->editHeader->musicPlaylistEditHeaderRenderer->privacy;
    } else {
        $header = nav($response, HEADER_DETAIL, true);
        if (empty($header)) {
            $header = nav($response, join(TWO_COLUMN_RENDERER, TAB_CONTENT, SECTION_LIST_ITEM, RESPONSIVE_HEADER), true);
        }
    }

    $playlist->title = nav($header, TITLE_TEXT);
    $playlist->thumbnails = nav($header, THUMBNAIL_CROPPED, true);
    if (empty($playlist->thumbnails)) {
        $playlist->thumbnails = nav($header, THUMBNAILS, true);
    }
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

        $song_count_text = $second_subtitle_runs[$has_views + 0]->text;
        $matches = [];
        if (preg_match("/\d+/", $song_count_text, $matches)) {
            $song_count_search = $matches[0];
            $song_count = (int)$song_count_search;
        } else {
            $song_count = 0;
        }

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

    $isAvailable = true;
    if (isset($data->musicItemRendererDisplayPolicy)) {
        $isAvailable = $data->musicItemRendererDisplayPolicy !== 'MUSIC_ITEM_RENDERER_DISPLAY_POLICY_GREY_OUT';
    }

    // For unavailable items and for album track lists indexes are preset,
    // because meaning of the flex column cannot be reliably found using navigationEndpoint
    $use_preset_columns = !$isAvailable || $is_album;

    $title_index = $use_preset_columns ? 0 : null;
    $artist_index = $use_preset_columns ? 1 : null;
    $album_index = $use_preset_columns ? 2 : null;
    $user_channel_indexes = [];
    $unrecognized_index = null;

    $flex_columns = $data->flexColumns;
    foreach ($data->flexColumns as $index => $flexColumn) {
        $flex_column_item = get_flex_column_item($data, $index);
        $navigation_endpoint = nav($flex_column_item, join(TEXT_RUN, "navigationEndpoint"), true);

        if (!$navigation_endpoint) {
            if (nav($flex_column_item, TEXT_RUN_TEXT, true) !== null) {
                $unrecognized_index = $unrecognized_index ?? $index;
            }
            continue;
        }

        if (!empty($navigation_endpoint->watchEndpoint)) {
            $title_index = $index;
        } elseif (!empty($navigation_endpoint->browseEndpoint)) {
            $page_type = nav(
                $navigation_endpoint,
                [
                    "browseEndpoint",
                    "browseEndpointContextSupportedConfigs",
                    "browseEndpointContextMusicConfig",
                    "pageType",
                ]
            );

            // MUSIC_PAGE_TYPE_ARTIST for regular songs, MUSIC_PAGE_TYPE_UNKNOWN for uploads
            if ($page_type === "MUSIC_PAGE_TYPE_ARTIST" || $page_type === "MUSIC_PAGE_TYPE_UNKNOWN") {
                $artist_index = $index;
            } elseif ($page_type === "MUSIC_PAGE_TYPE_ALBUM") {
                $album_index = $index;
            } elseif ($page_type === "MUSIC_PAGE_TYPE_USER_CHANNEL") {
                $user_channel_indexes[] = $index;
            } elseif ($page_type === "MUSIC_PAGE_TYPE_NON_MUSIC_AUDIO_TRACK_PAGE") {
                $title_index = $index;
            }
        }
    }

    // Extra check for rare songs, where artist is non-clickable and does not have navigationEndpoint
    if ($artist_index === null && $unrecognized_index !== null) {
        $artist_index = $unrecognized_index;
    }

    // Extra check for non-song videos, last channel is treated as artist
    if ($artist_index === null && $user_channel_indexes) {
        $artist_index = end($user_channel_indexes);
    }

    $title = $title_index !== null ? get_item_text($data, $title_index) : null;

    // I have lots of deleted songs in my playlists but
    // they never appear in my playlist, so I'm not sure
    // if this ever gets triggered. I'm also not a big
    // fan of the idea of returning here as $videoId and
    // associated video data may still be available.
    if ($title === 'Song deleted') {
        return null;
    }

    $flex_column_count = count($data->flexColumns);

    $artists = $artist_index !== null ? parse_song_artists($data, $artist_index) : null;

    $album = $album_index !== null ? parse_song_album($data, $album_index) : null;

    $views = $is_album ? get_item_text($data, 2) : null;

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
