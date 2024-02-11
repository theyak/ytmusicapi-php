<?php

namespace Ytmusicapi;

trait Uploads
{
    /**
     * Returns a list of uploaded songs
     *
     * @param int $limit How many songs to return. `null` retrieves them all. Default: 25
     * @param string $order Order of songs to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return array List of uploaded songs.
     */
    public function get_library_upload_songs($limit = 25, $order = null)
    {
        $this->_check_auth();
        $endpoint = "browse";
        $body = ["browseId" => "FEmusic_library_privately_owned_tracks"];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }
        $response = $this->_send_request($endpoint, $body);
        $results = get_library_contents($response, MUSIC_SHELF);

        if ($results === null) {
            return [];
        }

        pop_songs_random_mix($results);
        $songs = parse_uploaded_items($results->contents);

        if (isset($results->continuations)) {
            $request_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };
            $remaining_limit = $limit === null ? null : ($limit - count($songs));
            $songs = array_merge($songs, get_continuations($results, 'musicShelfContinuation', $remaining_limit, $request_func, 'parse_uploaded_items'));
        }

        return $songs;
    }

    /**
     * Gets the albums of uploaded songs in the user's library.
     *
     * @param int $limit Number of albums to return. `null` retrieves them all. Default: 25
     * @param string $order Order of albums to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return array List of albums as returned by `get_library_albums`
     */
    public function get_library_upload_albums($limit = 25, $order = null)
    {
        $this->_check_auth();
        $body = ["browseId" => "FEmusic_library_privately_owned_releases"];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        return parse_library_albums(
            $response,
            function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            },
            $limit
        );
    }

    /**
     * Gets the artists of uploaded songs in the user's library.
     *
     * @param int $limit Number of artists to return. `null` retrieves them all. Default: 25
     * @param string $order Order of artists to return. Allowed values: 'a_to_z', 'z_to_a', 'recently_added'. Default: Default order.
     * @return array List of artists as returned by `get_library_artists`
     */
    public function get_library_upload_artists($limit = 25, $order = null)
    {
        $this->_check_auth();
        $body = ["browseId" => "FEmusic_library_privately_owned_artists"];
        validate_order_parameter($order);
        if ($order !== null) {
            $body["params"] = prepare_order_params($order);
        }
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        return parse_library_artists(
            $response,
            function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            },
            $limit
        );
    }

    /**
     * Returns a list of uploaded tracks for the artist.
     *
     * @param string $browseId Browse id of the upload artist, i.e. from `get_library_upload_artists`
     * @param int $limit Number of songs to return (increments of 25).
     * @return UploadTrack[] List of uploaded songs.
     */
    public function get_library_upload_artist($browseId, $limit = 25)
    {
        $this->_check_auth();
        $body = ["browseId" => $browseId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, MUSIC_SHELF));
        if (count($results->contents) > 1) {
            array_shift($results->contents);
        }

        $items = parse_uploaded_items($results->contents);

        if (isset($results->continuations)) {
            $request_func = function ($additionalParams) use ($endpoint, $body) {
                return $this->_send_request($endpoint, $body, $additionalParams);
            };
            $remaining_limit = $limit === null ? null : ($limit - count($items));
            $items = array_merge($items, get_continuations($results, 'musicShelfContinuation', $remaining_limit, $request_func, 'parse_uploaded_items'));
        }

        return $items;
    }

    /**
     * Get information and tracks of an album associated with uploaded tracks
     *
     * @param string $browseId Browse id of the upload album, i.e. from `get_library_upload_albums`
     * @return Album Dictionary with title, description, artist and tracks.
     */
    public function get_library_upload_album($browseId)
    {
        $this->_check_auth();
        $body = ["browseId" => $browseId];
        $endpoint = "browse";
        $response = $this->_send_request($endpoint, $body);
        $album = parse_album_header($response);
        $results = nav($response, join(SINGLE_COLUMN_TAB, SECTION_LIST_ITEM, MUSIC_SHELF));
        $album->tracks = parse_uploaded_items($results->contents);
        $album->duration_seconds = sum_total_duration($album);
        return $album;
    }

    // We do have tests for the following functions, but the must be run manually
    // so ignore them for coverage purposes.

    // @codeCoverageIgnoreStart

    /**
     * Uploads a song to YouTube Music
     *
     * @param string $filepath Path to the music file (mp3, m4a, wma, flac or ogg)
     * @return string "STATUS_SUCCEEDED" or full response
     */
    public function upload_song($filepath)
    {
        $this->_check_auth();

        if (!$this->auth_type === AuthType::BROWSER) {
            throw new \Exception("Please provide browser authentication before using this function");
        }

        if (!file_exists($filepath)) {
            throw new \Exception("The provided file does not exist.");
        }

        $supported_filetypes = ["mp3", "m4a", "wma", "flac", "ogg"];
        if (!in_array(pathinfo($filepath, PATHINFO_EXTENSION), $supported_filetypes)) {
            throw new \Exception("The provided file type is not supported by YouTube Music. Supported file types are " . implode(', ', $supported_filetypes));
        }

        $headers = $this->headers;
        $upload_url = "https://upload.youtube.com/upload/usermusic/http?authuser=" . $headers['x-goog-authuser'];
        $filesize = filesize($filepath);
        $body = "filename=" . basename($filepath);
        unset($headers['content-encoding']);
        $headers['content-type'] = 'application/x-www-form-urlencoded;charset=utf-8';
        $headers['X-Goog-Upload-Command'] = 'start';
        $headers['X-Goog-Upload-Header-Content-Length'] = $filesize;
        $headers['X-Goog-Upload-Protocol'] = 'resumable';
        $options = [];
        if ($this->proxies) {
            $options['proxy'] = $this->proxies;
        }
        $response = $this->session->post($upload_url, $headers, $body, $options);

        $headers['X-Goog-Upload-Command'] = 'upload, finalize';
        $headers['X-Goog-Upload-Offset'] = '0';
        $upload_url = $response->headers['X-Goog-Upload-URL'];
        $response = $this->session->post($upload_url, $headers, file_get_contents($filepath), $options);

        if ($response->status_code === 200) {
            return 'STATUS_SUCCEEDED';
        } else {
            return $response;
        }
    }

    /**
     * Deletes a previously uploaded song or album
     *
     * @param string $entityId The entity id of the uploaded song or album, e.g. retrieved from `get_library_upload_songs`
     * @return string Status String or error
     */
    public function delete_upload_entity($entityId)
    {
        $this->_check_auth();
        $endpoint = 'music/delete_privately_owned_entity';
        if (strpos($entityId, 'FEmusic_library_privately_owned_release_detail') !== false) {
            $entityId = str_replace('FEmusic_library_privately_owned_release_detail', '', $entityId);
        }

        $body = ["entityId" => $entityId];
        $response = $this->_send_request($endpoint, $body);

        if (!isset($response->error)) {
            return 'STATUS_SUCCEEDED';
        } else {
            return $response->error;
        }
    }

    // @codeCoverageIgnoreEnd
}
