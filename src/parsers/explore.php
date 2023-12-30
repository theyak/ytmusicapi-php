<?php

namespace Ytmusicapi;

define(
    "Ytmusicapi\TRENDS",
    [
        "ARROW_DROP_UP" => "up",
        "ARROW_DROP_DOWN" => "down",
        "ARROW_CHART_NEUTRAL" => "neutral"
    ]
);

/**
 * @return array
 */
function parse_chart_song($data)
{
    $parsed = parse_song_flat($data);
    $parsed = (object)array_merge((array)$parsed, (array)parse_ranking($data));
    return $parsed;
}

/**
 * @return array
 */
function parse_chart_artist($data)
{
    $subscribers = get_flex_column_item($data, 1);
    if ($subscribers) {
        $subscribers = explode(" ", nav($subscribers, TEXT_RUN_TEXT))[0];
    }

    $parsed = [
        "title" => nav(get_flex_column_item($data, 0), TEXT_RUN_TEXT),
        "browseId" => nav($data, NAVIGATION_BROWSE_ID),
        "subscribers" => $subscribers,
        "thumbnails" => nav($data, THUMBNAILS),
    ];
    $parsed = array_merge($parsed, parse_ranking($data));
    return $parsed;
}

/**
 * @return array
 */
function parse_chart_trending($data)
{
    $flex_0 = get_flex_column_item($data, 0);
    $artists = parse_song_artists($data, 1);

    // last item is views
    $views = null;
    $last = end($artists);
    if (!$last->id) {
        $views = explode(" ", array_pop($artists)->name)[0];
    }

    $parsed = [
        "title" => nav($flex_0, TEXT_RUN_TEXT),
        "videoId" => nav($flex_0, join(TEXT_RUN, NAVIGATION_VIDEO_ID), true),
        "playlistId" => nav($flex_0, join(TEXT_RUN, NAVIGATION_PLAYLIST_ID), true),
        "artists" => $artists,
        "thumbnails" => nav($data, THUMBNAILS),
        "views" => $views
    ];
    return $parsed;
}

/**
 * @return array
 */
function parse_ranking($data)
{
    return [
        "rank" => nav($data, join("customIndexColumn.musicCustomIndexColumnRenderer", TEXT_RUN_TEXT)),
        "trend" => TRENDS[nav($data, "customIndexColumn.musicCustomIndexColumnRenderer.icon.iconType")]
    ];
}
