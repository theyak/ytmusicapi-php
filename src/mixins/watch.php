<?php

namespace Ytmusicapi;

trait Watch
{
    /**
     * Get a watch list of tracks. This watch playlist appears when you press
     * play on a track in YouTube Music. YouTube Music calls a similar function
     * each time a song gets played.
     *
     * Please note that the `INDIFFERENT` likeStatus of tracks returned by this
     * endpoint may be either `INDIFFERENT` or `DISLIKE`, due to ambiguous data
     * returned by YouTube Music.
     *
     * Known differences from Python version:
     *   - isExplicit is defined in tracks
     *
     * @param string $videoId videoId of the played video
     * @param string $playlistId playlistId of the played playlist or album
     * @param int $limit minimum number of watch playlist items to return
     * @param bool $radio get a radio playlist (changes each time)
     * @param bool $shuffle shuffle the input playlist. only works when the playlistId parameter
     *   is set at the same time. does not work if radio=True
     * @return WatchList List of watch playlist items. The counterpart key is optional and only
     *   appears if a song has a corresponding video counterpart (UI song/video
     *   switcher).
     */
    public function get_watch_playlist($videoId = null, $playlistId = null, $limit = 25, $radio = false, $shuffle = false)
    {
        $body = [
            "enablePersistentPlaylistPanel" => true,
            "isAudioOnly" => true,
            "tunerSettingValue" => "AUTOMIX_SETTING_NORMAL"
        ];

        if (!$videoId && !$playlistId) {
            throw new \Exception("You must provide either a video id, a playlist id, or both");
        }

        if ($videoId) {
            $body["videoId"] = $videoId;
            if (!$playlistId) {
                $playlistId = "RDAMVM" . $videoId;
            }

            if (!$radio && !$shuffle) {
                $body["watchEndpointMusicSupportedConfigs"] = [
                    "watchEndpointMusicConfig" => [
                        "hasPersistentPlaylistPanel" => true,
                        "musicVideoType" => "MUSIC_VIDEO_TYPE_ATV"
                    ]
                ];
            }
        }

        $body["playlistId"] = validate_playlist_id($playlistId);
        $is_playlist = substr($body["playlistId"], 0, 2) === "PL" || substr($body["playlistId"], 0, 3) === "OLA";
        if ($shuffle && $playlistId) {
            $body["params"] = "wAEB8gECKAE%3D";
        }
        if ($radio) {
            $body["params"] = "wAEB";
        }

        $endpoint = "next";
        $response = $this->_send_request($endpoint, $body);

        $watchNextRenderer = nav($response, "contents.singleColumnMusicWatchNextResultsRenderer.tabbedRenderer.watchNextTabbedResultsRenderer");

        $lyrics_browse_id = get_tab_browse_id($watchNextRenderer, 1);
        $related_browse_id = get_tab_browse_id($watchNextRenderer, 2);

        $results = nav($watchNextRenderer, join(TAB_CONTENT, "musicQueueRenderer.content.playlistPanelRenderer"));

        $playlist = "";
        foreach ($results->contents as $item) {
            $playlistId = nav($item, ['playlistPanelVideoRenderer', 'navigationEndpoint', 'watchEndpoint', 'playlistId'], true);
            if ($playlistId) {
                $playlist = $playlistId;
                break;
            }
        }

        $tracks = watch_playlist_parser($results->contents);

        if (isset($results->continuations)) {
            $request_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };

            $parse_func = function ($contents) {
                return watch_playlist_parser($contents);
            };

            $continuations = get_continuations($results, "playlistPanelContinuation", $limit - count($tracks), $request_func, $parse_func, $is_playlist ? "" : "Radio");
            $tracks = array_merge($tracks, $continuations);
        }

        return (object)[
            "tracks" => $tracks,
            "playlistId" => $playlist,
            "lyrics" => $lyrics_browse_id,
            "related" => $related_browse_id
        ];
    }

    /**
     * Get track information, including IDs to retrieve lyrics and related tracks.
     *
     * Known differences from Python version:
     *   - Function not available in Python version
     *
     * @param string $videoId Video ID
     * @return WatchTrack
     */
    public function get_track($videoId)
    {
        $result = $this->get_watch_playlist($videoId);
        $item = reset($result->tracks);

        $track = WatchTrack::from($item);

        // Additional fields available from a regular Track object
        $track->playlistId = $result->playlistId;
        $track->lyrics = $result->lyrics;
        $track->related = $result->related;
        $track->isAvailable = true;

        return $track;
    }
}
