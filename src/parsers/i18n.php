<?php

namespace Ytmusicapi;

function get_search_result_types()
{
    // TODO: Language conversion
    return [
        'artist',
        'playlist',
        'song',
        'video',
        'station',
        'profile',
        'podcast',
        'episode'
    ];
}

/**
 * Get data related to various categories of an artists.
 */
function parse_artist_contents($results)
{
    // I've never seen playlists or related come through.
    // YTM seems to make an additional search call to the artists
    // to get these and other values.
    $categories = ['albums', 'singles', 'videos', 'playlists', 'related'];

    // TODO: Language conversion
    $categories_local = ['albums', 'singles', 'videos', 'playlists', 'related'];

    // Wait, is this really the best way to do this in PHP?!?
    $categories_parser = [
        'Ytmusicapi\parse_album',
        'Ytmusicapi\parse_single',
        'Ytmusicapi\parse_video',
        'Ytmusicapi\parse_playlist',
        'Ytmusicapi\parse_related_artist',
    ];

    $artist = [];
    foreach ($categories as $i => $category) {
        $artist[$category] = null;

        // Find the shelf for the category.
        $data = array_filter($results, function ($r) use ($categories_local, $i) {
            if (!isset($r->musicCarouselShelfRenderer)) {
                return false;
            }

            $title = nav($r, join(CAROUSEL, CAROUSEL_TITLE), true);
            if ($title && mb_strtolower($title->text) === mb_strtolower($categories_local[$i])) {
                return true;
            }

            return false;
        });

        $data = array_map(function ($r) {
            return $r->musicCarouselShelfRenderer;
        }, $data);

        // Process data in shelf
        if (count($data) > 0) {
            $data = reset($data);
            $artist[$category] = (object)[
                'browseId' => null,
                'results' => [],
            ];

            $title = nav($data, join(CAROUSEL_TITLE), true);
            if (!empty($title->navigationEndpoint)) {
                $artist[$category]->browseId = nav($title, NAVIGATION_BROWSE_ID);
            }

            if (in_array($category, ['albums', 'singles', 'playlists'])) {
                $artist[$category]->params = nav($title, 'navigationEndpoint.browseEndpoint.params', true);
            }

            $parser = $categories_parser[$i];
            $artist[$category]->results = parse_content_list($data->contents, $parser);
        }
    }

    return $artist;
}
