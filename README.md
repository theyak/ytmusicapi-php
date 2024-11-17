# ytmusicapi-php

WARNING

Google has taken a harder stance against bots. Endpoints that may have
previously worked while not authenticated may now responsd with an error.
This seems especially true if you are running the software on a common
hosting provider such as AWS, Netlify, Vercel, Digital Ocean, or Linode.

Google has also modified or disabled OAuth authentication. [[1]](https://github.com/yt-dlp/yt-dlp/issues/3766) 
[[2]](https://github.com/sigma67/ytmusicapi/issues/676) so please use
cookie authentication.

## About

This is a port of the [ytmusicapi](https://github.com/sigma67/ytmusicapi)
package available for Python. It tries to stay as close to the original as
possible. All API function names, parameter names, and public methods have
been kept the same as in their original Python library. Keeping everything
the same should make it easy to reference their
[documentation](https://ytmusicapi.readthedocs.io/en/stable/index.html).
This package is currently feature compatable with YtMusicAPI v1.7.3.

## Requirements
This package was developed and tested in PHP 8.2 and the testing library
requires 8.1+ so I have not tested in anything lower than PHP 8.2.
That being said, I have tried to keep the code compatible with PHP 7.4+.

This package uses the [requests](https://requests.ryanmccue.info/)
package for communication with YouTube Music.

## Known Differences From Python Version

* The option to open the web browser from the command line is not available in OAuth setup.

* No support for locales or languages.

* You can pass a cookie string from your browser as the `$auth` parameter in the YTMusic constructor in the PHP version.

* Addition of get_account() function to get information about the authorized account. This was developed before the Python version's get_account_info() function.

* Addition of get_transcript() function, which is basically timestamped lyrics. Not all songs have this available.

* Addition of get_playlist_continuation() which allows pagniated results of tracks. Useful when wanting to provide a progress indicator while loading a playlist.

* Addition of get_song_info() to get basic information about a track, include if the track is a music video or not.

* Addition of get_track() to get regular track information about a track. This function is useful to get track information in the form of a Track type with the addition of IDs to get lyrics and related tracks.

* There are various minor differences throughout. They have been labeled in the code with "Known differences."

* Typehints were added for your text editor.


## Installation

```
composer require ytmusicapi/ytmusicapi
```

## Setup

To do anything specific with your own data, such as view private playlists
or edit your playlist, you will need to authenticate to YouTube Music.
There are several ways to authenticate:

### OAuth

OAuth is temporarily unavailable. Please see Browser section, below.

```
php vendor/bin/setup-ytmusicapi oauth
```

Go to the URL provided and go through Google's authorization procedure.
When complete, press enter. A file named _oauth.json_ will be created.
You can then use that file to authenticate to YouTube Music.

```php
$yt = new Ytmusicapi\YTMusic("oauth.json");
```

You can optionally specify a filename if you don't want to use the default _oauth.json_.

```
php vendor/bin/setup-ytmusicapi oauth --filename=youtubemusic.json
```

### Browser
Please note that this method requires Firefox to work correctly, and even then it's somewhat unreliable.

* Open a new tab
* Open the developer tools (Ctrl-Shift-I or Cmd-Shift-I) and select the “Network” tab
* Go to https://music.youtube.com and ensure you are logged in
* Find an authenticated POST request to a /browse or /next endpoint. The simplest way is to filter by `browse` or `next` using the search bar of the developer tools. If you don’t see the request, try scrolling down a bit or clicking on the library button in the top bar. If you still don't find a request, play a song and one should show up.
* Once you've found a request, right click on it, select **Copy Value** then **Copy Request Headers**.

Run the following command:

```
php vendor/bin/setup-ytmusicapi browser
```

Paste the headers and press enter twice to continue. A file named _browser.json_
will be created. You can then use that file to authenticate to YouTube Music.

```php
$yt = new Ytmusicapi\YTMusic("browser.json");
```

### Cookies

You can use your dev tools in your browser while on YouTube Music to fetch your cookie value.
In Network Tools, find a `browse` or `next` endpoint and click the _Headers_ option to
view the request headers. Copy your cookie value to the clipboard. Also make note of the
value of X-Goog-Authuser value. In your PHP script, you can then use the following:

```php
$cookie = "...Paste Cookie value from YouTube Music...";
$user = "...Value of X-Goog-Authuser from YouTube Music...";

$yt = new Ytmusicapi\YTMusic($cookie, $user);
```

### Manual Configuration File
Create a JSON file with the cookies and user value from headers found in the Network tab within developer tools.
You can do this by searching for a request to a `browse` or `next` endpoint from within YouTube Music. Once
found, click the `Headers` tab and copy/paste the `Cookie` and `X-Goog-Authuser` values into a JSON file which looks like:

```
{
    "cookie": "PASTE COOKIE VALUE HERE",
    "x-goog-authuser": "PASTE X-GOOG-AUTHUSER VALUE HERE"
}
```


## Examples
```php
// Fetch information about a song

require "vendor/autoload.php";

$yt = new Ytmusicapi\YTMusic();
$song = $yt->get_song('kJQP7kiw5Fk');
print_r($song);
```

```php
// Display tracks from liked music list, displaying a progress indicator.
// Since the liked music playlist is private, you need to be authenticated.
// This example requires PHP 8.0+ due to usage of named parameters.

$yt = new Ytmusicapi\YTMusic("oauth.json");

try {
    $playlist = $yt->get_playlist("LM", get_continuations: false);
    $tracks = $playlist->tracks;

    // This is the number of tracks YouTube says is in the playlist.
    // However, hidden or deleted tracks may make your actual
    // count a bit lower.
    $count = $playlist->track_count;

    while ($playlist->continuation) {
        echo "Loaded " . count($tracks) . " of approximately $count tracks\n";

        $playlist = $yt->get_playlist_continuation("LM", $playlist->continuation);

        $tracks = array_merge($tracks, $playlist->tracks);
    }
    echo "Loaded " . count($tracks) . PHP_EOL;

    foreach ($tracks as $track) {
        $artists = array_map(fn ($artist) => $artist->name, $track->artists);
        echo $track->title . " - " . implode(", ", $artists) . PHP_EOL;
    }
} catch (Exception $ex) {
    echo $ex->getMessage() . PHP_EOL;
}
```

```php
// Fetch information about a podcast episode

$yt = new Ytmusicapi\YTMusic();
$result = $yt->get_episode("nDnLSYCOg7E");

echo "Author: {$result->author->name}\n";
echo "Title: {$result->title}\n";
echo "Date: {$result->date}\n";
echo "Duration: {$result->duration}\n";
echo "Playlist ID: {$result->playlistId}\n";
echo "Description: {$result->description}\n";
```

## Usage

View the files in the _tests/Features_ to view example usage of many functions.


## Reference

The following functions have been implemented. A link to the original
python documentation is provided for reference. In addition, your
editor's Intellisense may be able to provide reference documentation.

#### Search
* [search($query, $filter = null, $scope = null, $limit = 20, $ignore_spelling = false)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.search)
* [get_search_suggestions($query, $detailed_runs = false)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_search_suggestions)

#### Browsing
* [get_home()](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_home)
* [get_artist($channelId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_artist)
* [get_album($browseId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_album)
* [get_artist_albums($browseId, $params)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_artist_albums)
* [get_album_browse_id($audioPlaylistId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_album_browse_id)
* [get_user($channelId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_user)
* [get_user_playlists($channelId, $params = null)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_user_playlists)
* [get_song($videoId, $signatureTimestamp)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_song)
* [get_song_related($browseId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_song)
* [get_lyrics($browseId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_lyrics)
* [get_taste_profile()](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_taste_profile)
* [set_taste_profile($artists, $taste_profile = null)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.set_taste_profile)
* get_account(): Account
* get_song_info(string|Song $videoId): SongInfo
* get_transcript($videoId): object[]

#### Explore
* [get_mood_categories()](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_mood_categories)
* [get_mood_playlists($params)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_mood_playlists)
* [get_charts($country = "ZZ")](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_charts)

#### Watch
* [get_watch_playlist($videoId = null, $playlistId = null, $limit = 25, $radio = false, $shuffle = false)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_watch_playlist)
* get_track(string $videoId): WatchTrack

#### Library
* [get_library_playlists($limit = 25)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_playlists)
* [get_library_songs($limit = 25, $validate_responses = false, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_songs)
* [get_library_albums($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_albums)
* [get_library_artists($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_artists)
* [get_library_subscriptions($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_subscriptions)
* [get_history()](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_history)
* [add_history_item($song)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.add_history_item)
* [remove_history_itmes($feedbackTokens)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.remove_history_items)
* [rate_song($videoId, $rating = "INDIFFERENT")](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.rate_song)
* [edit_song_library_status($feedbackTokens)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.edit_song_library_status)
* [rate_playlist($playlistId, $rating = "INDIFFERENT")](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.rate_playlist)
* [subscribe_artists($channelIds)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.subscribe_artists)
* [unsubscribe_artists($channelIds)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.unsubscribe_artists)
* [get_library_podcasts($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_podcasts)
* [get_library_channels($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_channels)
* [get_account_info()](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_account_info)


#### Playlists
* [get_playlist($playlistId, $limit = 100, $related = false, $suggestions_limit = 0, $get_continuations = true)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_playlist)
* get_playlist_continuation($playlistId, $token)
* [get_liked_songs($limit = 100)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_liked_songs)
* [create_playlist($title, $description, $privacy_status = "PRIVATE", $video_ids = null, $source_playlist = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.create_playlist)
* [edit_playlist($playlistId, $title = null, $description = null, $privacyStatus = null, $moveItem = null, $addPlaylistId = null, $addToTop = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.edit_playlist)
* [delete_playlist($playlistId)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.delete_playlist)
* [add_playlist_items($playlistId, $videoIds = null, $source_playlist = null, $duplicates = false)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.add_playlist_items)
* [remove_playlist_items($playlistId, $videos)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.remove_playlist_items)

#### Uploads
* [get_library_upload_songs($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_upload_songs)
* [get_library_upload_albums($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_upload_albums)
* [get_library_upload_artists($limit = 25, $order = null)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_upload_artists)
* [get_library_upload_artist($browseId, $limit = 25)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_upload_artist)
* [get_library_upload_album($browseId)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.get_library_upload_album)
* [upload_song($filepath)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.upload_song)
* [delete_upload_entity($entityId)](https://ytmusicapi.readthedocs.io/en/latest/reference.html#ytmusicapi.YTMusic.delete_upload_entity)

#### Podcasts
* [get_podcast($playlistId, $limit = 100)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_podcast)
* [get_episode($videoId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_episode)
* [get_channel($channelId)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_channel)
* [get_channel_episodes($channelId, $params)](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_channel_episodes)
* [get_episodes_playlist($playlist_id = "RDPN")](https://ytmusicapi.readthedocs.io/en/stable/reference.html#ytmusicapi.YTMusic.get_episodes_playlist)


### Credits

This library is based almost 100% on sigma67's ytmusicapi library for python. https://github.com/sigma67/ytmusicapi.
And when I say almost entirely, I mean it. Same function names, same parameter names, same variable names, same
logic except where PHP neccessitates different logic.

OpenAI's GPT-4 and GitHub CoPilot helped a lot in converting code from Python to PHP. It's not perfect, but it
certainly made things easier and faster. It wasn't very good at writing tests.
