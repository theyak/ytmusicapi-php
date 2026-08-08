<?php

namespace Ytmusicapi;

use WpOrg\Requests\Utility\CaseInsensitiveDictionary as CaseInsensitiveDict;

/**
 * Parse authentication string or array into headers and optional file path
 *
 * @param string|array $auth user-provided auth string or array
 * @return array [CaseInsensitiveDict headers, string|null auth_path]
 */
function parse_auth_str($auth)
{
    $auth_path = null;

    if (is_string($auth)) {

        $auth_str = $auth;
        if (str_starts_with($auth, "{")) {
            $input_json = json_decode($auth_str, true);
            if ($input_json === null) {
                throw new YTMusicUserError("Invalid auth JSON string or file path provided.");
            }
        } elseif (file_exists($auth_str)) {
            $auth_path = $auth_str;
            $json_content = file_get_contents($auth_path);
            $input_json = json_decode($json_content, true);
            if ($input_json === null) {
                throw new YTMusicUserError("Invalid auth JSON string or file path provided.");
            }
        } else {
            throw new YTMusicUserError("Invalid auth JSON string or file path provided.");
        }
        $auth = array_merge(initialize_headers(), $input_json);
        $headers = new CaseInsensitiveDict($input_json);
    } else {
        $auth = array_merge(initialize_headers(), (array)$auth);
        $headers = new CaseInsensitiveDict($auth);
    }

    // URLEncode unicode charaters
    foreach ($headers as $key => $header) {
    $headers[$key] = preg_replace_callback(
        '/[^\x00-\x7F]/',
        static fn(string $match): string => rawurlencode($match),
        $header
    );

    return [$headers, $auth_path];
}

/**
 * Determine the type of auth based on auth headers.
 *
 * @param CaseInsensitiveDict $auth_headers auth headers dict
 * @return AuthType constant
 */
function determine_auth_type($auth_headers)
{
    $auth_type = AuthType::OAUTH_CUSTOM_CLIENT;

    if (OAuthToken::is_oauth($auth_headers)) {
        $auth_type = AuthType::OAUTH_CUSTOM_CLIENT;
    }

    $cookie = $auth_headers->offsetExists("cookie") ? $auth_headers["cookie"] : null;
    $authorization = $auth_headers->offsetExists("authorization") ? $auth_headers["authorization"] : null;

    if ($authorization) {
        if (str_contains($authorization, "SAPISIDHASH")) {
            $auth_type = AuthType::BROWSER;
        } elseif (str_starts_with($authorization, "Bearer")) {
            $auth_type = AuthType::OAUTH_CUSTOM_FULL;
        }
    } else if ($cookie) {
        if (str_contains($cookie, "__Secure-3PAPISID") && str_contains($cookie, "SAPISID=")) {
            $auth_type = AuthType::BROWSER;
        }
    }

    return $auth_type;
}