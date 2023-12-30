<?php

namespace Ytmusicapi;

class AdaptiveFormat
{
    /**
     * @var int
     */
    public $itag;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $mimeType;

    /**
     * @var int
     */
    public $bitrate;

    /**
     * @var Range
     */
    public $initRange;

    /**
     * @var Range
     */
    public $indexRange;

    /**
     * @var string
     */
    public $lastModified;

    /**
     * @var string
     */
    public $contentLength;

    /**
     * @var string
     */
    public $quality;

    /**
     * @var string
     */
    public $projectionType;

    /**
     * @var int
     */
    public $averageBitrate;

    /**
     * @var bool
     */
    public $highReplication;

    /**
     * @var string
     */
    public $audioQuality;

    /**
     * @var string
     */
    public $approxDurationMs;

    /**
     * @var string
     */
    public $audioSampleRate;

    /**
     * @var int
     */
    public $audioChannels;

    /**
     * @var float
     */
    public $loudnessDb;

}
