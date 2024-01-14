<?php

namespace Ytmusicapi;

/**
 * Check if found result type is valid.
 *
 * @param string $result_type_local Found result type
 * @param string[] $result_types_local List of valid result types in defined language
 * @return string the result type in English. "album" if not found in $result_types_local. Happens more often than it should.
 */
function get_search_result_type($result_type_local, $result_types_local)
{
    if (!$result_type_local) {
        return null;
    }

    $result_types = ['artist', 'playlist', 'song', 'video', 'station', 'profile', 'podcast', 'episode'];
    $result_type_local = mb_strtolower($result_type_local);

    // default to album since it's labeled with multiple values ('Single', 'EP', etc.)
    if (!in_array($result_type_local, $result_types_local)) {
        $result_type = 'album';
    } else {
        $result_type = $result_types[array_search($result_type_local, $result_types_local)];
    }

    return $result_type;
}

function parse_top_result($data, $search_result_types)
{
    $result_type = get_search_result_type(nav($data, SUBTITLE), $search_result_types);

    $search_result = ['category' => nav($data, CARD_SHELF_TITLE), 'resultType' => $result_type];

    if ($result_type === 'artist') {
        $subscribers = nav($data, SUBTITLE2, true);
        if ($subscribers) {
            $search_result['subscribers'] = explode(' ', $subscribers)[0];
        }
        $artist_info = parse_song_runs(nav($data, ['title', 'runs']));
        $search_result = array_merge($search_result, $artist_info);
    }

    if (in_array($result_type, ['song', 'video'])) {
        $on_tap = $data->onTap;
        if ($on_tap) {
            $search_result['videoId'] = nav($on_tap, WATCH_VIDEO_ID);
            $search_result['videoType'] = nav($on_tap, NAVIGATION_VIDEO_TYPE);
        }
    }

    if (in_array($result_type, ['song', 'video', 'album'])) {
        $search_result['title'] = nav($data, TITLE_TEXT);
        $runs = array_slice(nav($data, 'subtitle.runs'), 2);
        $song_info = parse_song_runs($runs);
        $search_result = array_merge($search_result, $song_info);
    }

    if ($result_type === 'album') {
        $search_result['browseId'] = nav($data, join(TITLE, NAVIGATION_BROWSE_ID), true);
    }

    $search_result['thumbnails'] = nav($data, THUMBNAILS, true);

    return (object)$search_result;
}

function parse_search_result($data, $search_result_types, $result_type, $category)
{
    $default_offset = (empty($result_type) || $result_type === 'album') * 2;
    $search_result = ['category' => $category];
    $video_type = nav($data, join(PLAY_BUTTON, 'playNavigationEndpoint', NAVIGATION_VIDEO_TYPE), true);

    if (empty($result_type) && $video_type) {
        // NOTE: This logic differs from Python. It makes a half-hearted
        // attempt to fix https://github.com/sigma67/ytmusicapi/issues/483
        $text = strtolower(get_item_text($data, 1)); // TODO: Does this need language processing?
        if (!in_array($text, ["album", "single"])) {
            $result_type = $video_type === 'MUSIC_VIDEO_TYPE_ATV' ? 'song' : 'video';
        }
    }

    if (!$result_type) {
        $result_type = get_search_result_type(get_item_text($data, 1), $search_result_types);
    }

    $search_result['resultType'] = $result_type;

    if ($result_type !== 'artist') {
        $search_result['title'] = get_item_text($data, 0);
    }

    if ($result_type === 'artist') {
        $search_result['artist'] = get_item_text($data, 0);
        parse_menu_playlists($data, $search_result);
    } elseif ($result_type === 'album') {
        $search_result['type'] = get_item_text($data, 1);
    } elseif ($result_type === 'playlist') {
        $flex_item = get_flex_column_item($data, 1)->text->runs;
        $has_author = count($flex_item) === $default_offset + 3;
        $search_result['itemCount'] = explode(' ', get_item_text($data, 1, $default_offset + $has_author * 2))[0];
        $search_result['author'] = !$has_author ? null : get_item_text($data, 1, $default_offset);
    } elseif ($result_type === 'station') {
        $search_result['videoId'] = nav($data, NAVIGATION_VIDEO_ID);
        $search_result['playlistId'] = nav($data, NAVIGATION_PLAYLIST_ID);
    } elseif ($result_type === 'profile') {
        $search_result['name'] = get_item_text($data, 1, 2, true);
    } elseif ($result_type === 'song') {
        $search_result['album'] = null;
        if (isset($data->menu)) {
            $toggle_menu = find_object_by_key(nav($data, MENU_ITEMS), TOGGLE_MENU);
            if ($toggle_menu) {
                $search_result['inLibrary'] = parse_song_library_status($toggle_menu);
                $search_result['feedbackTokens'] = parse_song_menu_tokens($toggle_menu);
            }
        }
    } elseif ($result_type === "upload") {
        $browse_id = nav($data, NAVIGATION_BROWSE_ID, true);
        if (!$browse_id) { // Song result
            $flex_items = [];
            for ($i = 0; $i < 2; $i++) {
                $flex_items[] = nav(get_flex_column_item($data, $i), 'text.runs', true);
            }
            if ($flex_items[0]) {
                $search_result['videoId'] = nav($flex_items[0][0], NAVIGATION_VIDEO_ID, true);
                $search_result['playlistId'] = nav($flex_items[0][0], NAVIGATION_PLAYLIST_ID, true);
            }
            if ($flex_items[1]) {
                $search_result = array_merge($search_result, parse_song_runs($flex_items[1]));
            }
            $search_result['resultType'] = 'song';
        } else { // Artist or album result
            $search_result['browseId'] = $browse_id;
            if (strpos($search_result['browseId'], 'artist') !== false) {
                $search_result['resultType'] = 'artist';
            } else {
                $flex_item2 = get_flex_column_item($data, 1);
                $runs = [];
                foreach ($flex_item2->text->runs as $i => $run) {
                    if ($i % 2 === 0) {
                        $runs[] = $run->text;
                    }
                }
                if (count($runs) > 1) {
                    $search_result['artist'] = $runs[1];
                }
                if (count($runs) > 2) { // Date may be missing
                    $search_result['releaseDate'] = $runs[2];
                }
                $search_result['resultType'] = 'album';
            }
        }
    }

    // Python version doesn't add videoId and videoType to albums - maybe this is a new feature of YTMusic?
    if ($result_type === 'song' || $result_type === 'video' || $result_type === 'album') {
        $search_result['videoId'] = nav($data, join(PLAY_BUTTON, 'playNavigationEndpoint.watchEndpoint.videoId'), true);
        $search_result['videoType'] = $video_type;
        $search_result['duration'] = null;
        $flex_item = get_flex_column_item($data, 1);
        $runs = array_slice($flex_item->text->runs, $default_offset);
        $song_info = parse_song_runs($runs);
        $search_result = array_merge($search_result, $song_info);
    }

    if (in_array($result_type, ['artist', 'album', 'playlist', 'profile'])) {
        $search_result['browseId'] = nav($data, NAVIGATION_BROWSE_ID, true);
    }

    if (in_array($result_type, ['song', 'album'])) {
        $search_result['isExplicit'] = nav($data, BADGE_LABEL, true) !== null;
    }

    $search_result['thumbnails'] = nav($data, THUMBNAILS, true);

    return (object)$search_result;
}

