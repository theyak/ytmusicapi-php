<?php

namespace Ytmusicapi;

function parse_menu_playlists($data, &$result)
{
    $menu_items = nav($data, MENU_ITEMS);
    $watch_menu = find_objects_by_key($menu_items, MNIR);

    foreach ($watch_menu as $_x) {
        $item = $_x->menuNavigationItemRenderer;

        $icon = nav($item, ICON_TYPE);
        if ($icon == 'MUSIC_SHUFFLE') {
            $watch_key = 'shuffleId';
        } elseif ($icon == 'MIX') {
            $watch_key = 'radioId';
        } else {
            continue;
        }

        $watch_id = nav($item, 'navigationEndpoint.watchPlaylistEndpoint.playlistId', true);
        if (!$watch_id) {
            $watch_id = nav($item, 'navigationEndpoint.watchEndpoint.playlistId', true);
        }
        if ($watch_id) {
            if (is_object($result)) {
                $result->$watch_key = $watch_id;
            } else {
                $result[$watch_key] = $watch_id;
            }
        }
    }
}

function get_item_text($item, $index, $run_index = 0, $null_if_absent = false)
{

    $column = get_flex_column_item($item, $index);

    if (!$column) {
        return null;
    }
    if ($null_if_absent && empty($column->text->runs[$run_index])) {
        return null;
    }
    if ($null_if_absent && is_null($column->text->runs[$run_index])) {
        return null;
    }

    return $column->text->runs[$run_index]->text;
}

function get_flex_column_item($item, $index)
{
    if (count($item->flexColumns) <= $index ||
        !isset($item->flexColumns[$index]->musicResponsiveListItemFlexColumnRenderer->text->runs)) {
        return null;
    }
    return $item->flexColumns[$index]->musicResponsiveListItemFlexColumnRenderer;
}

function get_fixed_column_item($item, $index)
{
    if (!isset($item->fixedColumns[$index]->musicResponsiveListItemFixedColumnRenderer->text->runs)) {
        return null;
    }
    return $item->fixedColumns[$index]->musicResponsiveListItemFixedColumnRenderer;
}

/**
 * Gets video ID from string or URL
 *
 * @param sting $str Should be a YouTube video ID or a valid YouTube URL. Otherwise exception!
 *
 * Known differences from Python verion:
 *   - New function
 */
function get_video_id($id)
{
    // Check for valid video ID
    if (preg_match("/^[A-Za-z0-9_-]{11}$/", $id)) {
        return $id;
    }

    // Check for valid URL
    $matches = [];
    preg_match("/(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:music\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([a-zA-Z0-9\-_]*)/", $id, $matches);
    if (isset($matches[1])) {
        return $matches[1];
    }

    throw new \Exception("Invalid video ID or URL: {$id}");
}

/**
 * Goes through an array of string items looking for the " • " separator
 *
 * @param object{text: string, ...}[] $runs An array if items with a text property
 * @return int Index of the first " • " separator or the length of the array if not found
 */
function get_dot_separator_index($runs)
{
    foreach ($runs as $index => $run) {
        if (isset($run->text) && $run->text === " • ") {
            return $index;
        }
    }

    return count($runs);
}

function parse_duration($duration)
{
    if ($duration === null) {
        return $duration;
    }

    $seconds = 0;
    $parts = array_reverse(explode(":", $duration));
    if (isset($parts[2])) {
        $seconds = $parts[2] * 3600;
    }
    if (isset($parts[1])) {
        $seconds += $parts[1] * 60;
    }
    if (isset($parts[0])) {
        $seconds += $parts[0];
    }

    return $seconds;
}

function parse_id_name($sub_run)
{
    return [
        "id" => nav($sub_run, NAVIGATION_BROWSE_ID, true),
        "name" => nav($sub_run, "text", true),
    ];
}

function i18n($method)
{
    throw new \Exception("i18n not implemented. Please try to implement function.");
}

// I have no idea how to translate the following function:
/*
def i18n(method):
    @wraps(method)
    def _impl(self, *method_args, **method_kwargs):
        method.__globals__['_'] = self.lang.gettext
        return method(self, *method_args, **method_kwargs)

    return _impl
*/
