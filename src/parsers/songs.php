<?php

namespace Ytmusicapi;

function parse_song_artists($data, $index)
{
    $flex_item = get_flex_column_item($data, $index);
    if (!$flex_item) {
        return [];
    }

    $runs = $flex_item->text->runs;

    return parse_song_artists_runs($runs);
}

/**
 * Crazy parsing of song data. Used all over the place.
 *
 * @param array $runs
 * @return array This returns an array as it is usually merged with another array
 *   Here is the data it can return:
 *   - artists: array of artists, each with name and id
 *   - album: array with name and id
 *   - year: string
 *   - views: string
 *   - duration: string
 *   - duration_seconds: int
 */
function parse_song_runs($runs)
{
    $parsed = ['artists' => []];
    foreach ($runs as $i => $run) {
        if ($i % 2) { // uneven items are always separators
            continue;
        }
        $text = $run->text;
        if (isset($run->navigationEndpoint)) { // artist or album
            $item = (object)[
                "name" => $text,
                "id" => nav($run, NAVIGATION_BROWSE_ID, true),
            ];

            if ($item->id && (str_starts_with($item->id, 'MPRE')
                || str_contains($item->id, "release_detail"))) { // album
                $parsed['album'] = $item;
            } else { // artist
                $parsed['artists'][] = $item;
            }
        } else {
            // note: YT uses non-breaking space \xa0 to separate number and magnitude
            if (preg_match("/^\d([^ ])* [^ ]*$/", $text) && $i > 0) {
                $parsed['views'] = explode(' ', $text)[0];
            } elseif (preg_match("/^(\d+:)*\d+:\d+$/", $text)) {
                $parsed['duration'] = $text;
                $parsed['duration_seconds'] = parse_duration($text);
            } elseif (preg_match("/^\d{4}$/", $text)) {
                $parsed['year'] = $text;
            } else { // artist without id
                $parsed['artists'][] = (object)['name' => $text, 'id' => null];
            }
        }
    }

    return $parsed;
}

/**
 * @return Ref[]
 */
function parse_song_artists_runs($runs)
{
    $artists = [];

    for ($j = 0; $j < (int)(count($runs) / 2) + 1; $j++) {
        $artists[] = (object)[
            "name" => $runs[$j * 2]->text,
            "id" => nav($runs[$j * 2], NAVIGATION_BROWSE_ID, true),
        ];
    }
    return $artists;
}

/**
 * @return Album
 */
function parse_song_album($data, $index)
{
    $flex_item = get_flex_column_item($data, $index);
    $browse_id = nav($flex_item, join(TEXT_RUN, NAVIGATION_BROWSE_ID), true);

    if (!$flex_item) {
        return null;
    }

    return (object)[
        "name" => get_item_text($data, $index),
        "id" => $browse_id,
    ];
}

/**
 * Returns True if song is in the library
 *
 * @return bool
 */
function parse_song_library_status($item)
{
    $library_status = nav($item, join(TOGGLE_MENU, 'defaultIcon', 'iconType'), true);

    return $library_status == "LIBRARY_SAVED";
}

function parse_song_menu_tokens($item)
{
    $toggle_menu = $item->toggleMenuServiceItemRenderer;

    $library_add_token = nav($toggle_menu, join('defaultServiceEndpoint', FEEDBACK_TOKEN), true);
    $library_remove_token = nav($toggle_menu, join('toggledServiceEndpoint', FEEDBACK_TOKEN), true);

    $in_library = parse_song_library_status($item);
    if ($in_library) {
        $temp = $library_add_token;
        $library_add_token = $library_remove_token;
        $library_remove_token = $temp;
    }

    return (object)['add' => $library_add_token, 'remove' => $library_remove_token];
}

/**
 * Return current status based on what the button says. For instance
 * if the button says "LIKE" then the current status is either "DISLIKE"
 * or "INDIFFERENT". For some reason we choose to just return INDIFFERENT.
 */
function parse_like_status($service)
{
    $action = $service->likeEndpoint->status;
    return $action === "LIKE" ? "INDIFFERENT" : "LIKE";
}
