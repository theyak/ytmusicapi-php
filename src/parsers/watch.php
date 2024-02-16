<?php

namespace Ytmusicapi;

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

function parse_watch_track($data)
{
    $feedback_tokens = null;
    $like_status = null;
    $library_status = null;

    $items = nav($data, MENU_ITEMS);

    foreach ($items as $item) {
        if (isset($item->toggleMenuServiceItemRenderer)) {
            $library_status = parse_song_library_status($item);
            $service = $item->toggleMenuServiceItemRenderer->defaultServiceEndpoint;
            if (isset($service->feedbackEndpoint)) {
                $feedback_tokens = parse_song_menu_tokens($item);
            }
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
        'feedbackTokens' => $feedback_tokens,
        'likeStatus' => $like_status,
        'inLibrary' => $library_status,
        'isExplicit' => nav($data, BADGE_LABEL, true) !== null,
        'videoType' => nav($data, join('navigationEndpoint', NAVIGATION_VIDEO_TYPE), true)
    ];

    $longBylineText = nav($data, "longBylineText");
    if ($longBylineText) {
        $song_info = parse_song_runs($longBylineText->runs);
        $track = array_merge($track, $song_info);
    }

    return (object)$track;
}

function get_tab_browse_id($watchNextRenderer, $tab_id)
{
    if (!isset($watchNextRenderer->tabs[$tab_id]->tabRenderer->unselectable)) {
        return $watchNextRenderer->tabs[$tab_id]->tabRenderer->endpoint->browseEndpoint->browseId;
    } else {
        return null;
    }
}
