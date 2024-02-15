<?php

namespace Ytmusicapi;

trait Browse
{
    /**
     * Get information about the authorized account.
     *
     * @return Account
     *
     * Known differences from Python version:
     *   - Function not available in Python version
     */
    public function get_account()
    {
        $this->_check_auth();

        $endpoint = "account/account_menu";
        $response = $this->_send_request($endpoint, []);

        $renderer = nav($response, "actions.0.openPopupAction.popup.multiPageMenuRenderer.header.activeAccountHeaderRenderer", true);
        $sections = nav($response, "actions.0.openPopupAction.popup.multiPageMenuRenderer.sections", true);

        if (!$renderer || !$sections) {
            throw new \Exception("Could not find account information.");
        }

        $account_name = nav($renderer, "accountName.runs.0.text", true);
        $thumbnails = nav($renderer, "accountPhoto.thumbnails", true);
        $channelId = nav($sections, "0.multiPageMenuSectionRenderer.items.0.compactLinkRenderer.navigationEndpoint.browseEndpoint.browseId", true);
        $premium = nav($sections, "0.multiPageMenuSectionRenderer.items.1.compactLinkRenderer.icon.iconType", true) === "MONETIZATION_ON";

        $account = new Account();
        $account->name = $account_name;
        $account->channelId = $channelId;
        $account->thumbnails = $thumbnails;
        $account->is_premium = $premium;

        return $account;
    }

