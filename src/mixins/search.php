<?php

namespace Ytmusicapi;

/**
 * These don't seem to actually do anything :(
 *
 * @phpstan-type SearchFilterType
 *     "songs"|
 *     "videos"|
 *     "albums"|
 *     "artists"|
 *     "playlists"|
 *     "community_playlists"|
 *     "featured_playlists"|
 *     "profiles"|
 *     "podcasts"|
 *     "episodes
 *
 * @phpstan-type SearchScopeType "uploads"|"library"
 */
trait Search
{
    /**
     * Search YouTube music
     * Returns results within the provided category.
     *
     * @param string $query Query string, e.g., 'Oasis Wonderwall'
     * @param ?SearchFilterType $filter Filter for item types. Allowed values: `songs`, `videos`, `albums`, `artists`, `playlists`, `community_playlists`, `featured_playlists`,  `profiles`, `podcasts`, `episodes`.
     *   This is similar to clicking "View more" on the YouTube Music search results page
     *   Default: Default search, including all types of items.
     * @param ?SearchScopeType $scope Search scope. Allowed values: `library`, `uploads`.
     *   Default: Search the public YouTube Music catalogue.
     *   Changing scope from the default will reduce the number of settable filters. Setting a filter that is not permitted will throw an exception.
     *   For uploads, no filter can be set.
     *   For library, community_playlists and featured_playlists filter cannot be set.
     *   FWIW, this doesn't actually seem to work, even in Python version.
     * @param int $limit Number of search results to return
     *   Default: 20
     * @param bool $ignore_spelling Whether to ignore YTM spelling suggestions.
     *   If true, the exact search term will be searched for, and will not be corrected.
     *   This does not have any effect when the filter is set to `uploads`.
     *   Search results seem to be fairly unpredictable regardless of this setting.
     *   Default: false, will use YTM's default behavior of autocorrecting the search.
     * @return SearchResult[] List of results depending on filter.
     *   resultType specifies the type of item (important for default search).
     *   albums, artists and playlists additionally contain a browseId, corresponding to
     *   albumId, channelId and playlistId (browseId=`VL`+playlistId)
     */
    public function search($query, $filter = null, $scope = null, $limit = 20, $ignore_spelling = false)
    {
        $body = ['query' => $query];
        $endpoint = 'search';
        $search_results = [];
        $filters = [
            'albums', 'artists', 'playlists', 'community_playlists', 'featured_playlists', 'songs',
            'videos', 'profiles', 'podcasts', 'episodes'
        ];
        if ($filter && !in_array($filter, $filters)) {
            throw new YTMusicUserError(
                "Invalid filter provided. Please use one of the following filters or leave out the parameter: "
                . implode(', ', $filters)
            );
        }

        $scopes = ['library', 'uploads'];
        if ($scope && !in_array($scope, $scopes)) {
            throw new YTMusicUserError(
                "Invalid scope provided. Please use one of the following scopes or leave out the parameter: "
                . implode(', ', $scopes)
            );
        }

        if ($scope === "uploads" && $filter) {
            throw new YTMusicUserError(
                "No filter can be set when searching uploads. Please unset the filter parameter when scope is set to uploads."
            );
        }

        if ($scope === "library" && in_array($filter, ['community_playlists', 'featured_playlists'])) {
            throw new YTMusicUserError(
                "$filter cannot be set when searching library. Please use one of the following filters or leave out the parameter: "
                . implode(', ', array_merge(array_slice($filters, 0, 3), array_slice($filters, 5)))
            );
        }

        $params = get_search_params($filter, $scope, $ignore_spelling);
        if ($params) {
            $body['params'] = $params;
        }

        $response = $this->_send_request($endpoint, $body);

        // no results
        if (!isset($response->contents)) {
            return $search_results;
        }

        if (isset($response->contents->tabbedSearchResultsRenderer)) {
            $tab_index = 0;
            if (!$scope || $filter) {
                $tab_index = 0;
            } else {
                $tab_index = array_search($scope, $scopes) + 1;
            }
            $results = $response->contents->tabbedSearchResultsRenderer->tabs[$tab_index]->tabRenderer->content;
        } else {
            $results = $response->contents;
        }

        $section_list = nav($results, SECTION_LIST);

        // no results
        if (count($section_list) === 1 && isset($section_list[0]->itemSectionRenderer)) {
            return $search_results;
        }

        // set filter for parser
        $internal_filter = $filter;
        $result_type = null;
        if ($internal_filter && strpos($internal_filter, "playlists") !== false) {
            $internal_filter = "playlists";
        } elseif ($scope === "uploads") {
            $internal_filter = "uploads";
            $result_type = "upload";
        }

        foreach ($section_list as $res) {
            $category = null;

            if (isset($res->musicCardShelfRenderer)) {
                $top_result = parse_top_result(
                    $res->musicCardShelfRenderer, $this->get_search_result_types()
                );
                $search_results[] = $top_result;

                $shelf_contents = nav($res, ["musicCardShelfRenderer", "contents"], true);
                if (!$shelf_contents) {
                    continue;
                }

                // if "more from youtube" is present, remove it - it's not parseable
                if (isset($shelf_contents[0]->messageRenderer)) {
                    $category = nav($shelf_contents[0], join("messageRenderer", TEXT_RUN_TEXT));
                    array_shift($shelf_contents);
                }
            } elseif (isset($res->musicShelfRenderer)) {
                $shelf_contents = $res->musicShelfRenderer->contents;
                $category = nav($res, join(MUSIC_SHELF, TITLE_TEXT), true);
            } elseif (isset($res->itemSectionRenderer)) {
                $shelf_contents = $res->itemSectionRenderer->contents;
                if (!isset($shelf_contents[0]->musicResponsiveListItemRenderer)) {
                    continue;
                }
            } else {
                continue;
            }

            # if we know the filter it's easy to set the result type
            # unfortunately uploads is modeled as a filter (historical reasons),
            #  so we take care to not set the result type for that scope
            if (isset($res->musicShelfRenderer) || isset($res->itemSectionRenderer)) {
                if ($internal_filter && $scope !== "uploads") {
                    # YTM sometimes pads results with a differently-categorized shelf
                    # (e.g. a "Songs" shelf when filtering by featured_playlists) - skip
                    # it rather than mislabeling its contents as the requested type
                    if ($category) {
                        $string = strtolower(substr($internal_filter, 0, -1));
                        if (!str_contains(strtolower($category), $string)) {
                            continue;
                        }
                    }
                }
            }

            $search_results = array_merge(
                $search_results,
                parse_search_results($shelf_contents, $result_type, $category)
            );

            if ($internal_filter) {  // if filter is set, there are continuations
                $request_func = function ($additionalParams) use ($endpoint, $body) {
                    return $this->_send_request($endpoint, $body, $additionalParams);
                };

                $parse_func = function ($contents) use ($result_type, $category) {
                    return parse_search_results($contents, $result_type, $category);
                };

                $search_results = array_merge(
                    $search_results,
                    get_continuations(
                        $res->musicShelfRenderer,
                        'musicShelfContinuation',
                        $limit - count($search_results),
                        $request_func,
                        $parse_func
                    )
                );
            }
        }

        return $search_results;
    }

