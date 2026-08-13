<?php

namespace Ytmusicapi;

/** @phpstan-import-type TextRun */
class Artist
{
    /**
     * @var string
     */
    public $description;

    /**
     * @var TextRun[]
     */
    public $descriptionRuns;

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
     * @var string
     */
    public $monthlyListeners;

    /**
     * @var bool
     */
    public $subscribed;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;

    /**
     * @var ArtistSongList[]
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
     * @var Podcast[]
     */
    public $podcasts;

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
