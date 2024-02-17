<?php

namespace Ytmusicapi;

class Episode
{
    /**
     * @var Ref
     */
    public $author;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $date;

    /**
     * @var boolean
     */
    public $saved;

    /**
     * @var string
     */
    public $duration;

    /**
     * @var string
     */
    public $playlistId;

    /**
     * @var Podcasts\Description
     */
    public $description;

    /**
     * @var int
     */
    public $index;

    /**
     * @var string
     */
    public $videoId;

    /**
     * @var string
     */
    public $browseId;

    /**
     * @var string
     */
    public $videoType;
}
