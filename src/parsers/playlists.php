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

    $metadata = parse_playlist_header_meta($header);
    foreach ($metadata as $key => $value) {
        $playlist->{$key} = $value;
    }

    if (empty($playlist->thumbnails)) {
        $playlist->thumbnails = nav($header, THUMBNAIL_CROPPED, true);
    }

    $playlist->description = nav($header, join("description", DESCRIPTION_SHELF, DESCRIPTION), true);
    $playlist->year = nav($header, SUBTITLE2);

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
 * @param object $header
 */
function parse_playlist_header_meta($header): array {
    $playlist_meta = [
        "views" => null,
        "duration" => null,
        "trackCount" => null,
        "title" => implode("", array_map(function($run) {
            return $run->text;
        }, $header->title->runs ?? [])),
        "thumbnails" => nav($header, THUMBNAILS),
    ];

    if (!empty($header->facepile)) {
        $avatar_renderer = nav($header, "facepile.avatarStackViewModel.rendererContext", true);
        $avatar_command = nav(
            $avatar_renderer,
            "commandContext.onTap.innertubeCommand",
            true,
        );

        $tag = nav($avatar_command, "showEngagementPanelEndpoint.identifier.tag", true);

        $playlist_meta["collaborators"] = null;
        $playlist_meta["author"] = null;

        if ($tag) {
            $avatars = nav($header, "facepile.avatarStackViewModel.avatars");
            $list = [];
            foreach ($avatars as $avatar) {
                $list[] = $avatar->avatarViewModel->image->sources[0];
            }

            $playlist_meta["collaborators"] = (object)[
                "text" => nav($avatar_renderer, "accessibilityContext.label"),
                "avatars" => $list,
            ];
        } else {
            $playlist_meta["author"] = (object)[
                "name" => nav($header, "facepile.avatarStackViewModel.text.content"),
                "id" => nav(
                    $avatar_command,
                    "browseEndpoint.browseId",
                    true,
                ),
            ];
        }
    }

    if (isset($header->secondSubtitle->runs)) {
        $second_subtitle_runs = $header->secondSubtitle->runs;
        $has_views = (count($second_subtitle_runs) > 3) ? 2 : 0;
        $playlist_meta["views"] = !$has_views ? null : (int)($second_subtitle_runs[0]->text);
        $has_duration = (count($second_subtitle_runs) > 1) ? 2 : 0;
        $playlist_meta["duration"] = !$has_duration ? null : $second_subtitle_runs[$has_views + $has_duration]->text;

        $song_count_text = $second_subtitle_runs[$has_views + 0]->text;
        preg_match_all('/\d+/', $song_count_text, $matches);
        $song_count_search = $matches[0];

        // extract the digits from the text, return null if no match
        $playlist_meta["trackCount"] = !empty($song_count_search) ? intval(implode("", $song_count_search)) : null;
    }

    return $playlist_meta;
}

/**
 * @param object $response;
 * @param int $limit
 * @param callable $request_func
 * @return array
 */
function parse_audio_playlist($response, ?int $limit, callable $request_func): array {
    $playlist = [
        "owned" => false,
        "privacy" => "PUBLIC",
        "description" => null,
        "views" => null,
        "duration" => null,
        "tracks" => [],
        "thumbnails" => [],
        "related" => [],
    ];

    $section_list = nav($response, join(TWO_COLUMN_RENDERER, ["secondaryContents"], SECTION));
    $content_data = nav($section_list, join(CONTENT, ["musicPlaylistShelfRenderer"]));

    $playlist["id"] = nav($content_data, "targetId");
    $playlist["tracks"] = [];

    if (isset($content_data->contents)) {
        $playlist["tracks"] = parse_playlist_items($content_data->contents);

        $parse_func = function($contents) {
            return parse_playlist_items($contents);
        };

        $continuation_tracks = get_continuations_2025($content_data, $limit, $request_func, $parse_func);
        $playlist["tracks"] = array_merge($playlist["tracks"], $continuation_tracks);
    }

    $playlist["trackCount"] = count($playlist["tracks"]);

    $playlist["title"] = $playlist["tracks"][0]->album->name;
    $playlist["duration_seconds"] = sum_total_duration($playlist);

    return $playlist;
}

/**
 * Known differences from Python verions:
 *   - Looks in additional place for video type
 *   - Returns play count for album playlists
 *
 * @param mixed $results
 * @param bool $is_album
 * @param bool $is_collaborative
 * @return Track[]|AlbumTrack[]
 */
