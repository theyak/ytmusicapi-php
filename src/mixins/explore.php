<?php

namespace Ytmusicapi;

trait Explore
{
    /**
     * Fetch "Moods & Genres" categories from YouTube Music.
     *
     * @return array<string, Category[]> Array of sections and categories.
     */
    public function get_mood_categories()
    {
        $sections = [];
        $response = $this->_send_request('browse', ['browseId' => 'FEmusic_moods_and_genres']);

        foreach (nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST)) as $section) {
            $title = nav($section, join(GRID, 'header.gridHeaderRenderer', TITLE_TEXT));
            $sections[$title] = [];
            foreach (nav($section, GRID_ITEMS) as $category) {
                $sections[$title][] = (object)[
                    "title" => nav($category, CATEGORY_TITLE),
                    "params" => nav($category, CATEGORY_PARAMS)
                ];
            }
        }
        return $sections;
    }

    /**
     * Retrieve a list of playlists for a given "Moods & Genres" category.
     *
     * @param string $params obtained by `get_mood_categories`
     * @return RelatedPlaylist[] List of playlists in the format of `get_library_playlists`
     */
    public function get_mood_playlists($params)
    {
        $playlists = [];
        $response = $this->_send_request('browse', [
            'browseId' => 'FEmusic_moods_and_genres_category',
            'params' => $params
        ]);

        $nav = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));

        foreach ($nav as $section) {
            $path = "";
            if (isset($section->gridRenderer)) {
                $path = GRID_ITEMS;
            } elseif (isset($section->musicCarouselShelfRenderer)) {
                $path = CAROUSEL_CONTENTS;
            } elseif (isset($section->musicImmersiveCarouselShelfRenderer)) {
                $path = 'musicImmersiveCarouselShelfRenderer.contents';
            }
            if ($path) {
                $results = nav($section, $path);
                $list = parse_content_list($results, 'Ytmusicapi\\parse_playlist');
                $playlists = array_merge($playlists, $list);
            }
        }

        return $playlists;
    }

    /**
     * Get the latest data from YouTube Music: Top songs, top videos, top artists and top trending videos.
     * Global charts have no Trending section, US charts have an extra Genres section with some Genre charts.
     *
     * @param string $country ISO 3166-1 Alpha-2 country code. Default: ZZ = Global
     * @return array Dictionary containing chart songs (only if authenticated with premium account), chart videos, chart artists and
     */
    public function get_charts($country = "ZZ")
    {
        $body = ['browseId' => 'FEmusic_charts'];
        if ($country) {
            $body['formData'] = ['selectedValues' => [$country]];
        }
        $endpoint = 'browse';
        $response = $this->_send_request($endpoint, $body);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));

        $charts = ['countries' => []];
        $menu = nav(
            $results[0],
            join(MUSIC_SHELF, 'subheaders.0.musicSideAlignedItemRenderer.startItems.0.musicSortFilterButtonRenderer')
        );
        $charts['countries']['selected'] = nav($menu, TITLE);
        $charts['countries']['options'] = [];

        $mutations = nav($response, FRAMEWORK_MUTATIONS);
        foreach ($mutations as $m) {
            $token = nav($m, 'payload.musicFormBooleanChoice.opaqueToken', true);
            if ($token) {
                $charts['countries']['options'][] = $token;
            }
        }

        $charts_categories = ['videos', 'artists'];

        $has_genres = $country === 'US';
        $has_trending = $country !== "ZZ";

        // Either songs or videos will be in position 1.
        // It seems like premium accounts have songs, free accounts don't.
        // $has_songs = !!nav($results[1], join(CAROUSEL_CONTENTS, '0', MRLIR), true);

        $has_songs = (count($results) - 1) > (count($charts_categories) + (int)$has_genres + (int)$has_trending);

        if ($has_songs) {
            array_unshift($charts_categories, 'songs');
        }
        if ($has_genres) {
            $charts_categories[] = 'genres';
        }
        if ($has_trending) {
            $charts_categories[] = 'trending';
        }

        $parse_chart = function ($i, $parse_func, $key) use ($results, $has_songs) {
            return parse_content_list(
                nav($results[$i + (int)$has_songs], CAROUSEL_CONTENTS),
                $parse_func,
                $key
            );
        };

        foreach ($charts_categories as $i => $c) {
            $charts[$c] = [
                'playlist' => nav($results[1 + $i], join(CAROUSEL, CAROUSEL_TITLE, NAVIGATION_BROWSE_ID), true),
                'title' => nav($results[1 + $i], join(CAROUSEL, CAROUSEL_TITLE, "text"), true),
            ];
        }

        if ($has_songs) {
            $charts['songs'] = ['items' => $parse_chart(0, 'Ytmusicapi\\parse_chart_song', MRLIR)];
        }

        $charts['videos'] = ['items' => $parse_chart(1, 'Ytmusicapi\\parse_video', MTRIR)];
        $charts['artists'] = ['items' => $parse_chart(2, 'Ytmusicapi\\parse_chart_artist', MRLIR)];

        if ($has_genres) {
            $charts['genres'] = $parse_chart(3, 'Ytmusicapi\\parse_playlist', MTRIR);
        }

        if ($has_trending) {
            $charts['trending'] = ['items' => $parse_chart(3 + (int)$has_genres, 'Ytmusicapi\\parse_chart_trending', MRLIR)];
        }

        return $charts;
    }
}
