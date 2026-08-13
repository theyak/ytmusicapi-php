<?php

/**
 * Enum like structures.
 *
 * Made to keep things compatible with PHP 7.4 as enums weren't
 * available directly until PHP 8.1.
 */

namespace Ytmusicapi;

class BackedStringEnum {
	/**
	 * Get value from key name. Only useful if the key name is stored
	 * in a variable, otherwise just use className::KEY;
	 *
	 * @param string $key
	 * @return ?string
	 */
	public static function constant($key) {
		if (!str_contains($key, '::')) {
			$key = static::class . '::' . $key;
		}

		try {
			return constant($key);
		} catch (\Exception $ex) {
			return null;
		}
	}


	/**
	 * Return the full constant name from value.
	 * You could then feed this into the constant() function
	 * to turn around and get the value, w
	 *
	 * @param string $value
	 * @return ?string
	 */
	public static function tryFrom($value) {
		$constants = static::cases();

		foreach ($constants as $k => $v) {
			if ($value === $v) {
				return static::class . '::' . $k;
			}
		}

		return null;
	}

	/**
	 * @return array<string, string>
	 */
	public static function cases() {
        $reflection = new \ReflectionClass(static::class);
		return $reflection->getConstants();
	}
}

class PlaylistVoteEditOptions
{
    public const EVERYONE_CAN_VOTE = 'EVERYONE_CAN_VOTE';
    public const COLLABORATORS_ONLY = 'COLLABORATORS_ONLY';
    public const OFF = 'OFF';

	/**
	 * Get the value that needs to be sent to YouTube Music based
	 * on the value of the string passed in.
	 *
	 * @param string $value
	 */
    public static function getArgumentForRequest($value): int
    {
		$map = [
			self::EVERYONE_CAN_VOTE => 1,
			self::COLLABORATORS_ONLY => 2,
			self::OFF => 3,
		];

		return $map[$value] ?? null;
    }
}

class VoteStatus extends BackedStringEnum {
	public const UPVOTED = "VOTE_STATUS_UPVOTED";
	public const DOWNVOTED = "VOTE_STATUS_DOWNVOTED";
	public const UNSPECIFIED = "VOTE_STATUS_UNSPECIFIED";
}

class PrivacyStatus extends BackedStringEnum {
	const PUBLIC = "PUBLIC";
	const PRIVATE = "PRIVATE";
	const UNLISTED = "UNLISTED";
}

class LikeStatus extends BackedStringEnum {
	const LIKE = "LIKE";
	const DISLIKE = "DISLIKE";
	const INDIFFERENT = "INDIFFERENT";
}

class VideoType extends BackedStringEnum {
	const OMV = "MUSIC_VIDEO_TYPE_OMV";
	const UGC = "MUSIC_VIDEO_TYPE_UGC";
	const ATV = "MUSIC_VIDEO_TYPE_ATV";
	const OFFICIAL_SOURCE_MUSIC = "MUSIC_VIDEO_TYPE_OFFICIAL_SOURCE_MUSIC";
}

class PlaylistSortOrder {
	const MANUAL = 0;
	const NEWEST_FIRST = 1;
	const NEWEST_LAST = 2;
	const TOP_VOTED = 6;
}

class ResponseStatus
{
	const SUCCEEDED = "STATUS_SUCCEEDED";
}
