<?php

namespace Ytmusicapi;

/**
 * Known as parse_watch_playlist() in Python, but that conflicts with
 * function in parsers/browsing.php, so renamed here.
 *
 * @param array $results
 * @return array
 */
function watch_playlist_parser($results)
{
    $tracks = [];
    $PPVWR = 'playlistPanelVideoWrapperRenderer';
    $PPVR = 'playlistPanelVideoRenderer';

    foreach ($results as $result) {
        $counterpart = null;
        if (isset($result->{$PPVWR})) {
            $counterpart = $result->{$PPVWR}->counterpart[0]->counterpartRenderer->{$PPVR};
            $result = $result->{$PPVWR}->primaryRenderer;
        }
        if (!isset($result->{$PPVR})) {
            continue;
        }

        $data = $result->{$PPVR};
        if (isset($data->unplayableText)) {
            continue;
        }

        $track = parse_watch_track($data);
        if ($counterpart) {
            $track->counterpart = parse_watch_track($counterpart);
        }

        $tracks[] = $track;
    }

    return $tracks;
}

/**
 * @param object $data
 * @return object
 */
function parse_watch_track($data)
{
    $like_status = null;

    $items = nav($data, MENU_ITEMS);

    foreach ($items as $item) {
        if (isset($item->toggleMenuServiceItemRenderer)) {
            $service = $item->toggleMenuServiceItemRenderer->defaultServiceEndpoint;
            if (isset($service->likeEndpoint)) {
                $like_status = parse_like_status($service);
            }
        }
    }

    $track = [
        'videoId' => $data->videoId,
        'title' => nav($data, TITLE_TEXT),
        'length' => nav($data, 'lengthText.runs.0.text', true),
        'thumbnail' => nav($data, THUMBNAIL),
        'likeStatus' => $like_status,
        'isExplicit' => nav($data, BADGE_LABEL, true) !== null,
        'videoType' => nav($data, join('navigationEndpoint', NAVIGATION_VIDEO_TYPE), true),
        'inLibrary' => null,
        'feedbackTokens' => null,
        'pinnedToListenAgain' => null,
        'listenAgainFeedbackTokens' => null,
    ];

    $track = array_merge($track, parse_song_menu_data($data));

    $longBylineText = nav($data, "longBylineText");
    if ($longBylineText) {
        $song_info = parse_song_runs($longBylineText->runs);
        $track = array_merge($track, $song_info);
    }

    return (object)$track;
}

/**
 * @param object $watchNextRenderer
 * @return array
 */
function get_tab_browse_ids($watchNextRenderer) {
    $browse_ids = [];

    foreach ($watchNextRenderer->tabs as $tab) {
        if (isset($tab->tabRenderer->unselectable)) {
            continue;
        }

        $browse_endpoint = nav($tab, "tabRenderer.endpoint.browseEndpoint", true);
        if ($browse_endpoint) {
            $page_type = nav($browse_endpoint, PAGE_TYPE);
            $browse_ids[$page_type] = $browse_endpoint->browseId;
        }
    }

    return $browse_ids;
}