    /**
     * Get Search Suggestions
     *
     * @param string $query Query string, e.g., 'faded'
     * @param bool $detailed_runs Whether to return detailed runs of each suggestion.
     *   If true, it returns the query that the user typed and the remaining
     *   suggestion along with the complete text (like many search services
     *   usually bold the text typed by the user).
     *   Default: False, returns the list of search suggestions in plain text.
     * @return array A list of search suggestions. If ``detailed_runs`` is False, it returns plain text suggestions.
     *   If $detailed_runs is true, it returns a list of dictionaries with detailed information.
     */
    public function get_search_suggestions($query, $detailed_runs = false)
    {
        $body = ['input' => $query];
        $endpoint = 'music/get_search_suggestions';

        $response = $this->_send_request($endpoint, $body);
        return parse_search_suggestions($response, $detailed_runs);
    }

    /**
     * Remove search suggestion from the user search history.
     *
     * Example usage:
     *   $suggestions = $ytmusic->get_search_suggestions("fade", true);
     *   $success = $ytmusic->remove_search_suggestions($suggestions, [0]);
     *   if ($success) {
     *       echo "Suggestion removed successfully";
     *   } else {
     *       echo "Failed to remove suggestion";
     *   }
     *
     * @param array $suggestions The dictionary obtained from `get_search_suggestions()`
     *   (with $detailed_runs=true)`
     * @param array|null $indices Optional. The indices of the suggestions to be removed. Default: remove all suggestions.
     * @return bool true if the operation was successful, false otherwise.
     *
     * @throws YTMusicUserError If no search result from history is provided.
     * @throws YTMusicUserError If the index is out of range.
     */
    public function remove_search_suggestions($suggestions, $indices = null)
    {
        $found = false;
        foreach ($suggestions as $run) {
            if (!empty($run->fromHistory)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new YTMusicUserError(
                "No search result from history provided. " .
                "Please run get_search_suggestions first to retrieve suggestions. " .
                "Ensure that you have searched a similar term before."
            );
        }

        if (!$indices) {
            $indices = range(0, count($suggestions) -  1);
        }

        foreach ($indices as $index) {
            if ($index >= count($suggestions)) {
                throw new YTMusicUserError("Index out of range. Index must be smaller than the length of suggestions");
            }
        }

        $feedback_tokens = array_map(function ($index) use ($suggestions) {
            return $suggestions[$index]->feedbackToken;
        }, $indices);

        $feedback_tokens = array_filter($feedback_tokens);
        if (empty($feedback_tokens)) {
            return false;
        }

        $body = ["feedbackTokens" => $feedback_tokens];
        $endpoint = "feedback";
        $response = $this->_send_request($endpoint, $body);
        return (bool)(nav($response, "feedbackResponses.0.isProcessed", true));
    }
}