function parse_search_results($results, $search_result_types, $resultType = null, $category = null)
{
    $parsed_results = [];

    foreach ($results as $result) {
        $parsed_results[] = parse_search_result(
            $result->musicResponsiveListItemRenderer,
            $search_result_types,
            $resultType,
            $category
        );
    }
    return $parsed_results;
}

function get_search_params($filter, $scope, $ignore_spelling)
{
    $filtered_param1 = 'EgWKAQ';
    $params = null;
    if (!$filter && !$scope && !$ignore_spelling) {
        return $params;
    }

    if ($scope == 'uploads') {
        $params = 'agIYAw%3D%3D';
    }

    if ($scope == 'library') {
        if ($filter) {
            $param1 = $filtered_param1;
            $param2 = _get_param2($filter);
            $param3 = 'AWoKEAUQCRADEAoYBA%3D%3D';
        } else {
            $params = 'agIYBA%3D%3D';
        }
    }

    if (!$scope && $filter) {
        if ($filter === 'playlists') {
            $params = 'Eg-KAQwIABAAGAAgACgB';
            if (!$ignore_spelling) {
                $params .= 'MABqChAEEAMQCRAFEAo%3D';
            } else {
                $params .= 'MABCAggBagoQBBADEAkQBRAK';
            }
        } elseif (strpos($filter, 'playlists') !== false) {
            $param1 = 'EgeKAQQoA';
            if ($filter === 'featured_playlists') {
                $param2 = 'Dg';
            } else { // Community playlists
                $param2 = 'EA';
            }

            if (!$ignore_spelling) {
                $param3 = 'BagwQDhAKEAMQBBAJEAU%3D';
            } else {
                $param3 = 'BQgIIAWoMEA4QChADEAQQCRAF';
            }
        } else {
            $param1 = $filtered_param1;
            $param2 = _get_param2($filter);
            if (!$ignore_spelling) {
                $param3 = 'AWoMEA4QChADEAQQCRAF';
            } else {
                $param3 = 'AUICCAFqDBAOEAoQAxAEEAkQBQ%3D%3D';
            }
        }
    }

    if (!$scope && !$filter && $ignore_spelling) {
        $params = 'EhGKAQ4IARABGAEgASgAOAFAAUICCAE%3D';
    }

    return $params ?? $param1 . $param2 . $param3;
}

function _get_param2($filter)
{
    $filter_params = [
        'songs' => 'II',
        'videos' => 'IQ',
        'albums' => 'IY',
        'artists' => 'Ig',
        'playlists' => 'Io',
        'profiles' => 'JY',
        'podcasts' => 'JQ',
        'episodes' => 'JI'
    ];
    return $filter_params[$filter];
}

/**
 * Parse search suggestions
 *
 * Known differences from Python version:
 *   - Also checks for historySuggestionRenderer
 */
function parse_search_suggestions($results, $detailed_runs)
{
    if (!isset($results->contents[0]->searchSuggestionsSectionRenderer->contents)) {
        return [];
    }

    $raw_suggestions = $results->contents[0]->searchSuggestionsSectionRenderer->contents;
    $suggestions = [];


    foreach ($raw_suggestions as $raw_suggestion) {
        if (isset($raw_suggestion->historySuggestionRenderer)) {
            $suggestion_content = $raw_suggestion->historySuggestionRenderer;
        } else {
            $suggestion_content = $raw_suggestion->searchSuggestionRenderer;
        }

        $text = $suggestion_content->navigationEndpoint->searchEndpoint->query;
        $runs = $suggestion_content->suggestion->runs;

        if ($detailed_runs) {
            $suggestions[] = (object)['text' => $text, 'runs' => $runs];
        } else {
            $suggestions[] = $text;
        }
    }

    return $suggestions;
}
