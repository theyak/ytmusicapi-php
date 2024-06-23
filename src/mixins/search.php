<?php

//  91, 105, 117..118, 144, 148, 60

namespace Ytmusicapi;

trait Search
{
    /**
     * Search YouTube music
     * Returns results within the provided category.
     *
     * @param string $query Query string, e.g., 'Oasis Wonderwall'
     * @param string|null $filter Filter for item types. Allowed values: `songs`, `videos`, `albums`, `artists`, `playlists`, `community_playlists`, `featured_playlists`, `uploads`.
     *   This is similar to clicking "View more" on the YouTube Music search results page
     *   Default: Default search, including all types of items.
     * @param string|null $scope Search scope. Allowed values: `library`, `uploads`.
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
            throw new \Exception(
                "Invalid filter provided. Please use one of the following filters or leave out the parameter: "
                . implode(', ', $filters)
            );
        }

        $scopes = ['library', 'uploads'];
        if ($scope && !in_array($scope, $scopes)) {
            throw new \Exception(
                "Invalid scope provided. Please use one of the following scopes or leave out the parameter: "
                . implode(', ', $scopes)
            );
        }

        if ($scope === "uploads" && $filter) {
            throw new \Exception(
                "No filter can be set when searching uploads. Please unset the filter parameter when scope is set to uploads."
            );
        }

        if ($scope === "library" && in_array($filter, ['community_playlists', 'featured_playlists'])) {
            throw new \Exception(
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
        if ($filter && strpos($filter, "playlists") !== false) {
            $filter = "playlists";
        } elseif ($scope === "uploads") {
            $filter = "uploads";
        }

        foreach ($section_list as $res) {
            $result_type = null;
            $category = null;
            $search_result_types = $this->get_search_result_types();

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

                # if we know the filter it's easy to set the result type
                # unfortunately uploads is modeled as a filter (historical reasons),
                # so we take care to not set the result type for that scope
                if ($filter && $filter !== $scopes[1]) {
                    $result_type = strtolower(substr($filter, 0, -1));
                }
            } else {
                continue;
            }

            $search_results = array_merge(
                $search_results,
                parse_search_results($shelf_contents, $search_result_types, $result_type, $category)
            );

            if ($filter) {  // if filter is set, there are continuations
                $request_func = function ($additionalParams) use ($endpoint, $body) {
                    return $this->_send_request($endpoint, $body, $additionalParams);
                };

                $parse_func = function ($contents) use ($search_result_types, $result_type, $category) {
                    return parse_search_results($contents, $search_result_types, $result_type, $category);
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
     * @return array List of search suggestion results depending on $detailed_runs param.
     */
    public function get_search_suggestions($query, $detailed_runs = false)
    {
        $body = ['input' => $query];
        $endpoint = 'music/get_search_suggestions';

        $response = $this->_send_request($endpoint, $body);
        $search_suggestions = parse_search_suggestions($response, $detailed_runs);

        return $search_suggestions;
    }
}
