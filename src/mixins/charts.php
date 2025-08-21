<?php

namespace Ytmusicapi;

trait Charts
{
    /**
     * Get latest charts data from YouTube Music: Artists and playlists of top videos.
     * US charts have an extra Genres section with some Genre charts.
     *
     * @param string $country ISO 3166-1 Alpha-2 country code. Default: "ZZ" = Global
     * @return array Dictionary containing chart video playlists (with separate daily/weekly 
     *               charts if authenticated with a premium account), chart genres (US-only), 
     *               and chart artists.
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

        // Define chart categories
        $chartsCategories = [
            ["videos", "Ytmusicapi\\parse_chart_playlist", MTRIR]
        ];
        
        // Add genres for US only
        if ($country === "US") {
            $chartsCategories[] = ["genres", "Ytmusicapi\\parse_chart_playlist", MTRIR];
        }
        
        // Add artists
        $chartsCategories[] = ["artists", "Ytmusicapi\\parse_chart_artist", MRLIR];

        // use result length to determine if the daily/weekly chart categories are present
        // could also be done via an is_premium attribute on YTMusic instance
        if ((count($results) - 1) > count($chartsCategories)) {
            // Daily and weekly replace the "videos" playlist carousel
            $chartsCategories = array_merge(
                [
                    ["daily", "Ytmusicapi\\parse_chart_playlist", MTRIR],
                    ["weekly", "Ytmusicapi\\parse_chart_playlist", MTRIR]
                ],
                array_slice($chartsCategories, 1)
            );
        }
        
        // Process each category
        foreach ($chartsCategories as $i => $category) {
            list($name, $parse_func, $key) = $category;
            $carouselContents = nav($results[1 + $i], CAROUSEL_CONTENTS);
            $charts[$name] = parse_content_list($carouselContents, $parse_func, $key);
        }
        
        return $charts;
    }
}