<?php

namespace Ytmusicapi;

include "constants.php";
include "polyfills.php";
include "navigation.php";
include "continuations.php";
include "helpers.php";

// In an effort to stay as close as possible to sigma67's original code,
// we have used filenames that don't always match class names, we don't
// want to clutter the autoload_classmap, and we also have functions
// outside of classes, so we are bypassing PSR-4 autoloading.
// This is a quick and dirty way to load everything.
include_once "types/type.Record.php";
include_all("mixins");
include_all("parsers");
include_all("auth");
include_all("types");

class YTMusic
{
    use Browse;
    use Explore;
    use Library;
    use Playlists;
    use Search;
    use Uploads;
    use Watch;

    public $cookies = [];
    public $user;
    public $language = "en";
    public $auth;
    public $input;
    public $proxies;
    public $is_oauth_auth;
    public $is_browser_auth;
    public $session;
    public $headers;
    public $context;
    public $sapisid;
    public $lang = [];

    /**
     * Create a new instance to interact with YouTube Music.
     *
     * @param string $auth Optional. Provide a string or path to file. Authentication credentials
     *   are needed to manage your library. See setup() for how to fill in the correct credentials.
     *   Default: A default header is used without authentication.
     * @param string $user  Optional. Specify a user ID string to use in requests. This is needed
     *   if you want to send requests on behalf of a brand account. Otherwise the default account
     *   is used. You can retrieve the user ID by going to https://myaccount.google.com/brandaccounts
     *   and selecting your brand account. The user ID will be in the
     *   URL: https://myaccount.google.com/b/user_id/
     * @param \WpOrg\Requests\Session $requests_session A Requests session object.
     *   Default sessions have a request timeout of 30s, which produces a requests.exceptions.ReadTimeout.
     *   The timeout can be changed by passing your own Session object:
     *   ```php
     *   $session = new \WpOrg\Requests\Session();
     *   $session->options["timeout"] = 60;
     *   $ytm = YTMusic("oauth.json", "0", $seesion);
     *   ```
     * @param string|string[] $proxies Optional. IP address or list of addresses for proxies.
     * @param string $language (Not implemented yet) Optional. Can be used to change the language of returned data. English
     *   will be used by default. Available languages can be checked in the ytmusicapi/locales directory.
     * @param string $location (Not implemented yet) Optional. Can be used to change the location of the user. No location
     *   will be set by default. This means it is determined by the server. Available languages can
     *   be checked in the FAQ.
     */
    public function __construct($auth = null, $user = null, $requests_session = true, $proxies = [], $language = "en", $location = "")
    {

        $this->auth = $auth;
        $this->input = null;
        $this->proxies = $proxies;
        $this->is_oauth_auth = false;
        $this->session = null;
        $this->cookies = ["SOCS" => "CAI"];
        $this->user = $user;
        $this->language = $language;
        $this->lang = [];

        if ($auth) {
            $this->input = load_headers_file($auth);
            $this->input["filepath"] = $this->auth; // Needed to re-write file for oauth re-verifications
            $this->is_oauth_auth = is_oauth($this->input);
        }

        // Use requests library, if available
        if (class_exists("\WpOrg\Requests\Requests")) {
            if ($requests_session && $requests_session instanceof \WpOrg\Requests\Session) {
                $this->session = $requests_session;
            } else {
                $this->session = new \WpOrg\Requests\Session();
                $this->session->options["timeout"] = 30;
            }
        }

        $this->headers = prepare_headers($this->session, $proxies, $this->input);
        $this->context = initialize_context();

        // Is this needed? Everything seems to work without it
        // unless we are using mocks, in which case, nothing works.
        // It makes an additional call to YouTube, so I'll leave it
        // commented out for now.
        // if (empty($this->headers["x-goog-visitor-id"])) {
        //     $id = get_visitor_id([$this, "_send_get_request"]);
        //     $this->headers["x-goog-visitor-id"] = $id;
        // }

        // TODO: Language - this is done differently than python verion
        // and for the most part isn't implemented yet.
        if (is_file(__DIR__ . "/locales/{$this->language}.php")) {
            $this->lang = require "locales/{$this->language}.php";
        } else {
            $this->language = "en";
            $this->lang = [];
        }
        $this->context->client->hl = $this->language;

        // TODO: Localization
        $this->context->client->locale = "us";

        if ($user) {
            if (preg_match("/^\d+$/", $user)) {
                $this->headers["x-goog-authuser"] = $user;
            } else {
                $this->context->user->onBehalfOfUser = $user;
            }
        }

        $this->is_browser_auth = false;
        if (!empty($this->headers["cookie"])) {
            $this->cookies = convert_string_to_cookies($this->headers["cookie"]);
            if (empty($this->cookies["__Secure-3PAPISID"])) {
                throw new \Exception("Your cookie is missing the required value __Secure-3PAPISID");
            } else {
                $this->sapisid = $this->cookies["__Secure-3PAPISID"];
                $this->is_browser_auth = true;
            }
        }
    }

    /**
     * Sends a GET request to YouTube Music.
     *
     * @param string $url
     * @param array $params Query parameters that will be appended to the URL
     * @return object Result from YouTube Music.
     */
    public function _send_get_request($url, $params = null)
    {
        if ($params) {
            if (is_array($params)) {
                $params = http_build_query($params);
            }
            $separator = strpos($url, "?") === false ? "?" : "&";
            $url = $url . $separator . $params;
        }

        $options = [];
        if ($this->proxies) {
            $options["proxy"] = $this->proxies;
        }

        $headers = $this->headers;
        $headers["cookie"] = convert_cookies_to_string($this->cookies);

        $response = $this->session->get($url, $headers, $options);
        return $response->body;
    }

    /**
     * Sends a POST request to YouTube Music.
     *
     * @param string $endpoint The main YouTube Music endpoint to use
     * @param array $additional Additional query parameters to send with the request
     * @return object Result from YouTube Music.
     */
    public function _send_request($endpoint, $body, $additionalParams = "")
    {
        if ($this->is_oauth_auth) {
            $this->headers = prepare_headers(null, null, $this->input);
        }

        $params = YTM_PARAMS;
        if ($this->is_browser_auth) {
            $origin = $this->headers["origin"] ?? $this->headers["x-origin"];
            $this->headers["authorization"] = get_authorization($this->sapisid, $origin);
            $params .= YTM_PARAMS_KEY;
        }

        $body = (object)$body;
        $body->context = $this->context;
        $rawData = json_encode($body);

        $options = [];
        if ($this->proxies) {
            $options["proxy"] = $this->proxies;
        }

        if ($additionalParams && !str_starts_with($additionalParams, "&")) {
            $additionalParams = "&" . $additionalParams;
        }

        $url = YTM_BASE_API . $endpoint . $params . $additionalParams;

        $response = $this->session->post($url, $this->headers, $rawData, $options);

        if ($response->status_code >= 400) {
            $body = json_decode($response->body);
            $reason = $body->error->message ?? "Unknown error";
            $message = "Server returned HTTP " . $response->status_code . ": " . $reason;
            throw new \Exception($message, $response->status_code);
        }

        return json_decode($response->body);
    }

    private function _check_auth()
    {
        if (!$this->auth) {
            throw new \Exception("Please provide authentication before using this function");
        }
    }

    private function _($key)
    {
        return $this->lang[$key] ?? $key;
    }
}
