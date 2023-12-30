<?php

namespace Ytmusicapi;

/**
 * @param string|object|array $auth Name of file containing headers. May also be a JSON formatted string containing headers.
 * @return array
 */
function load_headers_file(string|object|array $auth): array
{
    if (is_array($auth)) {
        return $auth;
    }

    if (is_object($auth)) {
        return (array)$auth;
    }

    if (is_file($auth)) {
        return json_decode(file_get_contents($auth), true);
    }

    $data = json_decode($auth, true);
    if ($data) {
        return $data;
    }

    // Probably a cookie string
    // Not available in Python version of library
    if (strpos($auth, "; ")) {

        $cookies = convert_string_to_cookies($auth);
        if (!isset($cookies["__Secure-3PAPISID"])) {
            return [];
        }
        $headers = initialize_headers();
        $headers["cookie"] = $auth;
        $headers["x-goog-authuser"] = "0";
        $headers["authorization"] = get_authorization($cookies["__Secure-3PAPISID"]);
        return $headers;
    }

    return [];
}

/**
 * @param string $sapisid
 * @param string $origin
 * @return string
 */
function get_authorization(string $sapisid, string $origin = "https://music.youtube.com"): string
{
    $timestamp = time();
    $sha1 = sha1("{$timestamp} {$sapisid} {$origin}");
    $authorization = "SAPISIDHASH {$timestamp}_{$sha1}";
    return $authorization;
}


/**
 * Prepares authentication headers
 *
 * @param object $session Unused in PHP
 * @param array $proxies Unused in PHP
 * @param array|object|null $input Authentication headers.
 * @return array
 */
function prepare_headers($session = null, $proxies = [], $input = null): array
{
    if ($input) {
        if (is_oauth($input)) {
            $oauth = new YTMusicOAuth($session, $proxies);
            $headers = $oauth->load_headers($input, $input["filepath"]);
        } elseif (is_browser($input)) {
            $headers = $input;
        } elseif (is_custom_oauth($input)) {
            $headers = $input;
        } else {
            throw new \Exception(
                "Could not detect credential type. " .
                "Please ensure your oauth or browser credentials are set up correctly."
            );
        }
    } else {
        $headers = initialize_headers();
    }

    return array_change_key_case((array)$headers, CASE_LOWER);
}
