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
    use Library;
    use Playlists;
    use Uploads;
    use Podcasts;

    public $_base_headers;
    public $_headers;
    public $_token;
    public $_session;
    public $_input_dict;
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
        $this->_base_headers = null; // for authless initializing requests during OAuth flow
        $this->_headers = null; // cache formed headers including auth

        $this->auth = $auth; // raw auth
        $this->_input_dict = new CaseInsensitiveDict([]); // parsed auth arg value in dictionary format

        $this->auth_type = AuthType::UNAUTHORIZED;
        $this->proxies = $proxies;

        if ($requests_session && $requests_session instanceof \WpOrg\Requests\Session) {
            $this->_session = $requests_session;
        } else {
            $this->_session = new \WpOrg\Requests\Session();
            $this->_session->options["timeout"] = 30;
            // I don't know why we don't do proxies here or just leave it out
            // and require a session to be passed in.
        }

        // see google cookie docs: https://policies.google.com/technologies/cookies
        //value from https://github.com/yt-dlp/yt-dlp/blob/master/yt_dlp/extractor/youtube.py#L502
        $this->cookies = ["SOCS" => "CAI"];
        if ($this->auth) {
            $this->oauth_credentials = $oauth_credentials ?: new OAuthCredentials();
            $auth_filepath = null;
            if (is_string($this->auth)) {
                $auth_str = $this->auth;
                if (is_file($auth_str)) {
                    $auth_filepath = $auth_str;
                    $input_json = json_decode(file_get_contents($auth_str));
                } else {
                    $input_json = json_decode($auth_str);
                }

                $this->_input_dict = new CaseInsensitiveDict((array)$input_json);
            } else {
                $this->_input_dict = new CaseInsensitiveDict($this->auth);
            }

            if (OAuthToken::is_oauth($this->_input_dict)) {
                $this->_token = new RefreshingToken();
                $this->_token->setCredentials($this->oauth_credentials);
                foreach ($this->_input_dict->getAll() as $key => $value) {
                    $this->_token->$key = $value;
                }
                $this->_token->set_local_cache($auth_filepath, false);
                $this->_token->refresh_token();
                $this->auth_type = $oauth_credentials ? AuthType::OAUTH_CUSTOM_CLIENT : AuthType::OAUTH_DEFAULT;
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

        $auth_headers = $this->_input_dict["authorization"];

        if ($auth_headers) {
            if (str_contains($auth_headers, "SAPISIDHASH")) {
                $this->auth_type = AuthType::BROWSER;
            } elseif (str_starts_with($auth_headers, "Bearer")) {
                $this->auth_type = AuthType::OAUTH_CUSTOM_FULL;
            }
        } elseif (is_string($auth)) {
            // Check for cookie string passed in diretly
            if (strpos($auth, "__Secure-3PAPISID")) {
                $this->auth_type = AuthType::BROWSER;
                $this->_input_dict["cookie"] = $auth;
                $this->_input_dict["x-goog-authuser"] = $user;
                $this->_input_dict["origin"] = YTM_DOMAIN;
                $this->_input_dict["user-agent"] = USER_AGENT;
                $this->_input_dict["accept"] = "*/*";
                $this->_input_dict["accept-encoding"] = "gzip, deflate";
                $this->_input_dict["content-type"] = "application/json";
                $this->_input_dict["content-encodng"] = "gzip";
                unset($this->context->user->onBehalfOfUser);
            }
        }

        $this->params = YTM_PARAMS;

        if ($this->auth_type === AuthType::BROWSER) {
            $this->base_headers();
            $this->params .= YTM_PARAMS_KEY;
            $cookie = $this->_base_headers["cookie"] ?: "";

            $this->sapisid = sapisid_from_cookie($cookie);
            $this->origin = $this->_base_headers["origin"] ?? $this->_base_headers["x-origin"];

            if (!$this->sapisid) {
                throw new \Exception("Your cookie is missing the required value __Secure-3PAPISID");
            }
        }
    }

    public function base_headers()
    {
        if (!$this->_base_headers) {
            if (in_array($this->auth_type, [AuthType::BROWSER, AuthType::OAUTH_CUSTOM_FULL])) {
                $this->_base_headers = $this->_input_dict;
            } else {
                $this->_base_headers = [
                    "user-agent" => USER_AGENT,
                    "accept" => "*/*",
                    "accept-encoding" => "gzip, deflate",
                    "content-type" => "application/json",
                    "content-encoding" => "gzip",
                    "origin" => YTM_DOMAIN,
                ];
            }
        }

        return $this->_base_headers;
    }

    public function header()
    {
        // set on first use
        if (!$this->_headers) {
            $this->_headers = $this->base_headers();
        }

        // keys updated each use, custom oauth implementations left untouched
        if ($this->auth_type === AuthType::BROWSER) {
            $this->_headers["authorization"] = get_authorization($this->sapisid, $this->origin);
        } elseif (in_array($this->auth_type, AuthType::oauth_types()) && $this->auth_type !== AuthType::OAUTH_CUSTOM_FULL) {
            $this->_headers["authorization"] = $this->_token->as_auth();
            $this->_headers["X-Goog-Request-Time"] = strval(time());
        }

        if ($this->_headers instanceof CaseInsensitiveDict) {
            return $this->_headers->getAll();
        }

        return $this->_headers;
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
        $body = (object)$body;
        $body->context = $this->context;

        $options = [];
        if ($this->proxies) {
            $options["proxy"] = $this->proxies;
        }

        $response = $this->_session->post(
            YTM_BASE_API . $endpoint . $this->params . $additionalParams,
            $this->header(),
            json_encode($body),
            $options
        );

        $response_text = json_decode($response->body);

        if ($response->status_code >= 400) {
            $reason = $response_text->error->message ?? "Unknown error";
            $message = "Server returned HTTP " . $response->status_code . ": " . $reason . ".\n";
            $error = $response_text->error->message;
            throw new \Exception($message . $error);
        }

        return $response_text;
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

        $headers = $this->_headers ?: $this->base_headers();

        $response = $this->_session->get($url, $headers, $options);
        return $response->body;
    }

    /**
     * Checks if self has authentication
     */
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
