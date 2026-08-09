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
 * @param object $data
 * @return array
 */
function parse_chart_song($data)
{
    $parsed = parse_song_flat($data, with_playlist_id: true);
    $parsed = (object)array_merge((array)$parsed, (array)parse_ranking($data, none_if_absent: false));
    return $parsed;
}

/**
 * @param object $data
 * @return array
 */
function parse_trending_item($data)
{
    $video_type = nav($data, join(PLAY_BUTTON, "playNavigationEndpoint", NAVIGATION_VIDEO_TYPE));
    if ($video_type === "MUSIC_VIDEO_TYPE_PODCAST_EPISODE") {
        return parse_episode_flat($data);
    }

    return parse_song_flat($data, with_playlist_id: true);
}


/**
 * @param object $data
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
 * @param object $data
 * @return Episode
 */
function parse_chart_episode($data)
{
    $episode = parse_episode($data);
    unset($episode->index);
    $episode->podcast = parse_id_name(nav($data, ["secondTitle", "runs", 0]));

    // This is in the Python version, 1.12.2, but it doesn't seem right. It's also the
    // default in parse_episode() so we don't need to set it here.
    $episode->duration = nav($data, join("playbackProgress", PROGRESS_RENDERER, DURATION_TEXT), true);

    // This seems to work.
    if (!$episode->duration) {
        $episode->duration = nav($data, SUBTITLE2, true);
    }

    return $episode;
}

/**
 * @param object $data
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
    $parsed = array_merge($parsed, parse_ranking($data, none_if_absent: true));
    return $parsed;
}

/**
 * @param object $data
 * @param bool $none_if_absent If true, returns null if rank or trend is
 *
 * @return array
 */
function parse_ranking($data, $none_if_absent = true)
{
    $trend_icon_type = nav($data, join("customIndexColumn.musicCustomIndexColumnRenderer", ICON_TYPE), $none_if_absent);

    $result = [
        "rank" => nav($data, join("customIndexColumn.musicCustomIndexColumnRenderer", TEXT_RUN_TEXT), $none_if_absent),
        "trend" => TRENDS[$trend_icon_type] ?? null
    ];

    return $result;
}
