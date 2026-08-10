<?php

namespace Ytmusicapi;

function parse_song_artists($data, $index)
{
    $flex_item = get_flex_column_item($data, $index);
    if (!$flex_item) {
        return [];
    }

    $runs = $flex_item->text->runs;

    return parse_artists_runs($runs);
}

/**
 * @param string $text
 * @return string|null
 */
function parse_views($text)
{
    $viewsPrefix = '/^\D*?[\s:\x{ff1a}\x{200e}-\x{200f}\x{202a}-\x{202e}]/u';

    // Only for non-Latin scripts: "Maroon 5" is indistinguishable
    // from a prefixed count.
    $prefixed = 0;

    if (!preg_match('/[a-zA-Z]/', $text)) {
        $text = preg_replace(
            $viewsPrefix,
            '',
            $text,
            1,
            $count
        );

        $prefixed = $count;
    }

    if (!preg_match('/^\d/', $text)) {
        return null;
    }

    // A bare ASCII token like "2Pac" is an artist, not a view count
    // with a stripped word.
    if (!$prefixed && mb_check_encoding($text, 'ASCII') && !str_contains($text, ' ')) {
        return null;
    }

    // Note: NBSP glues number and magnitude ("1,7 Mrd. Aufrufe"),
    // but some locales use it before the views word too ("88 k vues");
    // CJK has no separator ("3406万回視聴")
    $head = explode(' ', $text, 2)[0];
    $head = explode("\u{00A0}", $head);

    return implode("\u{00A0}", array_slice($head, 0, 2));
}

/**
 * @param object $run
 * @return array
 */
function parse_song_run($run) {
    $text = $run->text;
    if (isset($run->navigationEndpoint)) { // artist or album
        $item = (object)[
            "name" => $text,
            "id" => nav($run, NAVIGATION_BROWSE_ID, true),
        ];

        if ($item->id && (str_starts_with($item->id, 'MPRE')
            || str_contains($item->id, "release_detail"))) { // album
            return ["type" => "album", "data" => $item];
        } else { // artist
            return ["type" => "artist", "data" => $item];
        }
    } else {
        if (preg_match("/^(\d+:)*\d+:\d+$/", $text)) {
            return ["type" => "duration", "data" => $text];
        } elseif (preg_match("/^\d{4}$/", $text)) {
            return ["type" => "year", "data" => $text];
        } elseif (parse_views($text) !== null) {
            return ["type" => "views", "data" => parse_views($text)];
        } else { // artist without id
            return ["type" => "artist", "data" => (object)["name" => $text, "id" => null]];
        }
    }
}

/**
 * Crazy parsing of song data. Used all over the place.
 *
 * @param array $runs
 * @param bool $skip_type_spec if true, skip the type specifier (like "Song", "Single", or "Album")
 *   that may appear before artists ("Song • Eminem"). Otherwise, that text item is parsed as an artist with no ID.
 * @return array This returns an array as it is usually merged with another array
 *   Here is the data it can return:
 *   - artists: array of artists, each with name and id
 *   - album: array with name and id
 *   - year: string
 *   - views: string
 *   - duration: string
 *   - duration_seconds: int
 */
function parse_song_runs($runs, $skip_type_spec = false)
{
    $parsed = ['artists' => []];

    // prevent type specifier from being parsed as an artist
    // it's the unlinked first run, separated by " • " from the artists, or from
    // metadata (duration/views/year) when the song lists no artist at all
    if (
        $skip_type_spec &&
        count($runs) > 2 &&
        empty($runs[0]->navigationEndpoint) &&
        parse_song_run($runs[0])["type"] == "artist" &&
        !empty($runs[1]->text) &&
        $runs[1]->text === " • " &&
        in_array((parse_song_run($runs[2]))["type"], ["artist", "duration", "views", "year"])
    ) {
        $runs = array_slice($runs, 2);
    }

    foreach ($runs as $i => $run) {
        if ($i % 2) { // uneven items are always separators
            continue;
        }

        $parsed_run = parse_song_run($run);
        $data = $parsed_run["data"];

        if ($parsed_run["type"] === "album") {
            $parsed["album"] = $data;
        } elseif ($parsed_run["type"] === "artist") {
            $parsed["artists"] = $parsed["artists"] ?? [];
            $parsed["artists"][] = $data;
        } elseif ($parsed_run["type"] === "views") {
            $parsed["views"] = $data;
        } elseif ($parsed_run["type"] === "duration") {
            $parsed["duration"] = $data;
            $parsed["duration_seconds"] = parse_duration($data);
        } elseif ($parsed_run["type"] === "year") {
            $parsed["year"] = $data;
        }
    }

    return $parsed;
}


