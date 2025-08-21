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
    $parsed = parse_trending_song($data);
    $parsed = (object)array_merge((array)$parsed, (array)parse_ranking($data));
    return $parsed;
}

/**
 * @return Object
 */
function parse_chart_playlist($data)
{
    return (object)[
        "title" => nav($data, TITLE_TEXT),
        "playlistId" => substr(nav($data, [TITLE, NAVIGATION_BROWSE_ID]), 2),
        "thumbnails" => nav($data, THUMBNAIL_RENDERER),
    ];
}


/**
 * @return Episode
 */
function parse_chart_episode($data)
{
    $episode = parse_episode($data);
    unset($episode->index);
    $episode->podcast = parse_id_name(nav($data, ["secondTitle", "runs", 0]));
    $episode->duration = nav($data, SUBTITLE2, true);
    return $episode;
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
function parse_trending_song($data)
{
    $flex_0 = get_flex_column_item($data, 0);
    $flex_1 = get_flex_column_item($data, 1);
    
    $parsed = [
        "title" => nav($flex_0, TEXT_RUN_TEXT),
        "videoId" => nav($flex_0, join(TEXT_RUN, NAVIGATION_VIDEO_ID), true),
        ...parse_song_runs(nav($flex_1, TEXT_RUNS)), // Gets artists and views
        "playlistId" => nav($flex_0, join(TEXT_RUN, NAVIGATION_PLAYLIST_ID), true),
        "thumbnails" => nav($data, THUMBNAILS),
        "isExplicit" => !!nav($data, BADGE_LABEL, true),
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