    /**
     * Get the home page.
     * The home page is structured as titled rows, returning 3 rows of music suggestions at a time.
     * Content varies and may contain artist, album, song or playlist suggestions, sometimes mixed within the same row
     *
     * Known differences from Python version:
     *   - Each item contains a resultType property indicating the type of content
     *
     * @param int $limit Number of rows to return
     * @return Shelf[]
     */
    public function get_home($limit = 3)
    {
        $endpoint = "browse";
        $body = ["browseId" => "FEmusic_home"];
        $response = $this->_send_request($endpoint, $body);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));
        $home = parse_mixed_content($results);

        $section_list = nav($response, join(SINGLE_COLUMN_TAB, "sectionListRenderer"));
        if (isset($section_list->continuations)) {

            $request_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };

            $parse_func = function ($contents) {
                return parse_mixed_content($contents);
            };

            $continuations = get_continuations($section_list, "sectionListContinuation", $limit - count($home), $request_func, $parse_func);
            $home = array_merge($home, $continuations);
        }

        return $home;
    }

    /**
     * Get information about an artist and their top releases (songs,
     * albums, singles, videos, and related artists). The top lists
     * contain pointers for getting the full list of releases.
     *
     * For songs/videos, pass the browseId to `get_playlist`.
     * For albums/singles, pass browseId and params to `get_artist_albums`.
     *
     * warning:
     *
     *   The returned channelId is not the same as the one passed to the function.
     *   It should be used only with `subscribe_artists`
     *
     * Known differences from Python version:
     *   - Check another location for suffleId and radioId
     *   - Checks musicVisualHeaderRenderer for artist name
     *   - Checks if artist has a subscription button
     *
     * @param string $channelId channel id of the artist
     * @return Artist
     */
    public function get_artist($channelId)
    {
        if (strpos($channelId, "MPLA") === 0) {
            $channelId = substr($channelId, 4);
        }

        $body = ["browseId" => $channelId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);

        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));

        $header = nav($response, "header.musicImmersiveHeaderRenderer", true);
        if (!$header) {
            $header = nav($response, "header.musicVisualHeaderRenderer", true);
        }

        $artist = (object)[
            "description" => null,
            "views" => null,
            "name" => nav($header, TITLE_TEXT),
        ];

        $descriptionShelf = find_object_by_key($results, DESCRIPTION_SHELF, null, true);
        if ($descriptionShelf) {
            $artist->description = nav($descriptionShelf, DESCRIPTION);
            $artist->views = isset($descriptionShelf->subheader) ? $descriptionShelf->subheader->runs[0]->text : null;
        }

        if (isset($header->subscriptionButton)) {
            $subscription_button = $header->subscriptionButton->subscribeButtonRenderer;
            $artist->channelId = $subscription_button->channelId;
            $artist->subscribers = nav($subscription_button, join("subscriberCountText.runs.0.text"), true);
            $artist->subscribed = (bool)$subscription_button->subscribed;
        }

        $artist->shuffleId = nav($header, join("playButton.buttonRenderer", NAVIGATION_WATCH_PLAYLIST_ID), true);
        $artist->radioId = nav($header, join("startRadioButton.buttonRenderer", NAVIGATION_WATCH_PLAYLIST_ID), true);
        if (!$artist->shuffleId) {
            $artist->shuffleId = nav($header, join("playButton.buttonRenderer", NAVIGATION_WATCH_PLAYLIST_ID2), true);
        }
        if (!$artist->radioId) {
            $artist->radioId = nav($header, join("startRadioButton.buttonRenderer", NAVIGATION_WATCH_PLAYLIST_ID2), true);
        }

        $artist->thumbnails = nav($header, THUMBNAILS, true);

        // API sometimes does not return songs
        // A bit weird that songs will always be defined with a browseId but other categories might not be.
        $artist->songs = (object)["browseId" => null];
        if (isset($results[0]->musicShelfRenderer)) {
            $musicShelf = $results[0]->musicShelfRenderer;
            if (property_exists(nav($musicShelf, TITLE), "navigationEndpoint")) {
                $artist->songs->browseId = nav($musicShelf, join(TITLE, NAVIGATION_BROWSE_ID));
            }
            $artist->songs->results = parse_playlist_items($musicShelf->contents);
        }

        $categories = parse_artist_contents($results);
        foreach ($categories as $key => $value) {
            $artist->{$key} = $value;
        }

        return $artist;
    }

    /**
     * Get the full list of an artist's albums or singles
     *
     * @param string $channelId browseId of the artist as returned by `get_artist`
     * @param array $params params obtained by `get_artist`
     * @param int $limit Number of albums to return. NULL retrieves them all. Default: 100
     * @param string $order Order of albums to return. Allowed values: 'Recency', 'Popularity', 'Alphabetical order'. Default: Default order.
     * @return AlbumInfo[] List of albums in the format of `get_library_albums`,
     *  except artists key is missing.
     */
    public function get_artist_albums($channelId, $params, $limit = 100, $order = "")
    {
        if (!str_starts_with($channelId, "MPAD")) {
            $channelId = "MPAD" . $channelId;
        }
        $body = ["browseId" => $channelId, "params" => $params];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);

        $request_func = function ($additionalParams) use ($endpoint, $body) {
            return $this->_send_request($endpoint, $body, $additionalParams);
        };

        $parse_func = function ($contents) {
            return parse_albums($contents);
        };

        if ($order) {
            $sort_options = nav(
                $response,
                join(
                    SINGLE_COLUMN_TAB, SECTION, HEADER_SIDE,
                    "endItems.0.musicSortFilterButtonRenderer",
                    "menu.musicMultiSelectMenuRenderer.options"
                ),
            );

            // Find the first nav result that matches the order
            $continution = null;
            foreach ($sort_options as $option) {
                $nav_result = nav($option, join(
                    MULTI_SELECT,
                    "selectedCommand",
                    "commandExecutorCommand",
                    "commands",
                    -1,
                    "browseSectionListReloadEndpoint"
                ));
                if (strtolower($nav_result) === strtolower($order)) {
                    $continution = $nav_result;
                    break;
                }
            }

            // if a valid order was provided, request continuation and replace original response
            if ($continution) {
                $additionalParams = get_reloadable_continuation_params(
                    ["continations" => $continution->continuation],
                );
                $response = $request_func($additionalParams);
                $results = nav($response, join(SECTION_LIST_CONTINUATION, CONTENT));
            } else {
                throw new \Exception("Invalid order parameter {$order}.");
            }
        } else {
            // just use the results from the first request
            $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM));
        }

        $contents = nav($results, GRID_ITEMS, true);
        if (!$contents) {
            $contents = nav($results, CAROUSEL_CONTENTS);
        }

        $albums = parse_albums($contents);

        $results = nav($results, GRID, true);
        if (isset($results->continuations)) {
            $remaining_limit = $limit ? $limit - count($albums) : null;
            $albums = array_merge(
                $albums,
                get_continuations($results, "gridContinuation", $remaining_limit, $request_func, $parse_func)
            );
        }

        return $albums;
    }

    /**
     * Get information and tracks of an album
     *
     * @param string $browseId browseId of the album, for example
     *   returned by `search()` or `get_song_related()`
     * @return Album
     */
    public function get_album($browseId)
    {
        if (!$browseId || !str_starts_with($browseId, "MPRE")) {
            throw new \Exception("Invalid album browseId provided, must start with MPRE.");
        }


        $body = ["browseId" => $browseId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);

        $album = parse_album_header($response);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, MUSIC_SHELF));
        $album->tracks = parse_playlist_items($results->contents, null, true);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST, 1, CAROUSEL), true);
        if ($results) {
            $album->other_versions = parse_content_list($results->contents, "Ytmusicapi\\parse_album"); // Probably need a function to parse album
        }
        $album->duration_seconds = sum_total_duration($album);
        foreach ($album->tracks as $i => $track) {
            $album->tracks[$i]->album = $album->title;
            $album->tracks[$i]->artists = $album->tracks[$i]->artists ?: $album->artists;
        }
        return $album;
    }

    /**
     * Get an album's browseId based on its audioPlaylistId.
     * Each album has a browseId that can used to get more information.
     * You can find the $audioPlaylistId by clicking on an album
     * title in YouTube Music and looking at the URL. The
     * $audoPlaylistId will show in the `list` parameter.
     *
     * @param string $audioPlaylistId id of the audio playlist  (starting with `OLAK5uy_`)
     * @return string|null browseId (starting with `MPREb_`)
     */
    public function get_album_browse_id($audioPlaylistId)
    {
        $params = ["list" => $audioPlaylistId];
        $response = $this->_send_get_request(YTM_DOMAIN . "/playlist", $params);

        // Python has a conversion from unicode_escape to utf8 it seems. Maybe needed?
        // $response = html_entity_decode($response, ENT_NOQUOTES, 'UTF-8');

        // You can probably get a whole lot of information from this reponse.
        // The following replacement might help with that.
        // $response = str_replace(['\\"', "\\x22", "\\x5b", "\\x5d", "\\x7b", "\\x7d"], ['"', "*", "[", "]", "{", "}"], $response);
        // Or (Warning: PHP 7.4+ code next line):
        // $response = preg_replace_callback('/(\\x[0-9a-f]{2})/i', fn ($matches) => chr(hexdec($matches[1])), $hex_string);
        // Seems like PHP would have a function for this. It probably does. I'm just dumb.

        preg_match('/\\\\"browseId\\\\"\\:\\\\"(MPREb_.*?)\\\\"/', $response, $matches);

        $browse_id = null;
        if (count($matches)) {
            $browse_id = $matches[1];
        }

        return $browse_id;
    }

    /**
     * Retrieve a user's page. A user may own videos or playlists.
     *
     * Known differences from Python version:
     *   - Adds channelId to response
     *
     * @param string $channelId channelId of the user
     * @return User Dictionary with information about a user.
     */
    public function get_user($channelId)
    {
        $endpoint = "browse";
        $body = ["browseId" => $channelId];
        $response = $this->_send_request($endpoint, $body);

        $user = [
            "name" => nav($response, join("header.musicVisualHeaderRenderer", TITLE_TEXT)),
            "channelId" => $channelId,
        ];
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST));
        $user = array_merge($user, parse_artist_contents($results));

        return (object)$user;
    }

    /**
     * Retrieve a list of playlists for a given user.
     * Call this function after get_user() with the returned `params` to get the full list.
     * I think this is needed only if the user has more than 10 playlists.
     *
     * Known differences from Python version:
     *   - Clarified documentation
     *
     * @param string $channelId channelId of the user
     * @param string $params params obtained by previous call to `get_user`
     * @return object[] List of playlists
     */
    public function get_user_playlists($channelId, $params = null)
    {
        $endpoint = "browse";
        $body = ["browseId" => $channelId, "params" => $params];
        $response = $this->_send_request($endpoint, $body);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, GRID_ITEMS), true);
        if (!$results) {
            return [];
        }

        $user_playlists = parse_content_list($results, "Ytmusicapi\\parse_playlist");
        return $user_playlists;
    }

    /**
     * Returns metadata and streaming information about a song or video.
     *
     * @param string $videoId Video ID
     * @param int|null $signatureTimestamp Provide the current YouTube signatureTimestamp.
     *   If not provided a default value will be used, which might result in invalid
     *   streaming URLs.
     * @return Song
     */
    public function get_song($videoId, $signatureTimestamp = null)
    {
        if (!$signatureTimestamp) {
            $epoch = new \DateTime("1969-12-31");
            $today = new \DateTime();
            $difference = $today->getTimestamp() - $epoch->getTimestamp();
            $days = floor($difference / (60 * 60 * 24)); // ??
            $signatureTimestamp = $days - 1;
        }

        $body = [
            "video_id" => $videoId,
            "playbackContext" => [
                "contentPlaybackContext" => [
                    "signatureTimestamp" => $signatureTimestamp,
                ],
            ],
        ];

        $data = $this->_send_request("player", $body);

        $song = (object)[];
        $song->playabilityStatus = $data->playabilityStatus ?? null;
        $song->streamingData = $data->streamingData ?? null;
        $song->playbackTracking = $data->playbackTracking ?? null;
        $song->videoDetails = $data->videoDetails ?? null;
        $song->microformat = $data->microformat ?? null;

        return $song;
    }

    /**
     * Get basic details about a song or video.
     *
     * This is basically the only way to determine if the video/track
     * is actually music or not. Use the `music` property to determine
     * if the track is a music video or not.
     *
     * Known differences from Python version:
     *   - Function not available in Python version
     *
     * @param string|Song $videoId Video ID
     * @return SongInfo
     */
    public function get_song_info($videoId)
    {
        $song = null;
        if (is_string($videoId)) {
            $videoId = get_video_id($videoId);
            $song = $this->get_song($videoId);
        } elseif (is_object($videoId) && $videoId->playabilityStatus) {
            $song = $videoId;
        }

        if (!$song) {
            throw new \Exception("Invalid videoId or Song object provided.");
        }

        if ($song->playabilityStatus->status === "ERROR") {
            throw new \Exception($song->playabilityStatus->reason);
        }

        $toast = nav(
            $song,
            "playabilityStatus.miniplayer.miniplayerRenderer.minimizedEndpoint.addToToastAction.item.notificationActionRenderer.responseText.runs.0.text",
            true
        );

        $track = new SongInfo();
        $track->music = $song->playabilityStatus->status === "OK";
        $track->toast = $toast ?? "";
        $track->canEmbed = $song->playabilityStatus->playableInEmbed ?? false;
        $track->playbackMode = $song->playabilityStatus->miniplayer->miniplayerRenderer->playbackMode ?? "";
        $track->videoId = $song->videoDetails->videoId ?? $videoId;
        $track->title = $song->videoDetails->title ?? "";
        $track->author = $song->videoDetails->author ?? "";
        $track->viewCount = (int)($song->videoDetails->viewCount ?? 0);
        $track->thumbnails = $song->videoDetails->thumbnail->thumbnails ?? [];
        $track->description = $song->microformat->microformatDataRenderer->description;
        $track->tags = $song->microformat->microformatDataRenderer->tags ?? [];

        // Probably some dumb regulation written and passed by people
        // who don't know anything about technology. The "madeForKids" label
        // is a very loose indiation of whether the track is made for kids
        // or not. It's not always accurate.
        $track->madeForKids = $toast && strpos($toast, $this->_("for kids")) !== false;

        // Some regular videos marked as music don't have associated video types.
        // These seem to all be uploaded videos from unofficial sources.
        // We'll mark those as regular videos. If you find a case where this is
        // not true, please open an issue including the videoId.
        $track->videoType = $track->music ? ($song->videoDetails->musicVideoType ?? "MUSIC_VIDEO_TYPE_UGC") : null;

        $track->duration_seconds = (int)($song->videoDetails->lengthSeconds ?? 0);
        if ($track->duration_seconds > 60 * 60) {
            $track->duration = gmdate("H:i:s", $track->duration_seconds);
        } else {
            $track->duration = gmdate("i:s", $track->duration_seconds);
        }
        $track->duration = ltrim($track->duration, "0");

        return $track;
    }

    /**
     * Gets related content for a song. Equivalent to the content
     * shown in the "Related" tab of the watch panel.
     *
     * Example:
     *  $playlist = $yt->get_watch_playlist($playlistId);
     *  $related = $yt->get_lyrics($playlist->related);
     *  print_r($related);
     *
     * @param string $browseId The `related` key  in the `get_watch_playlist` response.
     * @return Shelf[] List of related songs and videos.
     */
    public function get_song_related($browseId)
    {
        if (!is_string($browseId)) {
            throw new \Exception("Invalid browseId provided.");
        }

        $body = ["browseId" => $browseId];
        $response = $this->_send_request("browse", $body);
        $contents = nav($response, join("contents", SECTION_LIST), true);

        $content = parse_mixed_content($contents);
        $content = array_map(function ($item) { return Shelf::from($item); }, $content);
        return $content;
    }

    /**
     * Returns lyrics of a song or video. Note that not all songs have lyrics.
     * You may have an empty result if there are no lyrics.
     *
     * @param string $browseId Lyrics browse id obtained from `get_watch_playlist`.
     *   This is not the same as the videoId.
     * @return Lyrics
     *
     * Example:
     *  $playlist = $yt->get_watch_playlist($playlistId);
     *  $lyrics = $yt->get_lyrics($playlist->lyrics);
     *
     * Returns:
     * 	(object)[
     * 		"lyrics" => "Today is gonna be the day\\nThat they're gonna throw it back to you\\n",
     * 		"source" => "Source: LyricFind"
     * 	]
     */
    public function get_lyrics($browseId)
    {
        $lyrics = (object)[];

        if (!is_string($browseId)) {
            throw new \Exception("Invalid browseId provided. This song might not have lyrics.");
        }

        $response = $this->_send_request("browse", ["browseId" => $browseId]);

        $lyrics->lyrics = nav($response, join("contents", SECTION_LIST_ITEM, DESCRIPTION_SHELF, DESCRIPTION), true);
        $lyrics->source = nav($response, join("contents", SECTION_LIST_ITEM, DESCRIPTION_SHELF, "footer", RUN_TEXT), true);

        return $lyrics;
    }

    /**
     * Fetches suggested artists from taste profile (music.youtube.com/tasteprofile).
     * Tasteprofile allows users to pick artists to update their recommendations.
     * Only returns a list of suggested artists, not the actual list of selected entries.
     * Same results appear whether authenticated or not.
     *
     * Known differences from Python version:
     *  - Returns thumbnails as an array of objects with url, width, and height
     *
     * @return TasteProfile[] keyed by artist with their selection & impression value
     *
     * Example::
     *
     *	[
     *		"Drake": [
     *			"selectionValue": "tastebuilder_selection=/m/05mt_q"
     *			"impressionValue": "tastebuilder_impression=/m/05mt_q"
     *			"thumbnails": [
     *				(object)["url" => "https://lh3.googleusercontent.com/...", "width" => 60, "height" => 60],
     *				(object)["url" => "https://lh3.googleusercontent.com/...", "width" => 120, "height" => 120],
     *				(object)["url" => "https://lh3.googleusercontent.com/...", "width" => 226, "height" => 226],
     *				(object)["url" => "https://lh3.googleusercontent.com/...", "width" => 544, "height" => 544],
     *			]
     *		]
     *	]
     */
    public function get_tasteprofile()
    {
        $response = $this->_send_request("browse", ["browseId" => "FEmusic_tastebuilder"]);
        $profiles = nav($response, TASTE_PROFILE_ITEMS);

        $taste_profiles = [];
        foreach ($profiles as $itemList) {
            foreach ($itemList->tastebuilderItemListRenderer->contents as $item) {
                $artist = nav($item->tastebuilderItemRenderer, TASTE_PROFILE_ARTIST)[0]->text;
                $taste_profiles[$artist] = new TasteProfile();
                $taste_profiles[$artist]->selectionValue = $item->tastebuilderItemRenderer->selectionFormValue;
                $taste_profiles[$artist]->impressionValue = $item->tastebuilderItemRenderer->impressionFormValue;
                $taste_profiles[$artist]->thumbnails = $item->tastebuilderItemRenderer->thumbnailRenderer->musicArtistThumbnailRenderer->thumbnail->thumbnails;
            }
        }
        return $taste_profiles;
    }

    /**
     * Favorites artists to see more recommendations from the artist.
     * Use `get_tasteprofile` to see which artists are available to be recommended
     *
     * @param string[] $artists A List with names of artists, must be contained in the tasteprofile
     * @param array|null $taste_profile tasteprofile result from `get_tasteprofile`.
     *  Pass this if you call `get_tasteprofile` to save an extra request.
     * @return void
     */
    public function set_tasteprofile($artists, $taste_profile = null)
    {
        if (!$taste_profile) {
            $taste_profile = $this->get_tasteprofile();
        }

        $formData = [
            "impressionValues" => array_map(function ($profile) {
                return $profile->impressionValue;
            }, $taste_profile, []), // Blank array creates sequential instead of keyed array
            "selectedValues" => []
        ];

        foreach ($artists as $artist) {
            if (empty($taste_profile[$artist])) {
                throw new \Exception("The artist, $artist, was not present in taste!");
            }
            if (empty($taste_profile[$artist]->selectionValue)) {
                throw new \Exception("The artist, $artist, has no selectionValue!");
            }

            $formData["selectedValues"][] = $taste_profile[$artist]->selectionValue;
        }

        $body = ["browseId" => "FEmusic_home", "formData" => $formData];
        $this->_send_request("browse", $body);
    }
}
