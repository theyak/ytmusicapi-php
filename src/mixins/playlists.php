<?php

// 388

namespace Ytmusicapi;

use ytmusicapi\Playlist;

trait Playlists
{
    /**
     * Returns information and tracks of a playlist.
     *
     * Known differences from Python version:
     *   - Returns an empty array instead of null for missing artists
     *   - Additional $get_continuations parameter for pagnating results
     *   - Liked music playlist is PRIVATE instead of PUBLIC
     *
     * @param string $playlistId Playlist ID
     * @param int $limit Maximum number of tracks to return (This isn't quite accurate as continuations can cause this to be exceded)
     * @param bool $related Whether to return related playlists
     * @param int $suggestions_limit Maximum number of suggestions to return
     * @param bool $get_continuations Whether to return continuations. When set to false, only the first 100 or so tracks
     *   will be returned, and a token will be provided in the continuation property to get the next set of tracks.
     *   (PHP Only, not in Python version. Setting this to false is useful for making multiple requests to get all
     *   tracks if you want to provide some sort of progress indicator, otherwise, leave it as true.)
     * @return Playlist
     */
    public function get_playlist($playlistId, $limit = 100, $related = false, $suggestions_limit = 0, $get_continuations = true)
    {
        $browseId = str_starts_with($playlistId, "VL") ? $playlistId : "VL" . $playlistId;
        $body = ["browseId" => $browseId, "params" => "wgYCCAE%3D"];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);

        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, "musicPlaylistShelfRenderer"));

        $playlist = (object)["id" => $results->playlistId];

        $own_playlist = !empty($response->header->musicEditablePlaylistDetailHeaderRenderer);
        if ($own_playlist) {
            $header = $response->header->musicEditablePlaylistDetailHeaderRenderer;
            $playlist->privacy = $header->editHeader->musicPlaylistEditHeaderRenderer->privacy;
            $header = $header->header->musicDetailHeaderRenderer;
        } else {
            $header = $response->header->musicDetailHeaderRenderer;
            $playlist->privacy = "PUBLIC";
        }

        // Mark playlist private if doing Liked music
        if ($playlist->id === "LM" || $playlist->id === "VLLM") {
            $playlist->privacy = "PRIVATE";
        }

        $playlist->title = nav($header, TITLE_TEXT);
        $playlist->thumbnails = nav($header, THUMBNAIL_CROPPED);
        $playlist->description = nav($header, DESCRIPTION, true);
        $run_count = count(nav($header, SUBTITLE_RUNS));
        if ($run_count > 1) {
            $playlist->author = (object)[
                "name" => nav($header, SUBTITLE2),
                "id" => nav($header, join(SUBTITLE_RUNS, 2, NAVIGATION_BROWSE_ID), true),
            ];
            if ($run_count == 5) {
                $playlist->year = nav($header, SUBTITLE3);
            }
        }

        $playlist->views = null;
        $playlist->duration = null;
        if (isset($header->secondSubtitle->runs)) {
            $second_subtitle_runs = $header->secondSubtitle->runs;
            $has_views = (count($second_subtitle_runs) > 3) * 2;
            $playlist->views = null;
            if ($has_views) {
                $playlist->views = (int)($second_subtitle_runs[0]->text);
            }
            $has_duration = (count($second_subtitle_runs) > 1) * 2;
            $playlist->duration = null;
            if ($has_duration) {
                $playlist->duration = $second_subtitle_runs[$has_views + $has_duration]->text;
            }
            $song_count = explode(" ", $second_subtitle_runs[$has_views]->text);
            $song_count = count($song_count) > 1 ? (int)($song_count[0]) : 0;
        } else {
            // Could not figure out how to get this response.
            // @codeCoverageIgnoreStart
            $song_count = count($results->contents);
            // @codeCoverageIgnoreEnd
        }

        // Track count is approximate. If tracks have been removed, they won't load,
        // but will still be included in this count, because YouTube is funny that way.
        $playlist->track_count = $song_count;

        // Load suggestions and related items.
        // Suggestions and related are not available on all playlists, e.g., liked music.
        $section_list = nav($response, join(SINGLE_COLUMN_TAB, "sectionListRenderer"));

        $playlist->related = [];
        $playlist->suggestions = [];

        $request_func = function ($additionalParams) use ($endpoint, $body) {
            return $this->_send_request($endpoint, $body, $additionalParams);
        };

        if (!empty($section_list->continuations)) {
            $additionalParams = get_continuation_params($section_list);
            if ($own_playlist && ($suggestions_limit > 0 || $related)) {
                $parse_func = function ($results) {
                    return parse_playlist_items($results);
                };
                $suggested = $this->_send_request($endpoint, $body, $additionalParams);
                $continuation = nav($suggested, SECTION_LIST_CONTINUATION);
                $additionalParams = get_continuation_params($continuation);
                $suggestions_shelf = nav($continuation, join(CONTENT, MUSIC_SHELF));
                $playlist->suggestions = get_continuation_contents($suggestions_shelf, $parse_func);
                $playlist->suggestions = array_merge(
                    $playlist->suggestions,
                    get_continuations(
                        $suggestions_shelf,
                        'musicShelfContinuation',
                        $suggestions_limit - count($playlist->suggestions),
                        $request_func,
                        $parse_func,
                        "",
                        true
                    )
                );
            }

            if ($related) {
                $response = $this->_send_request($endpoint, $body, $additionalParams);
                $continuation = nav($response, SECTION_LIST_CONTINUATION, true);
                if ($continuation) {
                    $parse_func = function ($results) {
                        return parse_content_list($results, function ($data) {
                            return parse_playlist($data);
                        });
                    };

                    $playlist->related = get_continuation_contents(
                        nav($continuation, join(CONTENT, CAROUSEL)),
                        $parse_func
                    );
                }
            }
        }

        $playlist->tracks = [];
        $playlist->continuation = null;

        if (isset($results->contents)) {
            $playlist->tracks = parse_playlist_items($results->contents);

            $parse_func = function ($contents) {
                return parse_playlist_items($contents);
            };

            if (isset($results->continuations)) {
                if ($get_continuations) {
                    $playlist->tracks = array_merge(
                        $playlist->tracks,
                        get_continuations(
                            $results,
                            'musicPlaylistShelfContinuation',
                            $limit,
                            $request_func,
                            $parse_func
                        )
                    );
                } else {
                    $playlist->continuation = nav($results, 'continuations.0.nextContinuationData.continuation', true);
                }
            }
        }

        $playlist->duration_seconds = sum_total_duration($playlist);

        return $playlist;
    }

    /**
     * Returns the next set of tracks in a playlist.
     *
     * Known differences from Python version:
     *   - Function not available in Python version
     *
     * @param string $playlistId Playlist ID
     * @param string $token Continuation token
     * @return PlaylistContinuation
     */
    public function get_playlist_continuation($playlistId, $token)
    {
        $additional = "&ctoken={$token}&continuation={$token}&type=next";
        $results = $this->_send_request("browse", [], $additional);

        $continuation = nav($results, 'continuationContents.musicPlaylistShelfContinuation.continuations.0.nextContinuationData.continuation', true);
        $contents = nav($results, 'continuationContents.musicPlaylistShelfContinuation.contents', true);
        $tracks = parse_playlist_items($contents);

        return (object)[
            "id" => $playlistId,
            "tracks" => $tracks,
            "continuation" => $continuation,
        ];
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
     * Gets playlist items of saved podcast episodes
     *
     * @param int $limit How many items to return. Default: 100
     * @return Playlist List of playlistItem dictionaries. Same format as `get_playlist`
     */
    public function get_saved_episodes($limit = 100)
    {
        return $this->get_playlist('SE', $limit);
    }

    /**
     * Creates a new empty playlist and returns its id.
     *
     * Known differences from Python version:
     *  - Throws exceptions with some common errors because there is no return response on error.
     *
     * @param string $title Playlist title
     * @param string $description Playlist description
     * @param string $privacy_status Playlists can be 'PUBLIC', 'PRIVATE', or 'UNLISTED'. Default: 'PRIVATE'
     * @param array $video_ids IDs of songs to create the playlist with
     * @param string $source_playlist Another playlist whose songs should be added to the new playlist
     * @return string|object ID of the YouTube playlist or full response if there was an error
     */
    public function create_playlist($title, $description, $privacy_status = "PRIVATE", $video_ids = null, $source_playlist = null)
    {
        $this->_check_auth();

        if ($video_ids && $source_playlist) {
            throw new \Exception("You can't specify both video_ids and source_playlist");
        }

        if (!in_array($privacy_status, ["PUBLIC", "PRIVATE", "UNLISTED"])) {
            throw new \Exception("Invalid privacy status, must be one of PUBLIC, PRIVATE, or UNLISTED");
        }

        $body = [
            "title" => $title,
            "description" => html_to_txt($description),
            "privacyStatus" => $privacy_status,
        ];

        if ($video_ids) {
            $body["videoIds"] = $video_ids;
        }

        if ($source_playlist) {
            $body["sourcePlaylistId"] = $source_playlist;
        }

        $response = $this->_send_request("playlist/create", $body);

        if (!empty($response->playlistId)) {
            return $response->playlistId;
        }

        if (!$response) {
            throw new \Exception("Failed to create playlist");
        }

        return $response;
    }

    /**
     * Edit title, description or privacyStatus of a playlist.
     * You may also move an item within a playlist or append another playlist to this playlist.
     *
     * Known differences from Python version:
     *  - Does a check for valid privacy status.
     *
     * @param string $playlistId Playlist id
     * @param string $title Optional. New title for the playlist
     * @param string $description Optional. New description for the playlist
     * @param string $privacyStatus Optional. New privacy status for the playlist
     * @param array $moveItem Optional. Move one item before another. Items are specified by setVideoId
     * @param string $addPlaylistId Optional. Id of another playlist to add to this playlist
     * @param bool $addToTop Optional. Change the state of this playlist to add items to the top of the playlist (if true)
     *  or the bottom of the playlist (if false - this is also the default of a new playlist).
     * @return string Status String or full response
     */
    public function edit_playlist($playlistId, $title = null, $description = null, $privacyStatus = null, $moveItem = null, $addPlaylistId = null, $addToTop = null)
    {
        $this->_check_auth();
        $body = ['playlistId' => validate_playlist_id($playlistId)];
        $actions = [];

        if ($title) {
            $actions[] = ['action' => 'ACTION_SET_PLAYLIST_NAME', 'playlistName' => $title];
        }

        if ($description) {
            $actions[] = [
                'action' => 'ACTION_SET_PLAYLIST_DESCRIPTION',
                'playlistDescription' => $description
            ];
        }

        if ($privacyStatus) {
            if (!in_array($privacyStatus, ["PUBLIC", "PRIVATE", "UNLISTED"])) {
                throw new \Exception("Invalid privacy status, must be one of PUBLIC, PRIVATE, or UNLISTED");
            }

            $actions[] = [
                'action' => 'ACTION_SET_PLAYLIST_PRIVACY',
                'playlistPrivacy' => $privacyStatus
            ];
        }

        if ($moveItem) {
            $actions[] = [
                'action' => 'ACTION_MOVE_VIDEO_BEFORE',
                'setVideoId' => $moveItem[0],
                'movedSetVideoIdSuccessor' => $moveItem[1]
            ];
        }

        if ($addPlaylistId) {
            $actions[] = [
                'action' => 'ACTION_ADD_PLAYLIST',
                'addedFullListId' => $addPlaylistId
            ];
        }

        if ($addToTop === false) {
            $actions[] = ['action' => 'ACTION_SET_ADD_TO_TOP', 'addToTop' => 'false'];
        } elseif ($addToTop === true) {
            $actions[] = ['action' => 'ACTION_SET_ADD_TO_TOP', 'addToTop' => 'true'];
        }

        $body['actions'] = $actions;
        $endpoint = 'browse/edit_playlist';
        $response = $this->_send_request($endpoint, $body);
        return $response->status ?? $response;
    }

    /**
     * Delete a playlist.
     *
     * @param string $playlistId Playlist id
     * @return string|object Status String or full response
     */
    public function delete_playlist($playlistId)
    {
        $this->_check_auth();
        $body = ['playlistId' => validate_playlist_id($playlistId)];
        $endpoint = 'playlist/delete';
        $response = $this->_send_request($endpoint, $body);

        return empty($response->status) ? $response : $response->status;
    }

    /**
     * Add songs to an existing playlist.
     *
     * @param string $playlistId Playlist id
     * @param string|array $videoIds List of Video ids
     * @param string $source_playlist Playlist id of a playlist to add to the current playlist (no duplicate check)
     * @param bool $duplicates If true, duplicates will be added. If false, an error will be returned if there are duplicates (no items are added to the playlist)
     * @return string|object Status String and a dict containing the new setVideoId for each videoId or full response
     */
    public function add_playlist_items($playlistId, $videoIds = null, $source_playlist = null, $duplicates = false)
    {
        $this->_check_auth();

        $body = [
            'playlistId' => validate_playlist_id($playlistId),
            'actions' => [],
        ];

        if (!$videoIds && !$source_playlist) {
            throw new \Exception("You must provide either videoIds or a source_playlist to add to the playlist");
        }

        if ($videoIds) {
            foreach ($videoIds as $videoId) {
                $action = ['action' => 'ACTION_ADD_VIDEO', 'addedVideoId' => $videoId];
                if ($duplicates) {
                    $action['dedupeOption'] = 'DEDUPE_OPTION_SKIP';
                }
                $body['actions'][] = $action;
            }
        }

        if ($source_playlist) {
            $body['actions'][] = [
                'action' => 'ACTION_ADD_PLAYLIST',
                'addedFullListId' => $source_playlist
            ];

            // add an empty ACTION_ADD_VIDEO because otherwise
            // YTM doesn't return the object that maps videoIds to their new setVideoIds
            if (!$videoIds) {
                $body['actions'][] = ['action' => 'ACTION_ADD_VIDEO', 'addedVideoId' => null];
            }
        }

        $endpoint = 'browse/edit_playlist';
        $response = $this->_send_request($endpoint, $body);

        if (!empty($response->status) && $response->status === "STATUS_SUCCEEDED") {
            $result_dict = [];
            foreach ($response->playlistEditResults as $result_data) {
                $result_dict[] = $result_data->playlistEditVideoAddedResultData;
            }
            return (object)["status" => $response->status, "playlistEditResults" => $result_dict];
        }

        return $response;
    }

    /**
     * Remove songs from an existing playlist.
     *
     * @param string $playlistId Playlist id
     * @param Track[] $videos List of Tracks or Track like objects. Must contain videoId and setVideoId
     * @return string|object Status String or full response
     */
    public function remove_playlist_items($playlistId, $videos)
    {
        $this->_check_auth();

        $videos = array_filter($videos, function ($x) {
            return !empty($x->videoId) && !empty($x->setVideoId);
        });

        if (empty($videos)) {
            throw new \Exception("Cannot remove songs, because setVideoId is missing. Do you own this playlist?");
        }

        $body = [
            'playlistId' => validate_playlist_id($playlistId),
            'actions' => []
        ];

        foreach ($videos as $video) {
            $body['actions'][] = [
                'setVideoId' => is_array($video) ? $video['setVideoId'] : $video->setVideoId,
                'removedVideoId' => is_array($video) ? $video['videoId'] : $video->videoId,
                'action' => 'ACTION_REMOVE_VIDEO'
            ];
        }

        $endpoint = 'browse/edit_playlist';
        $response = $this->_send_request($endpoint, $body);

        if (!empty($response->status)) {
            return $response->status;
        }

        return $response;
    }
}
