<?php

namespace Ytmusicapi;

class Account
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
     * @var Thumbnail[]
     */
    public $thumbnails;

    /**
     * Flag indicating whether the account is a premium user.
     * @var bool
     */
    public $is_premium;
}
