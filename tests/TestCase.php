<?php

use PHPUnit\Framework\TestCase as BaseTestCase;

// tests/TestCase.php
class TestCase extends BaseTestCase
{
    // Pick a video with millions of views and lyrics
    public $videoId = "Z85lxckrtzg";
    public $videoArtist = "Michael Jackson";
    public $videoTitle = "Thriller";
    public $videoArtistChannel = "UCoIOOL7QKuBhQHVKL8y7BEQ";
    public $videoDuration = "5:58";
    public $videoDurationSecods = 358;

    // Artist must be one with more than 100 albums.
    public $artistId = "UChgxarBUCnPJV871-46bJ2g";
    public $artistName = "Elvis Presley";

    // Album
    public $albumId = "MPREb_pyQa1mky9hE";
    public $albumTitle = "Abbey Road";
    public $albumArtist = "The Beatles";
    public $albumYear = "1969";

    // User Channel
    public $userChannel = "UCESWJ5hrZ-BDCi0nGTl6I4Q";
    public $userChannelName = "neiva chemite";

    public $watchPlaylistId = "RDAMPLOLAK5uy_l_fKDQGOUsk8kbWsm9s86n4-nZNd2JR8Q"; // Radio based on track
    public $albumPlaylistId = "OLAK5uy_ljFy7zMtWNGkrVfpMTfSB0N2ITT45v188"; // Essential Hits Woodstock Generation

    // Playlist should have more than 100 items.
    public $playlistId = "PLeh1tyWa_rcESTaCyXeTVJvFtfcDIQgKK";
    public $playlistTitle = "CINEMIX Radio Official Playlist - Film Music Station";
    public $playlistAuthor = "Cinemix Radio";
    public $playlistYear = "2023";

    // A playlist that you own. Store this value in the environment
    // variable OWN_PLAYLIST_ID.
    // Make sure you have at least a few items in it so that related
    // tracks can be loaded.
    public $ownPlaylistId;

    public function performThis(): void
    {
        $this->ownPlaylistId = getenv("OWN_PLAYLIST_ID");
    }
}

include "src/YTMusic.php";
