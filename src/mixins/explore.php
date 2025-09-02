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
     * Get latest explore data from YouTube Music.
     * The Top Songs chart is only returned when authenticated with a premium account.
     *
     * @return array Array containing new album releases, top songs (if authenticated with a premium account), moods & genres, popular episodes, trending tracks, and new music videos.
     */
    public function get_explore()
    {
        $body = ['browseId' => 'FEmusic_explore'];
        
        $response = $this->_send_request("browse", $body);
        $results = nav($response, [SINGLE_COLUMN_TAB, SECTION_LIST]);

        $explore = [];
        foreach ($results as $result) {
            $browse_id = nav($result, [CAROUSEL, CAROUSEL_TITLE, NAVIGATION_BROWSE_ID], true);
            if ($browse_id === null) {
                continue;
            }

            $contents = nav($result, [CAROUSEL_CONTENTS]);
            switch ($browse_id) {
                case "FEmusic_new_releases_albums":
                    $explore["new_releases"] = parse_content_list($contents, "Ytmusicapi\\parse_album");
                    break;
                case "FEmusic_moods_and_genres":
                    $explore["moods_and_genres"] = array_map(function($genre) {
                        return [
                            "title" => nav($genre, CATEGORY_TITLE),
                            "params" => nav($genre, CATEGORY_PARAMS)
                        ];
                    }, nav($result, [CAROUSEL_CONTENTS]));
                    break;
                case "FEmusic_top_non_music_audio_episodes":
                    $explore["top_episodes"] = parse_content_list($contents, fn ($item) => parse_chart_episode($item), MMRIR);
                    break;
                case "FEmusic_new_releases_videos":
                    $explore["new_videos"] = parse_content_list($contents, fn ($item) => parse_video($item), MTRIR);
                    break;
                default:
                    if (str_starts_with($browse_id, "VLPL")) {
                        $explore["top_songs"] = [
                            "playlist" => $browse_id,
                            "items" => parse_content_list($contents, fn ($item) => parse_chart_song($item), MRLIR)
                        ];
                    } else if (str_starts_with($browse_id, "VLOLA")) {
                        $explore["trending"] = [
                            "playlist" => $browse_id,
                            "items" => parse_content_list($contents, fn ($item) => parse_song_flat($item), MRLIR)
                        ];
                    }
            }
        }

        return $explore;
    }
}
