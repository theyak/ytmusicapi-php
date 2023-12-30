<?php

namespace Ytmusicapi;

/**
 * Known differences from Python verions:
 *   - Looks in additional place for video type
 *
 * @param mixed $results
 * @param mixed $menu_entries
 * @return PlaylistTrack[]
 */
function parse_playlist_items($results, $menu_entries = null)
{
    $tracks = [];

    foreach ($results as $result) {
        if (!isset($result->musicResponsiveListItemRenderer)) {
            continue;
        }
        $data = $result->musicResponsiveListItemRenderer;

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
        // if this ever gets triggered.
        if ($title === 'Song deleted' && !$videoId) {
            continue;
        }

        $artists = parse_song_artists($data, 1);
        $album = parse_song_album($data, 2);

        $duration = null;
        if (isset($data->fixedColumns)) {
            $text = get_fixed_column_item($data, 0)->text;
            if (isset($text->simpleText)) {
                $duration = $text->simpleText;
            } else {
                $duration = $text->runs[0]->text;
            }
        }

        $thumbnails = null;
        if (isset($data->thumbnail)) {
            $thumbnails = nav($data, THUMBNAILS);
        }

        $isAvailable = true;
        if (isset($data->musicItemRendererDisplayPolicy)) {
            $isAvailable = $data->musicItemRendererDisplayPolicy !== 'MUSIC_ITEM_RENDERER_DISPLAY_POLICY_GREY_OUT';
        }

        $isExplicit = nav($data, BADGE_LABEL, true) !== null;

        $videoType = nav($data, join(MENU_ITEMS, '0.menuNavigationItemRenderer.navigationEndpoint', NAVIGATION_VIDEO_TYPE), true);
        if (!$videoType) {
            $videoType = nav($data, join(PLAY_BUTTON, "playNavigationEndpoint", NAVIGATION_VIDEO_TYPE), true);
        }

        $track = new Track();
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

        $tracks[] = $track;
    }

    return $tracks;
}

function validate_playlist_id($playlistId)
{
    if (!str_starts_with($playlistId, "VL")) {
        return $playlistId;
    }

    return substr($playlistId, 2);
}
