<?php

namespace Ytmusicapi;

class Album extends Record
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $title;

    /**
     * @var bool
     */
    public $isExplicit;

    /**
     * @var string
     */
    public $type;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;

    /**
     * @var string
     */
    public $description;

    /**
     * @var {name: string, id: string}[]
     */
    public $artists;

    /**
     * @var string
     */
    public $year;

    /**
     * @var int
     */
    public $trackCount;

    /**
     * @var string
     */
    public $duration;

    /**
     * @var string
     */
    public $likeStatus;

    /**
     * @var string
     */
    public $audioPlaylistId;

    /**
     * @var Track[]
     */
    public $tracks;

    /**
     * @var int
     */
    public $duration_seconds;

    /**
     * Other versions of the album, if available.
     * @var \stdclass[]
     */
    public $other_versions = [];
}
