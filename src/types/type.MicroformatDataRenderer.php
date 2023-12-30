<?php

namespace Ytmusicapi;

class MicroformatDataRenderer
{
    /**
     * @var string
     */
    public $urlCanonical;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var Thumbnail
     */
    public $thumbnail;

    /**
     * @var string
     */
    public $siteName;

    /**
     * @var string
     */
    public $appName;

    /**
     * @var string
     */
    public $androidPackage;

    /**
     * @var string
     */
    public $iosAppStoreId;

    /**
     * @var string
     */
    public $iosAppArguments;

    /**
     * @var string
     */
    public $ogType;

    /**
     * @var string
     */
    public $urlApplinksIos;

    /**
     * @var string
     */
    public $urlApplinksAndroid;

    /**
     * @var string
     */
    public $urlTwitterIos;

    /**
     * @var string
     */
    public $urlTwitterAndroid;

    /**
     * @var string
     */
    public $twitterCardType;

    /**
     * @var string
     */
    public $twitterSiteHandle;

    /**
     * @var string
     */
    public $schemaDotOrgType;

    /**
     * @var bool
     */
    public $noindex;

    /**
     * @var bool
     */
    public $unlisted;

    /**
     * @var bool
     */
    public $paid;

    /**
     * @var bool
     */
    public $familySafe;

    /**
     * @var string[]
     */
    public $tags;

    /**
     * @var string[]
     */
    public $availableCountries;

    /**
     * @var object{"name": string, "externalChannelId": string, "youtubeProfileUrl": string}
     */
    public $pageOwnerDetails;

    /**
     * @var object{"externalVideoId": string, "durationSeconds": string, "durationIso8601": string}
     */
    public $videoDetails;

    /**
     * @var LinkAlternate[]
     */
    public $linkAlternates;

    /**
     * @var string
     */
    public $viewCount;

    /**
     * @var string
     */
    public $publishDate;

    /**
     * @var string
     */
    public $category;

    /**
     * @var string
     */
    public $uploadDate;
}
