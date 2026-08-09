<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

include "TestCase.php";
uses(TestCase::class)->in('Feature');

require_once __DIR__ . '/Support/helpers.php';

// Confirm environment variables are set
$client_id = getenv("GOOGLE_CLIENT_ID");
$client_secret = getenv("GOOGLE_SECRET_ID");

// Warnings trigger errors
// set_error_handler(function (
//     int $severity,
//     string $message,
//     string $file,
//     int $line
// ): never {
//     throw new ErrorException($message, 0, $severity, $file, $line);
// });

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function ytmusic()
{
    static $yt;

    if ($yt) {
        return $yt;
    }

    $yt = new Ytmusicapi\YTMusic();

    return $yt;
}

function ytbrowser()
{
    static $yt;

    if ($yt) {
        return $yt;
    }

    $yt = new Ytmusicapi\YTMusic("browser.json");

    return $yt;
}

function ytauth()
{
    static $yt;

    if ($yt) {
        return $yt;
    }

    $client_id = getenv("GOOGLE_CLIENT_ID");
    $client_secret = getenv("GOOGLE_SECRET_ID");

    $credentials = new Ytmusicapi\OAuthCredentials($client_id, $client_secret);
    $yt = new Ytmusicapi\YTMusic("oauth.json", oauth_credentials: $credentials);

    return $yt;
}
