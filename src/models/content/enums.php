<?php

namespace Ytmusicapi;

class PrivacyStatus {
	const PUBLIC = "PUBLIC";
	const PRIVATE = "PRIVATE";
	const UNLISTED = "UNLISTED";
}

class LikeStatus {
	const LIKE = "LIKE";
	const DISLIKE = "DISLIKE";
	const INDIFFERENT = "INDIFFERENT";
}

class VideoType {
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

enum VoteStatus: string
{
	case UPVOTED = "VOTE_STATUS_UPVOTED";
	case DOWNVOTED = "VOTE_STATUS_DOWNVOTED";
	case UPSPECIFIED = "VOTE_STATUS_UNSPECIFIED";
}

enum PlaylistVoteEditOptions: string
{
    case EVERYONE_CAN_VOTE = 'EVERYONE_CAN_VOTE';
    case COLLABORATORS_ONLY = 'COLLABORATORS_ONLY';
    case OFF = 'OFF';

    public function getArgumentForRequest(): int
    {
        return match ($this) {
            self::EVERYONE_CAN_VOTE => 1,
            self::COLLABORATORS_ONLY => 2,
            self::OFF => 3,
        };
    }
}

class ResponseStatus
{
	const SUCCEEDED = "STATUS_SUCCEEDED";
}
