<?php

namespace Ytmusicapi;

/**
 * Limited token. Does not provide a refresh token. Commonly obtained via a token refresh.
 */
class BaseTokenDict
{
    /**
     * @var string
     */
    public $access_token;

    /**
     * @var int
     */
    public $expires_in;

    /**
     * @var string
     */
    public $scope = "https://www.googleapis.com/auth/youtube";

    /**
     * @var string
     */
    public $token_type = "Bearer";
}

/**
 * Entire token. Including refresh. Obtained through token setup.
 */
class RefreshableTokenDict extends BaseTokenDict
{
    /**
     * @var int
     * UNIX epoch timestamp in seconds
     */
    public $expires_at;

    /**
     * @var string
     * string used to obtain new access token upon expiration
     */
    public $refresh_token;
}

/**
 * Keys for the json object obtained via code response during auth flow.
 */
class AuthCodeDict
{
    /**
     * @var string
     * code obtained via user confirmation and oauth consent
     */
    public $device_code;

    /**
     * @var string
     * alphanumeric code user is prompted to enter as confirmation. formatted as XXX-XXX-XXX.
     */
    public $user_code;

    /**
     * @var int
     * seconds from original request timestamp
     */
    public $expires_in;

    /**
     * @var int
     * (?) "5" (?)
     */
    public $interval;

    /**
     * @var string
     * base url for OAuth consent screen for user signin/confirmation
     */
    public $verification_url;
}
