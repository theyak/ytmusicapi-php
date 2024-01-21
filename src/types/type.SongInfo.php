<?php

namespace Ytmusicapi;

class SongInfo
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
     * @var string
     */
    public $author;

    /**
     * @var string
     */
    public $description;

    /**
     * @var int
     */
    public $viewCount;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;

    /**
     * @var string[]
     */
    public $tags;

    /**
     * @var string
     */
    public $videoType;

    /**
     * @var int
     */
    public $duration_seconds;

    /**
     * @var string
     */
    public $duration;

    /**
     * Flag indicating if this video is a music video.
     * @var bool
     */
    public $music;

    /**
     * Known possible values:
     *   PLAYBACK_MODE_PAUSED_ONLY (seems set for songs for kids)
     *   PLAYBACK_MODE_ALLOW
     * @var string
     */
    public $playbackMode;

    /**
     * @var bool
     * Flag indicating if this video can be embeded in third party website
     */
    public $canEmbed;

    /**
     * @var string
     */
    public $toast;

    /**
     * Flag indicating if content was made for kids.
     * Note that YouTube Music is very bad at labeling these tracks.
     * There are many labeled for kids that are not for kids and
     * there are many that are for kids that aren't labeled as such.
     * @var bool
     */
    public $madeForKids;
}
