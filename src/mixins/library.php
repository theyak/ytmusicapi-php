<?php

namespace Ytmusicapi;

trait Library
{
    /**
     * Retrieves the playlists in the user's library.
     *
     * @param int $limit Number of playlists to retrieve. `null` retrieves them all.
     * @return PlaylistInfo[] List of owned playlists.
     */
    public function get_library_playlists($limit = 25)
    {
        $this->_check_auth();
        $body = ['browseId' => 'FEmusic_liked_playlists'];
        $endpoint = 'browse';
        $response = $this->_send_request($endpoint, $body);

        $results = get_library_contents($response, GRID);
        $playlists = parse_content_list(array_slice($results->items, 1), 'Ytmusicapi\\parse_playlist');

        if (isset($results->continuations)) {
            $request_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };
            $parse_func = function ($contents) {
                return parse_content_list($contents, 'Ytmusicapi\\parse_playlist');
            };
            $remaining_limit = $limit === null ? null : ($limit - count($playlists));
            $playlists = array_merge($playlists, get_continuations($results, 'gridContinuation', $remaining_limit, $request_func, $parse_func));
        }

        return $playlists;
    }

    /**
     * Gets the songs in the user's library (liked videos are not included).
     * To get liked songs and videos, use `get_liked_songs`
     *
     * Known differences from Python version:
     *   - Always marks inLibrary as true. This is because the inLibrary property is not set when it's a "single" album in the list
     *
     * @param int $limit Number of songs to retrieve
     * @param bool $validate_responses Flag indicating if responses from YTM should be validated and retried in case
     * when some songs are missing. Default: False
     * @param string $order Order of songs to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return Track[] List of songs. Same format as `get_playlist`
     */
    public function get_library_songs($limit = 25, $validate_responses = false, $order = null)
    {
        $this->_check_auth();
        $body = ['browseId' => 'FEmusic_liked_videos'];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }
        $endpoint = 'browse';
        $per_page = 25;

        $request_func = function ($additionalParams) use ($endpoint, $body) {
            return $this->_send_request($endpoint, $body);
        };

        $parse_func = function ($raw_response) {
            return parse_library_songs($raw_response);
        };

        if ($validate_responses && $limit === null) {
            throw new \Exception("Validation is not supported without a limit parameter.");
        }

        if ($validate_responses) {
            $validate_func = function ($parsed) use ($per_page, $limit) {
                return validate_response($parsed, $per_page, $limit, 0);
            };
            $response = resend_request_until_parsed_response_is_valid($request_func, null, $parse_func, $validate_func, 3);
        } else {
            $response = $parse_func($request_func(null));
        }

        $results = $response->results;
        $songs = $response->parsed;

        if (empty($songs) === null) {
            return [];
        }

        if (isset($results->continuations)) {
            $request_continuations_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };
            $parse_continuations_func = function ($contents) {
                return parse_playlist_items($contents);
            };

            if ($validate_responses) {
                $songs = array_merge(
                    $songs,
                    get_validated_continuations(
                        $results,
                        'musicShelfContinuation',
                        $limit - count($songs),
                        $per_page,
                        $request_continuations_func,
                        $parse_continuations_func
                    )
                );
            } else {
                $remaining_limit = $limit === null ? null : ($limit - count($songs));
                $songs = array_merge(
                    $songs,
                    get_continuations(
                        $results,
                        'musicShelfContinuation',
                        $remaining_limit,
                        $request_continuations_func,
                        $parse_continuations_func
                    )
                );
            }
        }

        $songs = array_map(function ($song) {
            $song->inLibrary = true;
            return $song;
        }, $songs);

        return $songs;
    }

    /**
     * Gets the albums in the user's library.
     *
     * @param int $limit Number of albums to return
     * @param string $order Order of albums to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return AlbumInfo[] List of albums.
     */
    public function get_library_albums($limit = 25, $order = null)
    {
        $this->_check_auth();
        $body = ['browseId' => 'FEmusic_liked_albums'];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }

        $endpoint = 'browse';
        $response = $this->_send_request($endpoint, $body);

        $parse_func = function ($additionalParams) use ($endpoint, $body) {
            return $this->_send_request($endpoint, $body, $additionalParams);
        };

        return parse_library_albums($response, $parse_func, $limit);
    }

    /**
     * Gets the artists of the songs in the user's library.
     *
     * @param int $limit Number of artists to return
     * @param string $order Order of artists to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return ArtistInfo[] List of artists.
     */
    public function get_library_artists($limit = 25, $order = null)
    {
        $this->_check_auth();
        $body = ['browseId' => 'FEmusic_library_corpus_track_artists'];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }
        $endpoint = 'browse';
        $response = $this->_send_request($endpoint, $body);
        return parse_library_artists(
            $response,
            function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            },
            $limit,
            "artists"
        );
    }

    /**
     * Gets the artists the user has subscribed to.
     *
     * @param int $limit Number of artists to return
     * @param string $order Order of artists to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return array List of artists. Same format as `get_library_artists`
     */
    public function get_library_subscriptions($limit = 25, $order = null)
    {
        $this->_check_auth();
        $body = ['browseId' => 'FEmusic_library_corpus_artists'];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }
        $endpoint = 'browse';
        $response = $this->_send_request($endpoint, $body);
        return parse_library_artists(
            $response,
            function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            },
            $limit,
            "subscriptions"
        );
    }

    /**
     * Gets playlist items for the 'Liked Songs' playlist
     *
     * @param int $limit How many items to return. Default: 100
     * @return Playlist List of playlistItem dictionaries. Same format as `get_playlist`
     */
    public function get_liked_songs($limit = 100)
    {
        return $this->get_playlist('LM', $limit);
    }

    /**
     * Gets your play history in reverse chronological order
     *
     * @return HistoryTrack[]
     */
    public function get_history()
    {
        $this->_check_auth();
        $body = ['browseId' => 'FEmusic_history'];
        $endpoint = 'browse';
        $response = $this->_send_request($endpoint, $body);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));
        $songs = [];
        foreach ($results as $content) {
            $data = nav($content, join(MUSIC_SHELF, 'contents'), true);
            if (!$data) {
                $error = nav($content, join('musicNotifierShelfRenderer', TITLE), true);
                throw new \Exception($error ?? "Error reading history");
            }
            $menu_entries = [join("-1", MENU_SERVICE, FEEDBACK_TOKEN)];
            $songlist = parse_playlist_items($data, $menu_entries);
            foreach ($songlist as &$song) {
                $song->played = nav($content->musicShelfRenderer, TITLE_TEXT);
            }
            unset($song);
            $songs = array_merge($songs, $songlist);
        }

        $songs = array_map(function ($song) {
            return HistoryTrack::from($song);
        }, $songs);

        return $songs;
    }

    /**
     * Add an item to the account's history using the playbackTracking URI
     * obtained from `get_song`.
     *
     * Known differences from Python version:
     *   - Can pass in a video id instead of a Song object
     *
     * @param Song|string $song Song as returned by `get_song` or videoId
     * @return object Full response. response.status_code is 204 if successful
     */
    public function add_history_item($song)
    {
        if (is_string($song)) {
            $song = $this->get_song($song);
        }
        $url = $song->playbackTracking->videostatsPlaybackUrl->baseUrl;

        $cpn = "";
        $CPNA = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_";
        for ($i = 0; $i < 16; $i++) {
            $cpn .= $CPNA[rand(0, 256) & 63];
        }

        $params = ['ver' => 2, 'c' => 'WEB_REMIX', 'cpn' => $cpn];
        return $this->_send_get_request($url, $params);
    }

    /**
     * Removes an item from the account's history. This method does not currently work with brand accounts
     *
     * Known differences from Python version:
     *   - Can pass in a single feedback token in addition to an array of tokens.
     *
     * @param string[]|string $feedbackTokens Token to identify the item to remove, obtained from get_history
     * @return object Full response from YouTube Music
     */
    public function remove_history_items($feedbackTokens)
    {
        if (is_string($feedbackTokens)) {
            $feedbackTokens = [$feedbackTokens];
        }

        $this->_check_auth();
        $body = ['feedbackTokens' => $feedbackTokens];
        $endpoint = 'feedback';
        return $this->_send_request($endpoint, $body);
    }

    /**
     * Rates a song ("thumbs up"/"thumbs down" interactions on YouTube Music)
     *
     * @param string $videoId Video id
     * @param string $rating One of 'LIKE', 'DISLIKE', 'INDIFFERENT'
     *  'INDIFFERENT' removes the previous rating and assigns no rating
     * @return object Full response from YouTube Music
     */
    public function rate_song($videoId, $rating = "INDIFFERENT")
    {
        $this->_check_auth();
        $body = ['target' => ['videoId' => $videoId]];
        $endpoint = prepare_like_endpoint($rating);
        return $endpoint ? $this->_send_request($endpoint, $body) : null;
    }

    /**
     * Adds or removes a song from your library depending on the token provided.
     *
     * Known differences from Python version:
     *   - Can pass in a single feedback token in addition to an array of tokens.
     *
     * @param string[]|string $feedbackTokens List of feedbackTokens obtained from authenticated requests
     *    to endpoints that return songs (i.e. get_album)
     * @return object Full response from YouTube Music
     */
    public function edit_song_library_status($feedbackTokens)
    {
        if (is_string($feedbackTokens)) {
            $feedbackTokens = [$feedbackTokens];
        }

        $this->_check_auth();
        $body = ['feedbackTokens' => $feedbackTokens];
        $endpoint = 'feedback';
        return $this->_send_request($endpoint, $body);
    }

    /**
     * Rates a playlist/album ("Add to library"/"Remove from library" interactions on YouTube Music)
     * You can also dislike a playlist/album, which has an effect on your recommendations
     *
     * @param string $playlistId Playlist id
     * @param string $rating One of 'LIKE', 'DISLIKE', 'INDIFFERENT'
     *   'INDIFFERENT' removes the playlist/album from the library
     * @return object Full response from YouTube Music
     */
    public function rate_playlist($playlistId, $rating = "INDIFFERENT")
    {
        $this->_check_auth();
        $body = ['target' => ['playlistId' => $playlistId]];
        $endpoint = prepare_like_endpoint($rating);
        return $endpoint ? $this->_send_request($endpoint, $body) : null;
    }

    /**
     * Subscribe to artists. Adds the artists to your library
     *
     * Known differences from Python version:
     *   - Can pass in a single feedback token in addition to an array of tokens.
     *
     * @param string[]|string $channelIds Artist channel ids
     * @return obect Full response from YouTube Music
     */
    public function subscribe_artists($channelIds)
    {
        if (is_string($channelIds)) {
            $channelIds = [$channelIds];
        }

        $this->_check_auth();
        $body = ['channelIds' => $channelIds];
        $endpoint = 'subscription/subscribe';
        return $this->_send_request($endpoint, $body);
    }

    /**
     * Unsubscribe from artists. Removes the artists from your library.
     *
     * Known differences from Python version:
     *   - Can pass in a single channel ID addition to an array of channel IDs.
     *
     * @param string[]|string $channelIds Artist channel ids
     * @return obect Full response from YouTube Music
     */
    public function unsubscribe_artists($channelIds)
    {
        if (is_string($channelIds)) {
            $channelIds = [$channelIds];
        }

        $this->_check_auth();
        $body = ['channelIds' => $channelIds];
        $endpoint = 'subscription/unsubscribe';
        return $this->_send_request($endpoint, $body);
    }
}
