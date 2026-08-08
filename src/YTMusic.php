<?php

namespace Ytmusicapi;

include "constants.php";
include "exceptions.php";
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
include_all("models");
include_all("models/content");
include_all("mixins");
include_all("parsers");
include_all("auth");
include_all("auth/oauth");
include_all("types");

use WpOrg\Requests\Utility\CaseInsensitiveDictionary as CaseInsensitiveDict;

/**
 * Allows automated interactions with YouTube Music by emulating the YouTube web client's requests.
 * Permits both authenticated and non-authenticated requests.
 * Authentication header data must be provided on initialization.
 */
class YTMusic
{
    use Browse;
    use Search;
    use Watch;
    use Explore;
    use Charts;
    use Library;
    use Playlists;
    use Uploads;
    use Podcasts;
    use I18n;

    public $_token;
    public $_session;
    public $_input_dict;
    public $_auth_headers;
    public $_base_headers;
    public $auth_type;
    public $oauth_credentials;
    public $proxies;
    public $params;
    public $origin;

    public $cookies = [];
    public $language = "en";
    public $auth;
    public $headers;
    public $context;
    public $sapisid;
    public $lang = [];

    /**
     * Create a new instance to interact with YouTube Music.
     *
     * @param string $auth Optional. Provide a string, path to file, cookie string, or oauth token dict.
     *   Authentication credentials are needed to manage your library.
     *   See `setup()` for how to fill in the correct credentials.
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
     * @param string $oauth_credentials Optional. Used to specify a different oauth client to be
     *   used for authentication flow.
     *
     * Known differences from Python version:
     *   - The `language` and `location` parameters are not implemented yet.
     *   - Can pass in a cookie string directly as the `auth` parameter.
     */
    public function __construct(
        $auth = null,
        $user = null,
        $requests_session = true,
        $proxies = [],
        $language = "en",
        $location = "",
        $oauth_credentials = null
    ) {
        $this->_session = $this->_prepare_session($requests_session);
        $this->proxies = $proxies;

        // see google cookie docs: https://policies.google.com/technologies/cookies
        // value from https://github.com/yt-dlp/yt-dlp/blob/2023.09.24/yt_dlp/extractor/youtube.py#L502
        $this->cookies = "SOCS=CAI;";

        $this->_auth_headers = new CaseInsensitiveDict([]);
        $this->auth_type = AuthType::UNAUTHORIZED;

        if ($auth) {
            // Custom, pass in cookie string directly. A bit easier for Chrome users.
            // A valid cookie must contain both __Secure-3PAPISID, SAPISID, and SID

            if (is_string($auth) && str_starts_with($auth, "{") === false && strpos($auth, "__Secure-3PAPISID") !== false && strpos($auth, "SAPISID=") !== false) {
                $this->auth_type = AuthType::BROWSER;
                $this->_auth_headers = initialize_headers();
                $this->_auth_headers["cookie"] = $auth;
                $this->_auth_headers["x-goog-authuser"] = $user ?? "0";

                // Prevent brand account
                $user = "0";
            } else {
                [$this->_auth_headers, $auth_path] = parse_auth_str($auth);
                $this->auth_type = determine_auth_type($this->_auth_headers);

                if ($this->auth_type == AuthType::OAUTH_CUSTOM_CLIENT) {
                    if (!$oauth_credentials) {
                        $message = "oauth JSON provided via auth argument, but oauth_credentials not provided.\n";
                        $message .= "Please provide oauth_credentials as specified in the OAuth setup documentation.\n";
                        throw new YTMusicUserError($message);
                    }

                    # Filter unknown keys (e.g. ``refresh_token_expires_in`` from Google's
                    # device flow) so previously saved oauth.json files load cleanly. See #921.G
                    $token_kwargs = new CaseInsensitiveDict([]);
                    foreach (Token::members() as $key) {
                        if (isset($this->_auth_headers[$key])) {
                            $token_kwargs[$key] = $this->_auth_headers[$key];
                        }
                    }

                    $this->_token = new RefreshingToken(
                        $oauth_credentials, $auth_path, $token_kwargs
                    );
                }
            }
        }

        // Prepare context
        $this->context = initialize_context();

        // TODO: Location
        // TODO: Language

        $this->context->client->hl = "en";
        $this->language = "en";

        // For brand accounts
        if ($user) {
            $this->context->user->onBehalfOfUser = $user;
        }

        $this->params = YTM_PARAMS;

        if ($this->auth_type === AuthType::BROWSER) {
            $this->params .= YTM_PARAMS_KEY;

            $headers = $this->base_headers();
            $this->sapisid = sapisid_from_cookie($this->_auth_headers["cookie"]);
            $this->origin = $headers["origin"] ?? $headers["x-origin"];

            if (!$this->sapisid) {
                throw new YTMusicUserError("Your cookie is missing the required value __Secure-3PAPISID");
            }
        }
    }

