<?php

namespace Ytmusicapi;

class AlbumList
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var AlbumInfo[]
     */
    public $results;

    /**
     * @var string
     */
    public $params;
}

#[\AllowDynamicProperties]
class AlbumInfo
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var string
     */
    public $playlistId;

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
     * "Album" or "Single"
     */
    public $type;

    /**
     * Artists for album. Not available for get_artist_albums()
     * since YouTube assume you know which artist you are
     * looking at.
     * @var Ref[]
     */
    public $artists;

    /**
     * @var string
     */
    public $year;

    /**
     * @var boolean
     */
    public $isExplicit;
}

class PlaylistList
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var PlaylistInfo[]
     */
    public $results;

    /**
     * @var string
     */
    public $params;
}

#[\AllowDynamicProperties]
class PlaylistInfo
{
    /**
     * @var string
     */
    public $resultType = "playlist";

    /**
     * @var string
     */
    public $title = "";

    /**
     * @var string
     */
    public $playlistId = "";

    /**
     * @var Thumbnail[]
     */
    public $thumbnails = [];

    /**
     * @var string
     */
    public $description = "";

    /**
     * @var string
     */
    public $count = 0;

    /**
     * @var Ref[]
     */
    public $author = [];
}

class ArtistVideos
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var VideoInfo[]
     */
    public $results;

    /**
     * @var string
     */
    public $params;
}

#[\AllowDynamicProperties]
class VideoInfo
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $videoId;

    /**
     * @var TrackArtist[]
     */
    public $artists;

    /**
     * @var string
     */
    public $playlistId;

    /**
     * @var Thumbnails[]
     */
    public $thumbnails;

    /**
     * @var string
     */
    public $views;
}

class SingleList
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var SingleInfo[]
     */
    public $results;

    /**
     * @var string
     */
    public $params;
}

#[\AllowDynamicProperties]
class SingleInfo
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var Track[]
     */
    public $results;
}

class RelatedList
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var RelatedInfo[]
     */
    public $results;

    /**
     * @var string
     */
    public $params;
}

#[\AllowDynamicProperties]
class RelatedInfo
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $browseId;

    /**
     * @var string
     */
    public $subscribers;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;
}

class SongList
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var Track[]
     */
    public $results;
}

class ArtistInfo
{
    /**
     * @var string
     */
    public $browseId;

    /**
     * @var string
     */
    public $artist;

    /**
     * @var string
     */
    public $shuffleId;

    /**
     * @var string
     */
    public $radioId;

    /**
     * Number of songs by artist in your library. Used in get_library_artists()
     * @var int
     */
    public $songs;

    /**
     * Number of subscribers to artist. Used in get_library_subscriptions()
     * @var int
     */
    public $subscribers;

    /**
     * @var Thumbnail[]
     */
    public $thumbnails;
}
