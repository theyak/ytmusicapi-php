<?php

namespace Ytmusicapi;

class VideoDetails
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
     * @var string
     */
    public $lengthSeconds;

    /**
     * @var string
     */
    public $channelId;

    /**
     * @var bool
     */
    public $isOwnerViewing;

    /**
     * @var bool
     */
    public $isCrawlable;

    /**
     * @var ThumbnailCollection
     */
    public $thumbnail;

    /**
     * @var bool
     */
    public $allowRatings;

    /**
     * @var string
     */
    public $viewCount;

    /**
     * @var string
     */
    public $author;

    /**
     * @var bool
     */
    public $isPrivate;

    /**
     * @var bool
     */
    public $isUnpluggedCorpus;

    /**
     * @var string
     */
    public $musicVideoType;

    /**
     * @var bool
     */
    public $isLiveContent;

}
