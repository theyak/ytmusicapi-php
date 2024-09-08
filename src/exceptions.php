<?php
/**
 * Custom exception classes for ytmusicapi
 */

namespace Ytmusicapi;

/**
 * Base error class
 *
 * shall only be raised if none of the subclasses below are fitting
 */
class YTMusicError extends \Exception
{
}

/**
 * error caused by invalid usage of ytmusicapi
 */
class YTMusicUserError extends YTMusicError
{
}

/**
 * error caused by the YouTube Music backend
 */
class YTMusicServerError extends YTMusicError {
}