    /**
     * Base headers are static. Once set, they are not changed.
     */
    public function base_headers()
    {
        if ($this->_base_headers) {
            return $this->_base_headers;
        }

        if ($this->auth_type === AuthType::BROWSER || $this->auth_type === AuthType::OAUTH_CUSTOM_FULL) {
            $this->_base_headers = $this->_auth_headers;
        } else {
            $this->_base_headers = initialize_headers();
        }

        if ($this->_base_headers instanceof CaseInsensitiveDict) {
            $this->_base_headers = $this->_base_headers->getAll();
        }

        // This caused all sorts of problems when using the direct credentials
        // The visitor ID only seems to be needed when calling get_user() followed
        // by get_user_videos(). Why do they make this so complicated?
        $keys = array_map(fn ($key) => strtolower($key), array_keys($this->_base_headers));
        if (!in_array("x-goog-visitor-id", $keys)) {
            $this->_base_headers["x-goog-visitor-id"] = get_visitor_id(fn ($url) => $this->_send_get_request($url, null, true));
        }

        return $this->_base_headers;
    }

    /**
     * Headers can change between requests, for instance if the oauth token
     * is expired and needs to be refreshed.
     */
    public function headers()
    {
        $headers = $this->base_headers();

        if ($this->auth_type === AuthType::BROWSER) {
            $headers["authorization"] = get_authorization($this->sapisid . " " . $this->origin);
        } else if ($this->auth_type === AuthType::OAUTH_CUSTOM_CLIENT) {
            $headers["authorization"] = $this->_token->as_auth();
            $headers["X-Goog-Request-Time"] = strval(time());
        }

        return $headers;
    }

    /**
     * Sends a POST request to YouTube Music using the mobile context.
     *
     * @param string $endpoint The main YouTube Music endpoint to use
     * @param array $body The body of the request
     * @return object Result from YouTube Music.
     */
    public function _send_mobile_request($endpoint, $body)
    {
        $copied_context_client = clone $this->context->client;
        $this->context->client->clientName = "ANDROID_MUSIC";
        $this->context->client->clientVersion = "7.21.50";

        try {
            $response = $this->_send_request($endpoint, $body);
        } finally {
            $this->context->client = $copied_context_client;
        }

        return $response;
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
        static $count = 1;
        $count++;

        // $response_text = file_get_contents("response-{$count}.json");
        // return json_decode($response_text);

        $body = (object)$body;
        $body->context = $this->context;

        $options = [];
        if ($this->proxies) {
            $options["proxy"] = $this->proxies;
        }

        $header = $this->headers();

        if ($header instanceof CaseInsensitiveDict) {
            $header = $header->getAll();
        }

        if (empty($header["cookie"])) {
            $header["cookie"] = $this->cookies;
        }

        $response = $this->_session->post(
            YTM_BASE_API . $endpoint . $this->params . $additionalParams,
            $header,
            json_encode($body),
            $options
        );

        // file_put_contents("response-{$count}.json", $response->body);
        $response_text = json_decode($response->body);

        if ($response->status_code >= 400) {
            $reason = $response_text->error->message ?? "Unknown error";
            $message = "Server returned HTTP " . $response->status_code . ": " . $reason . ".\n";
            $error = $response_text->error->message;
            throw new YTMusicServerError($message . $error);
        }

        return $response_text;
    }

    /**
     * Sends a GET request to YouTube Music.
     *
     * @param string $url
     * @param array $params Query parameters that will be appended to the URL
     * @param bool $use_base_headers Whether to use the base headers or the headers from the last request.
     * @return object Result from YouTube Music.
     */
    public function _send_get_request($url, $params = null, $use_base_headers = false)
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

        if ($use_base_headers) {
            $headers =  initialize_headers();
        } else {
            $headers =  $this->headers();
        }

        if (empty($headers["cookie"])) {
            $headers["cookie"] = $this->cookies;
        }

        $response = $this->_session->get($url, $headers, $options);
        return $response->body;
    }

    /**
     * Checks if self has provided authentication credentials
     */
    private function _check_auth()
    {
        if ($this->auth_type === AuthType::UNAUTHORIZED) {
            throw new YTMusicUserError("Please provide authentication before using this function");
        }
    }

    private function _($key)
    {
        if ($key === "episodes") {
            return "Latest episodes";
        }

        return $this->lang[$key] ?? $key;
    }

    /**
     * Prepare requests session or use user-provided requests_session
     *
     * @param \WpOrg\Requests\Session $requests_session
     * @return \WpOrg\Requests\Session
     */
    private function _prepare_session($requests_session)
    {
        if ($requests_session && $requests_session instanceof \WpOrg\Requests\Session) {
            return $requests_session;
        }

        $this->_session = new \WpOrg\Requests\Session();
        $this->_session->options["timeout"] = 30;

        return $this->_session;
    }
}