/**
 * @return Album
 */
function parse_song_album($data, $index)
{
    $flex_item = get_flex_column_item($data, $index);
    $browse_id = nav($flex_item, join(TEXT_RUN, NAVIGATION_BROWSE_ID), true);

    if (!$flex_item || !$browse_id) {
        return null;
    }

    return (object)[
        "name" => get_item_text($data, $index),
        "id" => $browse_id,
    ];
}

/**
 * Return dictionary with data from the provided song's context menu.
 *
 * Example:
 *
 * {
 *     "inLibrary": true,
 *     "feedbackTokens": {
 *         "add": "...",
 *         "remove": "..."
 *     },
 *     "pinnedToListenAgain": true,
 *     "listenAgainFeedbackTokens": {
 *         "pin": "...",
 *         "unpin": "..."
 *     }
 *
 * @param object $data
 * @return array
 */
function parse_song_menu_data($data)
{
    if (empty($data->menu)) {
        return [];
    }

    $song_data = [];

    foreach (nav($data, MENU_ITEMS) as $item) {
        $menu_item = nav($item, [TOGGLE_MENU], true)
            ?? nav($item, ["menuServiceItemRenderer"], true);

        if ($menu_item === null) {
            continue;
        }

        $song_data["inLibrary"] = $song_data["inLibrary"] ?? false;
        $song_data["pinnedToListenAgain"] = $song_data["pinnedToListenAgain"] ?? false;

        $current_icon_type = nav($menu_item, ["defaultIcon", "iconType"], true)
            ?? nav($menu_item, "icon.iconType", true);

        $feedback_token = function (string $endpoint_type) use ($menu_item): ?string {
            return nav($menu_item, join($endpoint_type, FEEDBACK_TOKEN), true);
        };

        // YTM signals the current state with isToggled instead of swapping
        // the default/toggled icons.
        $is_toggled = (bool) ($menu_item->isToggled ?? false);

        switch ($current_icon_type) {
            case "KEEP": // pin to listen again
                $song_data["pinnedToListenAgain"] = $is_toggled;
                $song_data["listenAgainFeedbackTokens"] = (object)[
                    "pin" => $feedback_token("defaultServiceEndpoint"),
                    "unpin" => $feedback_token("toggledServiceEndpoint"),
                ];
                break;

            case "KEEP_OFF": // unpin from listen again
                $song_data["pinnedToListenAgain"] = true;
                $song_data["listenAgainFeedbackTokens"] = (object)[
                    "pin" => $feedback_token("toggledServiceEndpoint"),
                    "unpin" => $feedback_token("defaultServiceEndpoint"),
                ];
                break;

            case "BOOKMARK_BORDER": // add to library
                $song_data["inLibrary"] = $is_toggled;
                $song_data["feedbackTokens"] = (object)[
                    "add" => $feedback_token("defaultServiceEndpoint"),
                    "remove" => $feedback_token("toggledServiceEndpoint"),
                ];
                break;

            case "BOOKMARK": // remove from library
                $song_data["inLibrary"] = true;
                $song_data["feedbackTokens"] = (object)[
                    "add" => $feedback_token("toggledServiceEndpoint"),
                    "remove" => $feedback_token("defaultServiceEndpoint"),
                ];
                break;

            case "REMOVE_FROM_HISTORY":
                $song_data["feedbackToken"] = $feedback_token("serviceEndpoint");
                break;
        }
    }

    return $song_data;
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
