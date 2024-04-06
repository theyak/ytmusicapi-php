<?php

namespace Ytmusicapi;

use Ytmusicapi\Podcasts\Description;


trait Podcasts
{
    /**
     * Get information about a podcast channel (episodes, podcasts). For episodes, a
     * maximum of 10 episodes are returned, the full list of episodes can be retrieved
     * via `get_channel_episodes`
     *
     * @param string $channelId Channel id
     * @return object Channel
     */
    function get_channel($channelId)
    {
        $body = ["browseId" => $channelId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);

        $channel = [
            "title" => nav($response, join(HEADER_MUSIC_VISUAL, TITLE_TEXT)),
            "thumbnails" => nav($response, join(HEADER_MUSIC_VISUAL, THUMBNAILS)),
        ];

        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));
        $channel = array_merge($channel, $this->parse_channel_contents($results));

        return (object)$channel;
    }

    /**
     * Get all channel episodes. This endpoint is currently unlimited
     *
     * @param string $channelId ChannelId of the user
     * @param string $params Parameter obtained by `get_channel`
     * @return array list of channel episodes in the format of `get_channel` "episodes" key
     */
    function get_channel_episodes($channelId, $params)
    {
        $body = ["browseId" => $channelId, "params" => $params];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, GRID_ITEMS));
        return parse_content_list($results, "Ytmusicapi\\parse_episode", MMRIR);
    }

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
        $parse_func = fn($contents) => parse_content_list($contents, "Ytmusicapi\\parse_episode", MMRIR);
        $episodes = $parse_func($results->contents);

        if (!empty($results->continuations)) {
            $request_func = fn ($params) => $this->_send_request($endpoint, $body, $params);
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

    /**
     * Get all episodes in an episodes playlist. Currently the only known playlist is the
     * "New Episodes" auto-generated playlist
     *
     * @param string $playlist_id Playlist ID, defaults to "RDPN", the id of the New Episodes playlist
     * @return object Object in format of `get_podcast`
     */
    function get_episodes_playlist($playlist_id = "RDPN")
    {
        $browseId = str_starts_with($playlist_id, "VL") ? $playlist_id : "VL" . $playlist_id;
        $body = ["browseId" => $browseId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        $playlist = parse_playlist_header($response);

        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, MUSIC_SHELF));
        $parse_func = fn ($contents) => parse_content_list($contents, "Ytmusicapi\\parse_episode", MMRIR);
        $playlist->episodes = $parse_func($results->contents);

        return $playlist;
    }

}
