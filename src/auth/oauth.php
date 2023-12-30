<?php

namespace Ytmusicapi;

/**
 * Determine if authorization headers are valid for OAuth authentication
 *
 * @param object|array $headers
 * @return bool True if headers are valid, false otherwise
 */
function is_oauth($headers)
{
    $oauth_structure = [
        "access_token",
        "expires_at",
        "expires_in",
        "token_type",
        "refresh_token",
    ];

    if (is_object($headers)) {
        $array = get_object_vars($headers);
    } else {
        $array = $headers;
    }
    $properties = array_keys($array);
    $properties = array_map("strtolower", $properties);

    foreach ($oauth_structure as $property) {
        if (!in_array($property, $properties)) {
            return false;
        }
    }

    return true;
}

/**
 * Checks whether the headers contain a Bearer token, indicating a custom OAuth implementation.
 *
 * @param object $headers
 * @return bool
 */
function is_custom_oauth($headers)
{
    $headers = (object)$headers;
    return isset($headers->authorization) && str_starts_with($headers->authorization, "Bearer ");
}

/**
 * OAuth implementation for YouTube Music based on YouTube TV
 */
class YTMusicOAuth
{
    public function __construct($session = null, $proxies = null)
    {
    }

    private function post($url, $data, $headers)
    {
        $headers = convert_headers_to_array($headers);

        // Make post request using always available PHP methods. No curl required.
        $opts = [
            "http" => [
                "method" => "POST",
                "header" => $headers,
                "content" => json_encode($data),
            ]
        ];

        $annoying = error_reporting(E_ALL ^ E_WARNING);
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        error_reporting($annoying);

        preg_match("/HTTP\/\d\.\d\s(\d{3})/", $http_response_header[0], $matches);
        if (count($matches) > 1) {
            $code = (int)$matches[1];
        } else {
            $code = 200;
        }

        if ($code > 400) {
            throw new \Exception("HTTP error {$code}", $code);
        }

        return json_decode($result);
    }

    private function _send_request($url, $data)
    {
        $data = (object)$data;
        $data->client_id = OAUTH_CLIENT_ID;
        $headers = [
            "User-Agent: " . OAUTH_USER_AGENT,
            "Content-Type: application/json",
        ];
        return $this->post($url, $data, $headers);
    }

    /**
     * Get a device code for OAuth authentication
     *
     * @return object
     */
    public function get_code()
    {
        $response = $this->_send_request(OAUTH_CODE_URL, ["scope" => OAUTH_SCOPE]);
        return $response;
    }

    private static function _parse_token($response)
    {
        if (is_string($response)) {
            $response = json_decode($response, true);
        }
        $response = (array)$response;

        $response["expires_at"] = time() + $response["expires_in"];
        return $response;
    }

    /**
     * Get an OAuth token from a device code
     *
     * @param string $device_code
     * @return object
     */
    public function get_token_from_code($device_code)
    {
        $response = $this->_send_request(
            OAUTH_TOKEN_URL,
            [
                "client_secret" => OAUTH_CLIENT_SECRET,
                "grant_type" => "http://oauth.net/grant_type/device/1.0",
                "code" => $device_code,
            ]
        );

        return $this->_parse_token($response);
    }

    /**
     * Refresh an expired or nearly expired token
     *
     * @param string $refresh_token
     * @return object
     */
    public function refresh_token($refresh_token)
    {
        $response = $this->_send_request(
            OAUTH_TOKEN_URL,
            [
                "client_secret" => OAUTH_CLIENT_SECRET,
                "grant_type" => "refresh_token",
                "refresh_token" => $refresh_token,
            ]
        );
        return $this->_parse_token($response);
    }

    /**
     * Writes OAuth token data to file
     *
     * @param object $token
     * @param string $filepath
     */
    public static function dump_token($token, $filepath = null)
    {
        if (!$filepath || strlen($filepath) > 255) {
            return;
        }
        file_put_contents($filepath, json_encode($token, JSON_PRETTY_PRINT));
    }

    /**
     * Do the OAuth flow and write token data to file
     *
     * @param string $filepath
     * @param bool $open_browser (Unused in PHP - Sorry :( )
     * @return object
     */
    public function setup($filepath = null, $open_browser = false)
    {
        $code = $this->get_code();
        $url = $code->verification_url . "?user_code=" . $code->user_code;

        echo "Go to {$url}, finish the login flow and press Enter when done, Ctrl-C to abort";
        readline();

        $token = $this->get_token_from_code($code->device_code);
        $this->dump_token($token, $filepath);

        return $token;
    }

    /**
     * Load headers from a token.
     *
     * @param object $token
     * @param string $filepath
     * @return array
     */
    public function load_headers($token, $filepath = null)
    {
        $headers = [
            "user-agent" => USER_AGENT,
            "accept" => "*/*",
            "accept-encoding" => "gzip, deflate",
            "content-type" => "application/json",
            "content-encoding" => "gzip",
            "origin" => YTM_DOMAIN,
            "X-Goog-Request-Time" => time(),
        ];

        if (time() > $token["expires_at"] - 3600) {
            $token = array_merge($token, $this->refresh_token($token["refresh_token"]));
            $this->dump_token($token, $filepath);
        }

        $headers["Authorization"] = $token["token_type"] . " " . $token["access_token"];

        return $headers;
    }
}
