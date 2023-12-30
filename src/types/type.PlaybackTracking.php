<?php

namespace Ytmusicapi;

class PlaybackTracking
{
    /**
     * @var Url
     */
    public $videostatsPlaybackUrl;

    /**
     * @var Url
     */
    public $videostatsDelayplayUrl;

    /**
     * @var Url
     */
    public $videostatsWatchtimeUrl;

    /**
     * @var Url
     */
    public $ptrackingUrl;

    /**
     * @var Url
     */
    public $qoeUrl;

    /**
     * @var Url
     */
    public $atrUrl;

    /**
     * @var int[]
     */
    public $videostatsScheduledFlushWalltimeSeconds;

    /**
     * @var int
     */
    public $videostatsDefaultFlushIntervalSeconds;

}
