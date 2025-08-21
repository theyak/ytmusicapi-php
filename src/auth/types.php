<?php

namespace Ytmusicapi;

/**
 * enum representing types of authentication supported by this library.
 *
 * Sorry, since we want to support PHP 7.0+, we can't use enum type :(
 */
class AuthType
{
    public const UNAUTHORIZED = 0;
    public const BROWSER = 1;

    // YTM instance is using a non-default OAuth client (id & secret)
    public const OAUTH_CUSTOM_CLIENT = 3;

    // allows fully formed OAuth headers to ignore browser auth refresh flow
    public const OAUTH_CUSTOM_FULL = 4;
}
