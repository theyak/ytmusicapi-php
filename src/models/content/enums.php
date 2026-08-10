<?php

namespace Ytmusicapi;

enum PrivacyStatus: string {
	case PUBLIC = "PUBLIC";
	case PRIVATE = "PRIVATE";
	case UNLISTED = "UNLISTED";
}

enum LikeStatus: string {
	case LIKE = "LIKE";
	case DISLIKE = "DISLIKE";
	case INDIFFERENT = "INDIFFERENT";
}

enum VideoType: string {
	case OMV = "MUSIC_VIDEO_TYPE_OMV";
	case UGC = "MUSIC_VIDEO_TYPE_UGC";
	case ATV = "MUSIC_VIDEO_TYPE_ATV";
	case OFFICIAL_SOURCE_MUSIC = "MUSIC_VIDEO_TYPE_OFFICIAL_SOURCE_MUSIC";
}

enum PlaylistSortOrder: int {
	case MANUAL = 0;
	case NEWEST_FIRST = 1;
	case NEWEST_LAST = 2;
	case TOP_VOTED = 6;
}

enum VoteStatus: string {
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
