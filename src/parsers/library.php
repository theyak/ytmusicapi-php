<?php

namespace Ytmusicapi;

use function Ytmusicapi\parse_podcast;

/**
 * Helper function to parse artists from search results
 *
 * Differences from Python version:
 *   - Number of songs by artist is used for library artists, subscribers used for subscriptions
 *
 * @param object $results
 * @param bool $uploaded
 * @param string $from One of "artists" or "subscriptions" indicating which type of object this is.
 * @return ArtistInfo[]
 */
function parse_artists($results, $uploaded = false, $from = "artists")
{
    $artists = [];

    foreach ($results as $result) {
        $data = $result->musicResponsiveListItemRenderer;
        $artist = new ArtistInfo();
        $artist->browseId = nav($data, NAVIGATION_BROWSE_ID);
        $artist->artist = get_item_text($data, 0);
        parse_menu_playlists($data, $artist);
        if ($uploaded) {
            $artist->songs = explode(' ', get_item_text($data, 1))[0];
        } else {
            $subtitle = get_item_text($data, 1);
            if ($subtitle) {
                if ($from === "artists") {
                    $artist->songs = explode(' ', $subtitle)[0];
                    $artist->subscribers = $artist->songs; // For Python version compatibility
                } else {
                    $artist->songs = null;
                    $artist->subscribers = explode(' ', $subtitle)[0];
                }
            }
        }
        $artist->thumbnails = nav($data, THUMBNAILS, true);
        $artists[] = $artist;
    }

    return $artists;
}


/**
 * Parse a list of albums from library albums or artist albums.
 * Calls parse_albums to do actual parsing.
 *
 * Known differences from Python version:
 *  - Attempts to set isExplicit value
 *
 * @param object $response ytmusicapi response
 * @param callable $request_func function to call to get next page of results
 * @param int $limit Continue getting albums until at least this many albums are found or no more albums available
 * @return AlbumInfo[]
 */
function parse_library_albums($response, $request_func, $limit)
{
    $results = get_library_contents($response, GRID);
    if ($results === null) {
        return [];
    }

    $albums = parse_albums($results->items);

    if (isset($results->continuations)) {
        $parse_func = function ($contents) {
            return parse_albums($contents);
        };
        $remaining_limit = $limit === null ? null : ($limit - count($albums));
        $albums = array_merge(
            $albums,
            get_continuations(
                $results,
                'gridContinuation',
                $remaining_limit,
                $request_func,
                $parse_func
            )
        );
    }

    return $albums;
}

/**
 * Parse a list of podcasts from library.
 * Calls parse_content_list to do actual parsing.
 *
 * @param object $response ytmusicapi response
 * @param callable $request_func function to call to get next page of results
 * @param int $limit Continue getting playlists until at least this many playlists are found or no more playlists available
 * @return PodcastShelfItem[]
 */
function parse_library_podcasts($response, $request_func, $limit)
{
    $results = get_library_contents($response, GRID);
    $parse_func = fn ($contents) => parse_content_list($contents, fn ($c) => parse_podcast($c));
    $podcasts = $parse_func(array_slice($results->items, 1));

    if (!empty($results->continuations)) {
        $remaining_limit = $limit === null ? null : ($limit - count($podcasts));
        $podcasts = array_merge(
            $podcasts,
            get_continuations(
                $results,
                'gridContinuation',
                $remaining_limit,
                $request_func,
                $parse_func
            )
        );
    }

    return $podcasts;
}

/**
 * Parse a list of artists from library artists
 * Calls parse_artists to do actual parsing.
 *
 * @param object $response ytmusicapi response
 * @param callable $request_func function to call to get next page of results
 * @param int $limit Continue getting artists until at least this many artists are found or no more artists available
 * @param string $from One of "artists" or "subscriptions" indicating which type of object this is.
 * @return ArtistInfo[]
 */
function parse_library_artists($response, $request_func, $limit, $from = "artists")
{
    $results = get_library_contents($response, MUSIC_SHELF);
    if ($results === null) {
        return [];
    }
    $artists = parse_artists($results->contents, false, $from);

    if (isset($results->continuations)) {
        $parse_func = function ($contents) use ($from) {
            return parse_artists($contents, false, $from);
        };
        $remaining_limit = $limit === null ? null : ($limit - count($artists));
        $artists = array_merge(
            $artists,
            get_continuations(
                $results,
                'musicShelfContinuation',
                $remaining_limit,
                $request_func,
                $parse_func
            )
        );
    }

    return $artists;
}

/**
 * Parse a list of albums from library albums or artist albums
 *
 * Known differences from Python version:
 *   - Attempts to set isExplicit value
 *
 * @param object $results
 * @return AlbumInfo[]
 */
function parse_albums($results)
{
    $albums = [];

    foreach ($results as $result) {
        $album = new AlbumInfo();
        $data = $result->musicTwoRowItemRenderer;

        $album->browseId = nav($data, join(TITLE, NAVIGATION_BROWSE_ID));
        $album->playlistId = nav($data, MENU_PLAYLIST_ID, true);
        $album->title = nav($data, TITLE_TEXT);
        $album->thumbnails = nav($data, THUMBNAIL_RENDERER);
        $album->isExplicit = nav($data, SUBTITLE_BADGE_LABEL, true) === "Explicit";

        if (isset($data->subtitle->runs)) {
            $album->type = nav($data, SUBTITLE);

            // artists and year
            $runs = parse_song_runs(array_slice($data->subtitle->runs, 2));
            $album->artists = isset($runs['artists']) ? $runs['artists'] : null;
            $album->year = isset($runs['year']) ? $runs['year'] : null;
        }

        $albums[] = $album;
    }

    return $albums;
}

/**
 * Remove the random mix that conditionally appears at the start of library sons
 */
function pop_songs_random_mix($results)
{
    if ($results) {
        if (count($results->contents) >= 2) {
            array_shift($results->contents);
        }
    }
}

/**
 * Parse a list of songs from library songs
 */
function parse_library_songs($response)
{
    $results = get_library_contents($response, MUSIC_SHELF);
    pop_songs_random_mix($results);
    return (object)[
        'results' => $results,
        'parsed' => parse_playlist_items($results->contents) ?? $results
    ];
}

/**
 * Find library contents. This function is a bit messy now
 * as it is supporting two different response types. Can be
 * cleaned up once all users are migrated to the new responses.
 * @param object response ytmusicapi response
 * @param string renderer GRID or MUSIC_SHELF
 * @return object library contents or None
 */
function get_library_contents($response, $renderer)
{
    $section = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST), true);
    $contents = null;
    if ($section === null) {
        $contents = nav($response, join(SINGLE_COLUMN, TAB_1_CONTENT, SECTION_LIST_ITEM, $renderer), true);
    } else {
        $results = find_object_by_key($section, 'itemSectionRenderer');
        if ($results === null) {
            $contents = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, $renderer), true);
        } else {
            $contents = nav($results, join(ITEM_SECTION, $renderer), true);
        }
    }
    return $contents;
}
