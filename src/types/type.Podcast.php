<?php

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