function parse_playlist_items(
    $results,
    $is_album = false,
    $is_collaborative = false,
) {
    $songs = [];

    foreach ($results as $result) {
        if (!isset($result->musicResponsiveListItemRenderer)) {
            continue;
        }
        $data = $result->musicResponsiveListItemRenderer;

        $song = parse_playlist_item($data, $is_album, $is_collaborative);
        if ($song) {
            $songs[] = $song;
        }

    }

    return $songs;
}

/**
 * @param object $data
 * @param bool $is_album
 * @param bool $is_collaborative
 */
function parse_playlist_item($data, $is_album = false, $is_collaborative = false)
{
    $videoId = null;
    $setVideoId = null;
    $creditsBrowseId = null;
    $like = null;

    // if the item has a menu, find its setVideoId
    if (isset($data->menu)) {
        foreach ($data->menu->menuRenderer->items as $item) {
            if (isset($item->menuServiceItemRenderer)) {
                $menu_service = $item->menuServiceItemRenderer->serviceEndpoint;
                if (isset($menu_service->playlistEditEndpoint)) {
                    $setVideoId = $menu_service->playlistEditEndpoint->actions[0]->setVideoId ?? null;
                    $videoId = $menu_service->playlistEditEndpoint->actions[0]->removedVideoId ?? null;
                }
            } else if (isset($item->menuNavigationItemRenderer)) {
                $maybe_credits_browse_id = nav($item, join(MNIR, NAVIGATION_BROWSE_ID), true);
                if ($maybe_credits_browse_id && str_starts_with($maybe_credits_browse_id, "MPTC")) {
                    $creditsBrowseId = $maybe_credits_browse_id;
                }
            }
        }
    }

    $song_menu_data = array_merge(["inLibrary" => null, "pinnedToListenAgain" => null], parse_song_menu_data($data));

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
    $duration_index = null;
    // collaborative playlists have duration in flexColumns (between artist and album)
    $album_index = $is_collaborative ? 3 : ($use_preset_columns ? 2 : null);
    $user_channel_indexes = [];
    $unrecognized_index = null;

    $flex_columns = $data->flexColumns;
    foreach ($flex_columns as $index => $flexColumn) {
        $flex_column_item = get_flex_column_item($data, $index);
        $navigation_endpoint = nav($flex_column_item, join(TEXT_RUN, "navigationEndpoint"), true);

        if (!$navigation_endpoint) {
            $run = nav($flex_column_item, TEXT_RUN, true);
            if ($run && isset($run->text)) {
                $parsed = parse_song_run($run);
                if ($parsed["type"] === "duration") {
                    $duration_index = $index;
                } else {
                    if (!$unrecognized_index) {
                        $unrecognized_index = $index;
                    }
                }
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
            } elseif ($page_type === "MUSIC_PAGE_TYPE_ALBUM" || $page_type === "MUSIC_PAGE_TYPE_AUDIOBOOK") {
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

    $artists = $artist_index !== null ? parse_song_artists($data, $artist_index) : null;

    $album = $album_index !== null ? parse_song_album($data, $album_index) : null;

    $views = $is_album ? get_item_text($data, 2) : null;

    $duration = $duration_index ? get_item_text($data, $duration_index) : null;

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

    // This is only for logged in users. Logged out users can still see vote count from playlistItemData
    $voting_status = nav($data, ENGAGEMENT_BAR, true);
    $community_vote_status = null;
    if ($voting_status) {
        $community_vote_status = (object)[
            "netVoteValue" => $voting_status->votes,
            "status" => $voting_status->status,
        ];
    }

    $track = $is_album ? new AlbumTrack() : new Track();
    $track->videoId = $videoId;
    $track->title = $title;
    $track->artists = $artists;
    $track->album = $album;
    $track->likeStatus = $like;
    $track->thumbnails = $thumbnails;
    $track->isAvailable = $isAvailable;
    $track->isExplicit = $isExplicit;
    $track->videoType = $videoType;
    $track->duration = 0;
    $track->duration_seconds = "";
    $track->setVideoId = '';
    $track->feedbackTokens = [];
    $track->views = $views;
    $track->communityVoteStatus = $community_vote_status;

    foreach ($song_menu_data as $k => $v) {
        $track->$k = $v;
    }

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

    if ($creditsBrowseId) {
        $track->creditsBrowseId = $creditsBrowseId;
    }

    return $track;
}

/**
 * @param string $playlistId;
 */
function validate_playlist_id($playlistId)
{
    if (!str_starts_with($playlistId, "VL")) {
        return $playlistId;
    }

    return substr($playlistId, 2);
}

