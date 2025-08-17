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