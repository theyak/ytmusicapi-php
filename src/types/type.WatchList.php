<?php

namespace Ytmusicapi;

class WatchList
{
    /**
     * @var Track[]
     */
    public $tracks;

    /**
     * ID of playlist the video is played from
     * @var string
     */
    public $playlistId;

    /**
     * ID of lyrics, to be passed to get_lyrics()
     * @var string
     */
    public $lyrics;

    /**
     * ID of related songs, to be passed to get_song_related()
     * @var string
     */
    public $related;
}
