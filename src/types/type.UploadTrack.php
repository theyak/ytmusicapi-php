<?php

namespace Ytmusicapi;

class UploadTrack
{
	/**
	 * @var string
	 */
	public $entityId;

    /**
     * @var string
     */
    public $videoId;

    /**
     * @var string
     */
    public $title;

    /**
     * @var TrackArtist[]
     */
    public $artists;

    /**
     * @var Ref
     */
    public $album;

    /**
     * @var string
     */
    public $likeStatus;

    /**
     * @var Thumbnails[]|null
     */
    public $thumbnails;

    /**
     * @var string
     */
    public $duration;

    /**
     * @var int
     */
    public $duration_seconds;

    /**
     * @var bool
     */
    public $isAvailable;
}
