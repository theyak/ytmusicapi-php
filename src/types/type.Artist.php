<?php

namespace Ytmusicapi;

class Artist
{
    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $views;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $channelId;

    /**
     * @var string
     */
    public $shuffleId;

    /**
     * @var string
     */
    public $radioId;

    /**
     * @var string
     */
    public $subscribers;

    /**
     * @var bool
     */
    public $subscribed;

    /**
     * @var Thumbnails[]
     */
    public $thumbnails;

    /**
     * @var SongList
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
     * @var AlbumList
     */
    public $shows;

    /**
     * @var Episode[]
     */
    public $episodes;

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
