<?php

namespace Ytmusicapi;

use WpOrg\Requests\Utility\CaseInsensitiveDictionary as CaseInsensitiveDict;

// This gets used before tests begin, so we can't test them directly
// @codeCoverageIgnoreStart
function include_all($dir)
{
    $files = scandir(__DIR__ . "/" . $dir);
    foreach ($files as $file) {
        if (str_ends_with($file, ".php")) {
            include_once $dir . "/" . $file;
        }
    }
}
// @codeCoverageIgnoreEnd

function sum_total_duration($item)
{
    if (!isset($item->tracks)) {
        return 0;
    }

    $sum = 0;
    foreach ($item->tracks as $track) {
        if (isset($track->duration_seconds) && ($track->isAvailable || $track instanceof UploadTrack)) {
            $sum += (int)$track->duration_seconds;
        }
    }

    return $sum;
}

function initialize_headers(): array
{
    return [
        "user-agent" => USER_AGENT,
        "accept" => "*/*",
        "accept-encodng" => "gzip, deflate",
        "content-type" => "application/json",
        "content-encoding" => "gzip",
        "origin" => YTM_DOMAIN,
    ];
}

/**
 * @return object
 */
function initialize_context()
{
    return (object)[
        "client" => (object)[
            "clientName" => "WEB_REMIX",
            "clientVersion" => "1." . gmdate("Ymd") . ".01.00"
        ],
        "user" => (object)[]
    ];
}

// @codeCoverageIgnoreStart
function get_visitor_id(callable $request_func): string
{
    $response = $request_func(YTM_DOMAIN);
    preg_match('/ytcfg\\.set\\s*\\(\\s*({.+?})\\s*\\)\\s*;/', $response, $matches);

    if (count($matches) > 0) {
        $ytcfg = json_decode($matches[1]);
        return $ytcfg->VISITOR_DATA;
    }
    return "";
}
// @codeCoverageIgnoreEnd


/**
 * Convert key => value headers to a form that can be used by PHP external requests.
 *
 * @param array $headers
 */
function convert_headers_to_array($headers)
{
    if (array_is_list($headers)) {
        return $headers;
    }

    $headers = (array)$headers;

    $result = [];
    foreach ($headers as $key => $value) {
        $result[] = "$key: $value";
    }

    return $result;
}

/**
 * Converts a key => value array of cookies to a cookie string that can
 * be used by PHP external requests.
 *
 * @param array $cookies
 * @return string
 */
function convert_cookies_to_string($cookies)
{
    if (is_string($cookies)) {
        return $cookies;
    }

    $result = [];
    foreach ($cookies as $key => $value) {
        $result[] = "{$key}={$value}";
    }

    return implode("; ", $result);
}

/**
 * Converts a string of cookies to key => value pairs.
 *
 * @param string $cookiesStr The cookie string
 * @return CaseInsensitiveDict
 */
function convert_string_to_cookies($cookiesStr)
{
    if ($cookiesStr instanceof CaseInsensitiveDict) {
        return $cookiesStr;
    }

    if (is_array($cookiesStr)) {
        return new CaseInsensitiveDict($cookiesStr);
    }

    if (is_object($cookiesStr)) {
        return new CaseInsensitiveDict((array)$cookiesStr);
    }

    $cookiesStr = trim($cookiesStr);

    if (!$cookiesStr || strpos($cookiesStr, "=") === false) {
        return new CaseInsensitiveDict([]);
    }

    $cookies = new CaseInsensitiveDict([]);

    if (str_starts_with(strtolower($cookiesStr), "cookie: ")) {
        $cookiesStr = substr($cookiesStr, 8);
    }

    $cookiesStr = trim($cookiesStr);

    $split = explode("; ", $cookiesStr);

    foreach ($split as $value) {
        [$key, $val] = explode("=", $value);
        $cookies[trim($key)] = trim($val);
    }

    return $cookies;
}

/**
 * @param mixed $object
 */
function json_dump($object): void
{
    file_put_contents("dump.json", json_encode($object, JSON_PRETTY_PRINT));
}

/**
 * @param string $raw_cookie
 */
function sapisid_from_cookie($raw_cookie): ?string
{
    $cookies = convert_string_to_cookies($raw_cookie);
    return $cookies["__Secure-3PAPISID"];
}

/**
 * Get authorization header for YouTube Music.
 * 
 * @param string $sapisid
 * @return string
 */
function get_authorization($sapisid): string
{
    $timestamp = time();
    $sha1 = sha1("{$timestamp} {$sapisid}");
    $authorization = "SAPISIDHASH {$timestamp}_{$sha1}";
    return $authorization;
}
