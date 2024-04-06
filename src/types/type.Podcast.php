<?php
/**
 * The Podcast type represents information about a Podcast.
 * See the PodcastShelfItem type to get information about a Podcast listed on a channel or in your library.
 */

namespace Ytmusicapi;

class Podcast
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
    public $description;

    /**
     * @var boolean
     */
    public $saved;

    /**
     * @var Episode[]
     */
    public $episodes;
}
