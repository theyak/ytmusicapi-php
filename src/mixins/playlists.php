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
     *   - Additional $get_continuations parameter for paginating results
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
    public function get_playlist(
        $playlistId,
        $limit = 100,
        $related = false,
        $suggestions_limit = 0,
        $get_continuations = true
    ) {
        $browseId = str_starts_with($playlistId, "VL") ? $playlistId : "VL" . $playlistId;
        $body = ["browseId" => $browseId, "params" => "wgYCCAE%3D"];
        $endpoint = "browse";
        $request_func = fn ($additionalParams) => $this->_send_request($endpoint, $body, $additionalParams);
        $response = $request_func("");

        $request_func_continuations = fn ($body) => $this->_send_request($endpoint, $body);
        $is_ola = str_starts_with($playlistId, "OLA") || str_starts_with($playlistId, "VLOLA");
        $has_playlist_header = nav($response, join(TWO_COLUMN_RENDERER, TAB_CONTENT, SECTION_LIST_ITEM), true);
        if ($is_ola && !$has_playlist_header) {
            return (object)parse_audio_playlist($response, $limit, $request_func_continuations);
        }

        $header_data = nav($response, join(TWO_COLUMN_RENDERER, TAB_CONTENT, SECTION_LIST_ITEM));
        $section_list = nav($response, join(TWO_COLUMN_RENDERER, "secondaryContents", SECTION));
        $playlist = [];

        $playlist["owned"] = !empty($header_data->musicEditablePlaylistDetailHeaderRenderer->editHeader);

        if (!$playlist["owned"]) {
            $header = nav($header_data, RESPONSIVE_HEADER);
            $playlist["id"] = nav(
                $header,
                join("buttons", 1, "musicPlayButtonRenderer", "playNavigationEndpoint", WATCH_PLAYLIST_ID),
                true
            );
            $playlist["privacy"] = "PUBLIC";
        } else {
            $playlist["id"] = nav($header_data, join(EDITABLE_PLAYLIST_DETAIL_HEADER, PLAYLIST_ID));
            $header = nav($header_data, join(EDITABLE_PLAYLIST_DETAIL_HEADER, HEADER, RESPONSIVE_HEADER));
            $playlist["privacy"] = nav($header_data, join(EDITABLE_PLAYLIST_DETAIL_HEADER, "editHeader", "musicPlaylistEditHeaderRenderer", "privacy"), true);
        }

        $description_shelf = nav($header, join("description", DESCRIPTION_SHELF), true);
        $playlist["description"] = $description_shelf
            ? implode("", array_column($description_shelf->description->runs, "text"))
            : null;

        $playlist = array_merge($playlist, parse_playlist_header_meta($header));
        $is_collaborative = array_key_exists("collaborators", $playlist);

        $slice_index = 2 + (int)$playlist["owned"] * 2;
        $runs = array_slice(nav($header, SUBTITLE_RUNS), $slice_index);
        $playlist = array_merge($playlist, parse_song_runs($runs));

        $request_func = function($additionalParams) use ($endpoint, $body) {
            return $this->_send_request($endpoint, $body, $additionalParams);
        };

        $playlist["related"] = [];
        if (isset($section_list->continuations) && $get_continuations) {
            $additionalParams = get_continuation_params($section_list);

            if ($playlist["owned"] && ($suggestions_limit > 0 || $related)) {
                $parse_func = fn ($results) => parse_playlist_items($results);
                $suggested = $request_func($additionalParams);
                $continuation = nav($suggested, SECTION_LIST_CONTINUATION);
                $additionalParams = get_continuation_params($continuation);
                $suggestions_shelf = nav($continuation, join(CONTENT, MUSIC_SHELF));
                $playlist["suggestions"] = get_continuation_contents($suggestions_shelf, $parse_func);

                $playlist["suggestions"] = array_merge(
                    $playlist["suggestions"],
                    get_reloadable_continuations(
                        $suggestions_shelf,
                        "musicShelfContinuation",
                        $suggestions_limit - count($playlist["suggestions"]),
                        $request_func,
                        $parse_func,
                    )
                );
            }

            if ($related) {
                $response = $request_func($additionalParams);
                $continuation = nav($response, SECTION_LIST_CONTINUATION, true);
                if ($continuation) {
                    $parse_func = function($results) {
                        return parse_content_list($results, 'Ytmusicapi\\parse_playlist');
                    };
                    $playlist["related"] = get_continuation_contents(
                        nav($continuation, join(CONTENT, CAROUSEL)),
                        $parse_func
                    );
                }
            }
        }

        $playlist["tracks"] = [];
        $content_data = nav($section_list, join(CONTENT, "musicPlaylistShelfRenderer"));
        if (isset($content_data->contents)) {
            $playlist["tracks"] = parse_playlist_items(
                $content_data->contents, is_collaborative: $is_collaborative
            );

            if ($get_continuations) {
                $parse_func = fn ($contents) => parse_playlist_items($contents, is_collaborative: $is_collaborative);

                $playlist["tracks"] = array_merge(
                    $playlist["tracks"],
                    get_continuations_2025($content_data, $limit, $request_func_continuations, $parse_func)
                );
            } else {
                $playlist['continuation'] = get_continuation_token($content_data->contents);
            }
        }

        if ($playlistId === "LM") {
            $playlist["privacy"] = "PRIVATE";
        }

        $playlist = (object)$playlist;
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

        $contents = nav($results, 'onResponseReceivedActions.0.appendContinuationItemsAction.continuationItems', true);
        $continuation = get_continuation_token($contents);
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
        $this->_check_auth();
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

        $invalid_characters = ["<", ">"]; // ytmusic will crash if these are part of the title
        if (array_filter($invalid_characters, fn ($invalid) => str_contains($title, $invalid))) {
            $msg = sprintf("%s contains invalid characters: %s", $title, implode(", ", $invalid_characters));
            throw new YTMusicUserError($msg);
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

        if (isset($response->playlistId)) {
            $playlist_id = $response->playlistId;
            return $playlist_id;
        }

        validate_write_response($response);

        return $response;
    }

    /**
     * Given an invite token, join a collaborative playlist and add it to your library.
     *
     * @param string $playlistId ID of the playlist to join.
     *     If you're already a collaborator, an Unauthorized server error is raised.
     * @param string $joinCollaborationToken See validate_playlist_id(), or `jct` in YTM's invite URL.
     * @return string|array Status String or full response
     */
    public function join_collaborative_playlist(
        string $playlistId,
        string $joinCollaborationToken
    ): string|array {
        $this->_check_auth();

        $body = [
            "playlistId" => validate_playlist_id($playlistId),
            "actions" => [
                [
                    "action" => "ACTION_JOIN_COLLABORATION",
                    "joinCollaborationToken" => $joinCollaborationToken,
                ],
            ],
        ];

        $endpoint = "browse/edit_playlist";
        $response = $this->_send_request($endpoint, $body);

        return empty($response->status) ? $response : $response->status;
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
     * @param array $moveItem  Optional. Move one item before another. Items are specified by setVideoId, which is the
     *     unique id of this playlist item. See `get_playlist`
     * @param string $addPlaylistId Optional. Id of another playlist to add to this playlist
     * @param bool $addToTop Optional. Change the state of this playlist to add items to the top of the playlist (if true)
     *  or the bottom of the playlist (if false - this is also the default of a new playlist).
     * @param bool $collaboration. Optional. Enable or disable collaboration.
     *     If false and collaboration is not enabled, a Forbidden server error is raised.
     *     If true, a new `joinCollaborationToken` is returned.
     *     Collaborators cannot interact with private playlists.
     * @param string $sortOrder Optional. Change the order tracks are returned in. The default is `MANUAL`.
     * @param PlaylistVoteEditOptions Optional. Change who can participate in community voting in this playlist.
     *     Note that a bad request will be thrown if voteOption is PlaylistVoteEditOptions.COLLABORATORS_ONLY
     *     but the playlist is not enabled for collaboration prior to the edit.
     * @return object|string Status String, `collaboration` dict described below, or full response
     *
     * Object returned when `collaboration` is true and the request is successful:
     *     {
     *         "status": "STATUS_SUCCEEDED",
     *         "joinCollaborationToken": "kM9wXdRj2p8v_qL3sHBkTz"
     *     }
     */
    public function edit_playlist(
        $playlistId,
        $title = null,
        $description = null,
        $privacyStatus = null,
        $moveItem = null,
        $addPlaylistId = null,
        $addToTop = null,
        $collaboration = null,
        $sortOrder = null,
        $voteOption = null,
    ) {
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

        if ($collaboration) {
            $actions[] = ["action" => "ACTION_CREATE_COLLABORATION_INVITE_LINK"];
        } else if ($collaboration === false) {
            $actions[] = [
                "action" => "ACTION_SET_CLOSED_TO_CONTRIBUTIONS",
                "closedToContributions" => true
            ];
        }

        if ($moveItem) {
            $action = (object)[
                'action' => 'ACTION_MOVE_VIDEO_BEFORE',
                'setVideoId' => is_string($moveItem) ? $moveItem : $moveItem[0],
            ];

            if (is_array($moveItem) && count($moveItem) > 1) {
                $action->movedSetVideoIdSuccessor = $moveItem[1];
            }
            $actions[] = $action;
        }

        if ($addPlaylistId) {
            $actions[] = [
                'action' => 'ACTION_ADD_PLAYLIST',
                'addedFullListId' => $addPlaylistId
            ];
        }

        if ($sortOrder) {
            $actions[] = [
                "action" => "ACTION_SET_PLAYLIST_VIDEO_ORDER",
                "playlistVideoOrder" => $sortOrder,
            ];
        }

        if ($addToTop === false) {
            $actions[] = ['action' => 'ACTION_SET_ADD_TO_TOP', 'addToTop' => 'false'];
        } elseif ($addToTop === true) {
            $actions[] = ['action' => 'ACTION_SET_ADD_TO_TOP', 'addToTop' => 'true'];
        }

        if ($voteOption) {
            $actions[] = [
                "action" => "ACTION_SET_ALLOW_ITEM_VOTE",
                "itemVotePermission" => $voteOption->getArgumentForRequest(),
            ];
        }

        $body['actions'] = $actions;
        $endpoint = 'browse/edit_playlist';

        $response = $this->_send_request($endpoint, $body);
        if ($collaboration && nav($response, "status", true) === ResponseStatus::SUCCEEDED) {
            $invite_link = nav($response, "collaborationInviteLink", true);

            parse_str(parse_url($invite_link, PHP_URL_QUERY), $params);
            $jct = $params['jct'] ?? null;

            return (object)[
                "status" => $response->status,
                "joinCollaborationToken" => $jct,
            ];
        }

        // Why allow returning a string or an object?!?!
        return empty($response->status) ? $response : $response->status;
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
            throw new YTMusicUserError("You must provide either videoIds or a source_playlist to add to the playlist");
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

        return empty($response->status) ? $response : $response->status;
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
            throw new YTMusicUserError("Cannot remove songs, because setVideoId is missing. Do you own this playlist?");
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

        return empty($response->status) ? $response : $response->status;
    }
}
