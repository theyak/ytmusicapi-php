<?php

namespace Ytmusicapi;

/**
 * Parses data from get_home() and get_song_related() routines
 *
 * @param array $rows
 * @return Shelf[]
 */
function parse_mixed_content($rows)
{
    $items = [];

    foreach ($rows as $row) {
        $keys = object_keys($row);
        if (in_array(DESCRIPTION_SHELF, object_keys($row))) {
            $results = nav($row, DESCRIPTION_SHELF);
            $title = nav($results, 'header.' . RUN_TEXT);
            $contents = nav($results, DESCRIPTION);
        } else {
            $results = $row->{$keys[0]};
            if (!isset($results->contents)) {
                continue;
            }

            $title = nav($results, join(CAROUSEL_TITLE, 'text'));
            $contents = [];

            foreach ($results->contents as $result) {
                $data = nav($result, MTRIR, true);
                $content = null;
                if ($data) {
                    $page_type = nav($data, implode(".", [TITLE, NAVIGATION_BROWSE, PAGE_TYPE]), true);
                    if ($page_type === null) { // song or watch_playlist
                        if (nav($data, NAVIGATION_WATCH_PLAYLIST_ID, true) !== null) {
                            $content = parse_watch_playlist($data);
                        } else {
                            $content = parse_song($data);
                        }
                    } elseif ($page_type === "MUSIC_PAGE_TYPE_ALBUM") {
                        $content = parse_album($data);
                    } elseif ($page_type === "MUSIC_PAGE_TYPE_ARTIST") {
                        $content = parse_related_artist($data);
                    } elseif ($page_type === "MUSIC_PAGE_TYPE_PLAYLIST") {
                        $content = parse_playlist($data);
                    }
                } else {
                    $data = nav($result, MRLIR, true);
                    if (!$data) {
                        continue;
                    }
                    $content = parse_song_flat($data);
                }

                $contents[] = $content;
            }
        }

        $item = new Shelf();
        $item->title = $title;
        $item->contents = $contents;
        $items[] = $item;
    }

    return $items;
}

/**
 * @param object $results
 * @param string|callable $parse_func
 * @param string $key
 * @return array
 */
function parse_content_list($results, $parse_func, $key = null)
{
    $key = $key === null ? MTRIR : $key;
    $contents = [];
    foreach ($results as $result) {
        $contents[] = $parse_func($result->$key);
    }

    return $contents;
}

/**
 * Get information about an album from the get_home() routine.
 *
 * @param object $result
 * @return object
 */
function parse_album($result)
{
    return (object)[
        'resultType' => 'album',
        'title' => nav($result, TITLE_TEXT),
        'year' => nav($result, SUBTITLE2, true),
        'browseId' => nav($result, join(TITLE, NAVIGATION_BROWSE_ID)),
        'thumbnails' => nav($result, THUMBNAIL_RENDERER),
        'isExplicit' => nav($result, SUBTITLE_BADGE_LABEL, true) !== null,
    ];
}

/**
 * Get information about a single. I don't think this is used.
 *
 * @param object $result
 * @return object
 */
function parse_single($result)
{
    return (object)[
        'resultType' => 'single',
        'title' => nav($result, TITLE_TEXT),
        'year' => nav($result, SUBTITLE, true),
        'browseId' => nav($result, join(TITLE, NAVIGATION_BROWSE_ID)),
        'thumbnails' => nav($result, THUMBNAIL_RENDERER),
    ];
}

/**
 * Get information about a song from the get_home() routine.
 *
 * @param object $result
 * @return object
 */
function parse_song($result)
{
    $song = (object)[
        'resultType' => 'song',
        'title' => nav($result, TITLE_TEXT),
        'videoId' => nav($result, NAVIGATION_VIDEO_ID),
        'playlistId' => nav($result, NAVIGATION_PLAYLIST_ID, true),
        'thumbnails' => nav($result, THUMBNAIL_RENDERER)
    ];

    $song = object_merge($song, parse_song_runs(nav($result, SUBTITLE_RUNS)));
    return $song;
}

/**
 * Get information about a song from the parse_chart_song() routine
 *
 * @param object $data
 * @return object
 */
