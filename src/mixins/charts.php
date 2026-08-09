<?php

namespace Ytmusicapi;

/**
 * chart playlists are the only carousel items linking to a "VL" playlist
 *
 * @param array $contents
 */
function is_playlist_carousel($contents)
{
    $browse_id = nav($contents[0], join(MTRIR, TITLE, NAVIGATION_BROWSE_ID), true);
    return $browse_id && str_starts_with($browse_id, "VL");
}

/**
 * @param array $contents
 */
function is_artist_carousel($contents)
{
    return isset($contents[0]->musicResponsiveListItemRenderer);
}

trait Charts
{
    /**
     * Get latest charts data from YouTube Music: Artists and playlists of top videos.
     * Unauthenticated requests return unranked Artists with "rank" and "trend" set to None.
     * US charts have an extra Genres section, IN charts an extra Languages section.
     *
     * @param string $country ISO 3166-1 Alpha-2 country code. Default: "ZZ" = Global
     * @return array Dictionary containing chart video playlists (with separate daily/weekly
     *               charts if authenticated with a premium account), chart genres (US-only),
     *               chart languages (India only) and chart artists.
     *
     * Example return:
     * [
     *     "countries" => [
     *         "selected" => [
     *             "text" => "United States"
     *         ],
     *         "options" => ["DE", "ZZ", "ZW"]
     *     ],
     *     "videos" => [
     *         [
     *             "title" => "Daily Top Music Videos - United States",
     *             "playlistId" => "PL4fGSI1pDJn61unMfmrUSz68RT8IFFnks",
     *             "thumbnails" => []
     *         ]
     *     ],
     *     "artists" => [
     *         [
     *             "title" => "YoungBoy Never Broke Again",
     *             "browseId" => "UCR28YDxjDE3ogQROaNdnRbQ",
     *             "subscribers" => "9.62M",
     *             "thumbnails" => [],
     *             "rank" => "1",
     *             "trend" => "neutral"
     *         ]
     *     ],
     *     "genres" => [
     *         [
     *             "title" => "Top 50 Pop Music Videos United States",
     *             "playlistId" => "PL4fGSI1pDJn77aK7sAW2AT0oOzo5inWY8",
     *             "thumbnails" => []
     *         ]
     *     ]
     * ]
     */
    public function get_charts($country = "ZZ")
    {
        $body = ["browseId" => "FEmusic_charts"];

        if ($country) {
            $body["formData"] = ["selectedValues" => [$country]];
        }

        $response = $this->_send_request("browse", $body);
        $results = nav($response, [SINGLE_COLUMN_TAB, SECTION_LIST]);

        $charts = ["countries" => []];

        // Navigate to menu for country selection
        $menu = nav(
            $results[0],
            [
                MUSIC_SHELF,
                "subheaders",
                0,
                "musicSideAlignedItemRenderer",
                "startItems",
                0,
                "musicSortFilterButtonRenderer"
            ]
        );

        $charts["countries"]["selected"] = nav($menu, TITLE);

        // Get country options
        $frameworkMutations = nav($response, FRAMEWORK_MUTATIONS);
        $options = [];
        foreach ($frameworkMutations as $mutation) {
            $token = nav($mutation, "payload.musicFormBooleanChoice.opaqueToken", true);
            if ($token !== null) {
                $options[] = $token;
            }
        }
        $charts["countries"]["options"] = $options;

        // carousels carry no machine-readable identity, so recognize them by their contents;
        // anything else (some regions add an album chart) is ignored instead of shifting the
        // categories that come after it
        $carousels = [];
        foreach (array_slice($results, 1) as $section) {
            $c = nav($section, CAROUSEL_CONTENTS, true);

            if ($c) {
                $carousels[] = $c;
            }
        }

        $playlist_carousels = array_values(array_filter(
            $carousels, fn ($c) => is_playlist_carousel($c)
        ));

        $artist_carousels = array_values(array_filter(
            $carousels, fn ($c) => is_artist_carousel($c)
        ));

        $playlist_names = ["videos"];

        if ($country === "US") {
            $playlist_names[] = "genres";
        } elseif ($country === "IN") {
            $playlist_names[] = "languages";
        }

        // premium sessions get daily and weekly carousels in place of the "videos" one
        // could also be done via an is_premium attribute on YTMusic instance
        if (count($playlist_carousels) > count($playlist_names)) {
            $playlist_names = array_merge(["daily", "weekly"], array_slice($playlist_names, 1));
        }

        foreach ($playlist_names as $i => $name) {
            $charts[$name] = parse_content_list(
                $playlist_carousels[$i],
                fn ($c) => parse_chart_playlist($c),
                MTRIR
            );
        }

        if ($artist_carousels) {
            $charts["artists"] = parse_content_list(
                $artist_carousels[0],
                fn ($c) => parse_chart_artist($c),
                MRLIR
            );
        }

        return $charts;
    }
}