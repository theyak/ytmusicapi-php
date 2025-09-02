# ytmusicapi-php

Please see https://theyak.github.io/ytmusicapi-php/ for latest documentation.
Note that the documentation is a work in progress.

WARNING

Google has taken a harder stance against bots. Endpoints that may have
previously worked while not authenticated may now responsd with an error.
This seems especially true if you are running the software on a common
hosting provider such as AWS, Netlify, Vercel, Digital Ocean, or Linode.


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

- The option to open the web browser from the command line is not available in OAuth setup.

- No support for locales or languages.

- You can pass a cookie string from your browser as the `$auth` parameter in the YTMusic constructor in the PHP version.

- Addition of get_account() function to get information about the authorized account. This was developed before the Python version's get_account_info() function.

- Addition of get_playlist_continuation() which allows pagniated results of tracks. Useful when wanting to provide a progress indicator while loading a playlist.

- Addition of get_song_info() to get basic information about a track, include if the track is a music video or not.

- Addition of get_track() to get regular track information about a track. This function is useful to get track information in the form of a Track type with the addition of IDs to get lyrics and related tracks.

- There are various minor differences throughout. They have been labeled in the code with "Known differences" or "Custom"

## Installation

```
composer require ytmusicapi/ytmusicapi
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

## Testing

To run tests:

1. Clone the repository
2. Setup browser authentication with `php src/setup_ytmusicapi browser`
3. Run `vendor/bin/pest -v`

You can run a single test file with `vendor/bin/pest -v tests/Features/testfile.php`

You can skip tests by adding `->skip()` to the end of a test function.

You can specify limited tests by adding `->only()` to the end of test functions.


### Code

If you look at the code, you will notice docblocks are generally used to define parameter and return types.
This allows greater flexibility in types and documenting what the parameters are for. It also prevents ugly
run-time wrong parameter type errors which can kill your entire application - PHP is very good at converting
types on the fly, so this is usually OK. When it's not OK, you'll usually receive another type of error, such
as unable to convert object to string.

### Credits

This library is based almost 100% on sigma67's ytmusicapi library for python. https://github.com/sigma67/ytmusicapi.
And when I say almost entirely, I mean it. Same function names, same parameter names, same variable names, same
logic except where PHP neccessitates different logic.

OpenAI's GPT-4 and GitHub CoPilot helped a lot in converting code from Python to PHP. It's not perfect, but it
certainly made things easier and faster. It wasn't very good at writing tests.
