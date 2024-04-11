<?php
/**
 * Meta information about a Podcast as it appears in library or channel shelves.
 */

namespace Ytmusicapi;

class PodcastShelfItem
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var Ref
     */
    public $channel;

    /**
     * @var string
     */
    public $browseId;

    /**
     * @var string
     */
    public $podcastId;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;
}
