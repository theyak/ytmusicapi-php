<?php

namespace Ytmusicapi;

class User
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $channelId;

    /**
     * @var ArtistSongs
     */
    public $songs;

    /**
     * @var AlbumList
     */
    public $albums;

    /**
     * @var SingleList
     */
    public $singles;

    /**
     * @var PlaylistList
     */
    public $playlists;

    /**
     * @var VideoList
     */
    public $videos;

    /**
     * @var RelatedList
     */
    public $related;
}
