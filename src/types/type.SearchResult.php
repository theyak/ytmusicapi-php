<?php

namespace Ytmusicapi;

class SearchResult
{
    /**
     * Category of search result. Generally one of:
     * `Top results`, `More from YouTube`, `Songs`, `Videos`, `Albums`, `Artists`, `Playlists`, `Community playlists`, `Featured playlists`, `Uploads`
     * @var string
     */
    public $category;

    /**
     * Type of search result. One of: `album`, `artist`, `playlist`, `song`, `video`, `station`, `profile`, `podcast`, `episode`
     * @var string
     */
    public $resultType;

    /**
     * Title of the search result. Not valid for resultType = 'artist'
     * @var string
     */
    public $title;

    /**
     * Release data. Seems to only be valid for an uploaded album, and even then
     * only in certain situations, so don't count on this existing.
     * @var string
     */
    public $releaseDate;

    /**
     * Name of artist. Valid only for resultType = 'artist' or 'album' if an upload
     * @var string
     */
    public $artist;

    /**
     * Artists. Valid for resultType = 'song', 'video', and 'album'
     * @var TrackArtist[]
     */
    public $artists;

    /**
     * ID for shuffling the artist's songs. Valid only for resultType = 'artist'
     * @var string
     */
    public $shuffleId;

    /**
     * ID for making an artist station. Valid only for resultType = 'artist'
     * @var string
     */
    public $radioId;

    /**
     * Type of Album (Album or Single). Valid only for resultType = 'album'
     * @var string
     */
    public $type;

    /**
     * Number of songs in the playlist. May not be 100% accurate as videos/songs get deleted. Valid only for resultType = 'playlist'
     * @var string
     */
    public $itemCount;

    /**
     * Author of the playlist. Valid only for resultType = 'playlist'
     * @var string
     */
    public $author;

    /**
     * Video ID. Valid for resultType = 'song', 'video', 'station', 'album'
     * @var string
     */
    public $videoId;

    /**
     * Video type. Usually 'MUSIC_VIDEO_TYPE_ATV' for songs and other things for videos. Valud for song, video, album
     * @var string
     */
    public $videoType;

    /**
     * Playlist ID. Valid only for resultType = 'station' and 'song'. For 'song', item must be an upload.
     * @var string
     */
    public $playlistId;

    /**
     * Name of user. Valid only	for resultType = 'profile'
     * @var string
     */
    public $name;

    /**
     * Album object. Valid only for resultType = 'song' and 'video'
     * @var Ref
     */
    public $album;

    /**
     * Flag indicating if song is in library. Valid only for resultType = 'song'
     * @var bool
     */
    public $inLibrary;

    /**
     * Feedback tokens. Valid only for resultType = 'song'
     * @var object{add: string, remove: string}
     */
    public $feedbackTokens;

    /**
     * Browse ID. Valid for resultType = 'upload', 'artist', 'album', 'playlist', and 'profile'
     * @var string
     */
    public $browseId;

    /**
     * Number of views. Valid for resultType = 'song' and 'video'
     * @var string
     */
    public $views;

    /**
     * Duration of the song or video. Valid for resultType = 'song' and 'video'
     * @var string
     */
    public $duration;

    /**
     * Duration of the song or video in seconds. Valid for resultType = 'song' and 'video'
     * @var int
     */
    public $duration_seconds;

    /**
     * Year of release. Often does not have a value, especially for songs and videos. Valid for resultType = 'song', 'video', and 'album'
     * @var string
     */
    public $year;

    /**
     * Flag indicating if item is explicit. Valid for resultType = 'song' and 'album'
     */
    public $isExplicit;

    /**
     * Thumbnails for item
     * @var Thumbnail[]
     */
    public $thumbnails;
}
