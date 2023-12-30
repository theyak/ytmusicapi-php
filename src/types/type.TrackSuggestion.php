<?php

namespace Ytmusicapi;

class TrackSuggestion
{
    /**
     * @var string
     */
    public $videoId;

    /**
     * @var string
     */
    public $title;

    /**
     * @var Artist[]
     */
    public $artists;

    /**
     * @var Album
     */
    public $album;

    /**
     * @var string
     */
    public $likeStatus;

    /**
     * @var ThumbnailCollection
     */
    public $thumbnails;

    /**
     * @var bool
     */
    public $isAvailable;

    /**
     * @var bool
     */
    public $isExplicit;

    /**
     * @var string
     */
    public $duration;

    /**
     * @var int
     */
    public $duration_seconds;

    /**
     * @var string
     */
    public $setVideoId;
}
