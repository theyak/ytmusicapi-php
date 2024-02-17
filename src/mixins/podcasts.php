<?php

namespace Ytmusicapi;

use Ytmusicapi\Podcasts\Description;

use function Ytmusicapi\Podcasts\parse_episodes;
use function Ytmusicapi\Podcasts\parse_podcast_header;
use function Ytmusicapi\Podcasts\parse_episode_header;

trait Podcasts
{
    /**
     * Returns podcast metadata and episodes
     * .. note::
     *   To add a podcast to your library, you need to call `rate_playlist` on it
     *
     * @param string $playlistId Playlist id
     * @param int $limit How many songs to return. `null` retrieves them all. Default: 100
     * @return Podcast
     */
    public function get_podcast($playlistId, $limit = 100)
    {
        $browseId = strpos($playlistId, "MPSP") === 0 ? $playlistId : "MPSP" . $playlistId;
        $body = ["browseId" => $browseId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        $two_columns = nav($response, TWO_COLUMN_RENDERER);
        $header = nav($two_columns, join(TAB_CONTENT, SECTION_LIST_ITEM, RESPONSIVE_HEADER));
        $podcast = parse_podcast_header($header);

        $results = nav($two_columns, join("secondaryContents", SECTION_LIST_ITEM, MUSIC_SHELF));
        $episodes = parse_episodes($results->contents);

        if (!empty($results->continuations)) {
            $request_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };
            $parse_func = function ($contents) {
                return parse_episodes($contents);
            };
            $remaining_limit = is_null($limit) ? null : ($limit - count($episodes));
            $episodes = array_merge($episodes, get_continuations(
                $results,
                "musicShelfContinuation",
                $remaining_limit,
                $request_func,
                $parse_func
            ));
        }

        $podcast->episodes = $episodes;
        $podcast = object_merge(new Podcast(), $podcast);

        return $podcast;
    }

    /**
     * Retrieve episode data for a single episode
     * .. note::
     *     To save an episode, you need to call `add_playlist_items` to add
     *     it to the `SE` (saved episodes) playlist.
     *
     * @param string $videoId browseId (MPED..) or videoId for a single episode
     * @return Episode
     */
    public function get_episode($videoId)
    {
        $browseId = strpos($videoId, "MPED") === 0 ? $videoId : "MPED" . $videoId;
        $body = ["browseId" => $browseId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);

        $two_columns = nav($response, TWO_COLUMN_RENDERER);
        $header = nav($two_columns, join(TAB_CONTENT, SECTION_LIST_ITEM, RESPONSIVE_HEADER));

        $episode = parse_episode_header($header);

        $description_runs = nav(
            $two_columns,
            join("secondaryContents", SECTION_LIST_ITEM, DESCRIPTION_SHELF, "description", "runs")
        );
        $episode->description = Description::from_runs($description_runs);

        $episode = object_merge(new Episode(), $episode);

        return $episode;
    }
}
