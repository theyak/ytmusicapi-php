<?php

namespace Ytmusicapi;

#[\AllowDynamicProperties]
class Track
{
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
     * @var bool
     */
    public $inLibrary;

    /**
     * @var Thumbnails[]
     */
    public $thumbnails;

    /**
     * @var bool
     */
    public $isAvailable;

    /**
     * @var bool
     */
    public $isExplicit;

    /**
     * @var string
     */
    public $duration;

    /**
     * @var int
     */
    public $duration_seconds;

    /**
     * @var string
     */
    public $videoType;

    /**
     * @var FeedbackTokens
     */
    public $feedbackTokens;

    /**
     * @var int
     */
    public $views;

    public static function from($object)
    {
        $properties = get_class_vars(static::class);
        $track = new static();
        foreach ($properties as $property => $value) {
            if (isset($object->$property)) {
                $track->$property = $object->$property;
            }
        }
        return $track;
    }
}

class AlbumTrack extends Track
{
    /**
     * @var int
     * Only available for albums
     */
    public $trackNumber;

    /**
     * @var string
     * Play count is only available for albums
     */
    public $playCount = "";
}

#[\AllowDynamicProperties]
class HistoryTrack extends Track
{
    /**
     * When history item was played
     * @var string
     */
    public $played;

    /**
     * Token to use to remove track from history. Weird name.
     * @var string
     */
    public $feedbackToken;
}

#[\AllowDynamicProperties]
class WatchTrack extends Track
{
    /**
     * This is the same as duration and provided for
     * compatability with Pyhton version of ytmusicapi.
     * @var string
     */
    public $length;

    /**
     * @var string
     */
    public $playlistId;

    /**
     * @var string
     */
    public $lyrics;

    /**
     * @var string
     */
    public $related;

    public static function from($object)
    {
        $track = parent::from($object);
        if (!empty($object->length)) {
            $track->duration = $object->length;
        }

        if (!empty($object->thumbnail)) {
            $track->thumbnails = $object->thumbnail;
        }

        $track->duration_seconds = parse_duration($track->duration);

        // Watch tracks are always available
        $track->isAvailable = true;

        return $track;
    }
}

#[\AllowDynamicProperties]
class PlaylistTrack extends Track
{
    /**
     * @var string
     */
    public $setVideoId;
}
