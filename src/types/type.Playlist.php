<?php

namespace Ytmusicapi;

class Playlist
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $privacy;

    /**
     * @var string
     */
    public $title;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;

    /**
     * @var string
     */
    public $description;

    /**
     * @var ?Ref
     */
    public $author;

    /**
     * @var string
     */
    public $year;

    /**
     * @var string
     */
    public $duration;

    /**
     * @var int
     */
    public $duration_seconds;

    /**
     * @var int
     */
    public $trackCount;

    /**
     * @var TrackSuggestion[]
     */
    public $suggestions;

    /**
     * @var RelatedPlaylist
     */
    public $related;

    /**
     * @var Track[]
     */
    public $tracks;

    /**
     * @var string
     */
    public $continuation;

    /**
     * @var Collaborators
     */
    public $collaborators;
}