function parse_song_flat($data)
{
    $columns = [];
    for ($i = 0; $i < count($data->flexColumns); $i++) {
        $columns[] = get_flex_column_item($data, $i);
    }
    $song = [
        'resultType' => 'song',
        'title' => nav($columns[0], TEXT_RUN_TEXT),
        'videoId' => nav($columns[0], join(TEXT_RUN, NAVIGATION_VIDEO_ID), true),
        'artists' => parse_song_artists($data, 1),
        'thumbnails' => nav($data, THUMBNAILS),
        'isExplicit' => nav($data, BADGE_LABEL, true) !== null
    ];
    if (count($columns) > 2 && $columns[2] !== null) {
        $navigation = nav($columns[2], TEXT_RUN);
        if (isset($navigation->navigationEndpoint)) {
            $song['album'] = (object)[
                'name' => nav($columns[2], TEXT_RUN_TEXT),
                'id' => nav($columns[2], join(TEXT_RUN, NAVIGATION_BROWSE_ID))
            ];
        } else {
            $song['views'] = explode(' ', nav($columns[1], "text.runs.-1.text"))[0];
        }
    } else {
        $runs = nav($columns[1], TEXT_RUNS);
        $views = (end($runs))->text;
        $song['views'] = explode(' ', $views)[0];
    }

    return (object)$song;
}

/**
 * Try to parse data from a list of videos.
 * Note that data in YouTube Music is not consistent, especially for
 * non US videos, so this function may not give the desired data for all cases.
 *
 * Known differences from Python version:
 *  - Does an additional check for playlistId if not found in regular location
 *
 * @param object $result
 * @return VideoInfo
 */
function parse_video($result)
{
    $runs = nav($result, SUBTITLE_RUNS);
    $artists_len = get_dot_separator_index($runs);
    $videoId = nav($result, NAVIGATION_VIDEO_ID, true);
    if (!$videoId) {
        foreach (nav($result, MENU_ITEMS) as $entry) {
            $id = nav($entry, join(MENU_SERVICE, QUEUE_VIDEO_ID), true);
            if ($id) {
                $videoId = $id;
                break;
            }
        }
    }

    $last_run = end($runs);

    $data = (object)[
        'title' => nav($result, TITLE_TEXT),
        'videoId' => $videoId,
        'artists' => parse_song_artists_runs(array_slice($runs, 0, $artists_len)),
        'playlistId' => nav($result, NAVIGATION_PLAYLIST_ID, true),
        'thumbnails' => nav($result, THUMBNAIL_RENDERER, true),
        'views' => explode(' ', $last_run->text)[0]
    ];

    // Difference from Python - look in another place for playlistId.
    if (!$data->playlistId) {
        $nav = "thumbnailOverlay.musicItemThumbnailOverlayRenderer.";
        $nav .= "content.musicPlayButtonRenderer.playNavigationEndpoint.";
        $nav .= "watchEndpoint.playlistId";
        $data->playlistId = nav($result, $nav, true);
    }

    return $data;
}

/**
 * Parse playlist data for get_home() or search results
 *
 * Known differences from Python version:
 *  - Searches view count with decimals and with K, M, and B suffixes
 *  - Allows 5 items in runs for some responses
 *
 * @param object $data
 * @return PlaylistInfo[]
 */
function parse_playlist($data)
{
    $playlist = new PlaylistInfo();
    $playlist->title = nav($data, TITLE_TEXT);
    $playlist->playlistId = substr(nav($data, join(TITLE, NAVIGATION_BROWSE_ID)), 2);
    $playlist->thumbnails = nav($data, THUMBNAIL_RENDERER);
    $playlist->description = "";
    $playlist->count = 0;
    $playlist->author = [];

    $subtitle = $data->subtitle;
    if (isset($subtitle->runs)) {
        $playlist->description = implode('', array_column($subtitle->runs, 'text'));

        if (count($subtitle->runs) === 5 && $subtitle->runs[0]->text === "Playlist") {
            $runs = array_slice($subtitle->runs, 2);
        } else {
            $runs = $subtitle->runs;
        }

        if (count($runs) === 3 && preg_match('/^\d{1,3}(,\d{3})*(\.\d+)?([KMB])? /', $runs[2]->text)) {
            $playlist->count = (int)explode(' ', $runs[2]->text)[0];
            $playlist->author = parse_song_artists_runs([$runs[0]]);
        }
    }

    return $playlist;
}

/**
 * Parse artist data for get_home() routine
 *
 * @param object $data
 * @return object
 */
function parse_related_artist($data)
{
    $subscribers = nav($data, SUBTITLE, true);
    if ($subscribers) {
        $subscribers = explode(' ', $subscribers)[0];
    }
    return (object)[
        'resultType' => 'artist',
        'title' => nav($data, TITLE_TEXT),
        'browseId' => nav($data, join(TITLE, NAVIGATION_BROWSE_ID)),
        'subscribers' => $subscribers,
        'thumbnails' => nav($data, THUMBNAIL_RENDERER)
    ];
}

function parse_watch_playlist($data)
{
    return (object)[
        'resultType' => 'watch_playlist',
        'title' => nav($data, TITLE_TEXT),
        'playlistId' => nav($data, NAVIGATION_WATCH_PLAYLIST_ID),
        'thumbnails' => nav($data, THUMBNAIL_RENDERER),
    ];
}
