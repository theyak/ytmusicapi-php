<?php

namespace Ytmusicapi;

trait I18n
{
    public function get_search_result_types()
    {
        return [
            $this->_('artist'),
            $this->_('playlist'),
            $this->_('song'),
            $this->_('video'),
            $this->_('station'),
            $this->_('profile'),
            $this->_('podcast'),
            $this->_('episode'),
        ];
    }

    /**
     * Get data related to various categories of an artists.
     */
    function parse_channel_contents($results)
    {

        file_put_contents("channel_contents.json", json_encode($results, JSON_PRETTY_PRINT));
        $categories = [
            ["albums", $this->_("albums"), "Ytmusicapi\parse_album", MTRIR],
            ["singles", $this->_("singles"), "Ytmusicapi\parse_single", MTRIR],
            ["videos", $this->_("videos"), "Ytmusicapi\parse_video", MTRIR],
            ["playlists", $this->_("playlists"), "Ytmusicapi\parse_playlist", MTRIR],
            ["related", $this->_("related"), "Ytmusicapi\parse_related_artist", MTRIR],
            ["episodes", $this->_("episodes"), "Ytmusicapi\parse_episode", MMRIR],
            ["podcasts", $this->_("podcasts"), "Ytmusicapi\parse_podcast", MTRIR],
        ];

        $artist = [];

        foreach ($categories as $category) {
            [$category, $category_local, $category_parser, $category_key] = $category;
            $artist[$category] = null;

            // Find the shelf for the category.
            $data = array_filter($results, function ($r) use ($category_local) {
                if (empty($r->musicCarouselShelfRenderer)) {
                    return false;
                }

                $title = nav($r, join(CAROUSEL, CAROUSEL_TITLE), true);
                if ($title && mb_strtolower($title->text) === mb_strtolower($category_local)) {
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

                $artist[$category]->params = nav($title, 'navigationEndpoint.browseEndpoint.params', true);

                $artist[$category]->results = parse_content_list(
                    $data->contents, $category_parser, $category_key
                );
            }
        }

        return $artist;
    }
}
