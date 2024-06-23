<?php

namespace Ytmusicapi;

function parse_uploaded_items($results)
{
    $songs = [];
    foreach ($results as $result) {
        $data = $result->musicResponsiveListItemRenderer;
        if (!isset($data->menu)) {
            continue;
        }

        $entityId = nav(
            $data,
            join(
                MENU_ITEMS,
                -1,
                MNIR,
                "navigationEndpoint",
                "confirmDialogEndpoint",
                "content",
                "confirmDialogRenderer",
                "confirmButton",
                "buttonRenderer",
                "command",
                "musicDeletePrivatelyOwnedEntityCommand",
                "entityId"
            )
        );

        $videoId = nav($data, join(MENU_ITEMS, "0", MENU_SERVICE, "queueAddEndpoint.queueTarget.videoId"));

        $title = get_item_text($data, 0);
        $like = nav($data, MENU_LIKE_STATUS);
        $thumbnails = isset($data->thumbnail) ? nav($data, THUMBNAILS) : null;
        $duration = null;
        if (isset($data->fixedColumns)) {
            $duration = get_fixed_column_item($data, 0)->text->runs[0]->text;
        }
        $song = new UploadTrack();
        $song->entityId = $entityId;
        $song->videoId = $videoId;
        $song->title = $title;
        $song->duration = $duration;
        $song->duration_seconds = parse_duration($duration);
        $song->artists = parse_song_artists($data, 1);
        $song->album = parse_song_album($data, 2);
        $song->likeStatus = $like;
        $song->thumbnails = $thumbnails;
        $song->isAvailable = true;

        $songs[] = $song;
    }

    return $songs;
}
