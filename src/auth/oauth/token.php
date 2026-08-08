<?php

namespace Ytmusicapi;

use WpOrg\Requests\Utility\CaseInsensitiveDictionary as CaseInsensitiveDict;

/**
 * Base class representation of the YouTubeMusicAPI OAuth token.
 */
class Token
{
    public string $scope;
    public string $token_type = "Bearer";
    public string $access_token;
    public string $refresh_token;
    public ?int $expires_at = 0;
    public ?int $expires_in = 0;
    public ?string $filepath = "";

    public static function members(): array
    {
        return array_keys(get_class_vars("Ytmusicapi\Token"));
    }

    /**
     * Returns dictionary containing underlying token values.
     */
    public function as_dict()
    {
        return json_decode(json_encode($this));
    }

    public function as_json()
    {
        return json_encode($this->as_dict());
    }

    /**
     * Returns Authorization header ready str of token_type and access_token.
     *
     * @return string
     */
    public function as_auth()
    {
        return "{$this->token_type} {$this->getAccessToken()}";
    }

    public function is_expiring()
    {
        return $this->expires_in < 60;
    }

    public function getAccessToken()
    {
        return $this->access_token;
    }
}

/**
 * Wrapper for an OAuth token implementing expiration methods.
 */
class OAuthToken extends Token
{
    /**
     * Check if all keys in Token members exist in headers.
     *
     * @param CaseInsensitiveDict $headers
     * @return bool
     */
    public static function is_oauth($headers)
    {
        $members = Token::members();

        foreach ($members as $key) {
            if (!isset($headers[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update access_token and expiration attributes with a BaseTokenDict inplace.
     * expires_at attribute set using current epoch, avoid expiration desync
     * by passing only recently requested tokens dicts or updating values to compensate.
     *
     * @param BaseTokenDict $fresh_access
     */
    public function update($fresh_access): void
    {
        $this->access_token = $fresh_access->access_token;
        $this->expires_at = time() + $fresh_access->expires_in;
    }

    public function is_expiring()
    {
        return $this->expires_at - time() < 60;
    }

    /**
     * @param string $file_path
     */
    public static function from_json($file_path): ?self
    {
        if (file_exists($file_path)) {
            $file_pack = json_decode(file_get_contents($file_path), true);
            return new self($file_pack);
        }

        return null;
    }
}

/**
 * Compositional implementation of Token that automatically refreshes
 * an underlying OAuthToken when required (credential expiration <= 1 min)
 * upon access_token attribute access.
 *
 * PROBLEM: This doesn't really work in PHP. Basically, in the Python version,
 * any time the `access_token` property was read, it would check if it needed
 * updating via a __getattribute__ method. Unfortunately, PHP's __get method
 * only works on properties that aren't defined on the class, so there's no
 * exact way to acheive the exact same functionality. We have tried to
 * mitigate this functionality with the getAccessToken() method.
 */
class RefreshingToken extends OAuthToken
{
    /**
     * credentials used for access_token refreshing
     */
    public Credentials $credentials;

    /**
     * filename to store token json
     */
    public ?string $_local_cache = null;

    /**
     * @param Credentials $credentials
     * @param ?string $local_cache
     * @param array $headers
     */
    public function __construct($credentials, $local_cache = null, $headers = [])
    {
        $this->credentials = $credentials;
        $this->_local_cache = $local_cache;
        foreach ($headers as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Auto refresh token if it is expiring.
     */
    public function getAccessToken()
    {
        $this->refresh_token();
        return $this->access_token;
    }

    public function refresh_token()
    {
        if ($this->is_expiring()) {
            $fresh = $this->credentials->refresh_token($this->refresh_token);
            $this->update($fresh);
            $this->store_token();
        }
    }

    /**
     * @param Credentials $credentials
     */
    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * @param string $path
     */
    public function set_local_cache($path): void
    {
        $this->_local_cache = $path;
        $this->store_token();
    }

    /**
     * Method for CLI token creation via user inputs.
     *
     * @param OAuthCredentials $credentials: Client credentials
     * @param bool $open_browser: Not supported
     * @param ?string $to_file: Optional. Path to store/sync json version of resulting token. (Default = None).
     * @return RefreshingToken
     */
    public static function prompt_for_token($credentials, $open_browser = false, $to_file = null)
    {
        $code = $credentials->get_code();
        $url = $code->verification_url . "?user_code=" . $code->user_code;

        echo "Go to " . $url . ", finish the login flow and press Enter when done, Ctrl-C to abort";
        readline();

        $raw_token = $credentials->token_from_code($code->device_code);

        $refresh_token_expires_in = $raw_token->refresh_token_expires_in ?? $raw_token->expires_in;

        $ref_token = new static($credentials);
        $ref_token->access_token = $raw_token->access_token;
        $ref_token->refresh_token = $raw_token->refresh_token;
        $ref_token->scope = $raw_token->scope;
        $ref_token->token_type = $raw_token->token_type;
        $ref_token->expires_in = $refresh_token_expires_in;

        $ref_token->update($raw_token);

        if ($to_file) {
            $ref_token->set_local_cache($to_file);
        }

        return $ref_token;
    }

    /**
     * Write token values to json file at specified path, defaulting to $this->local_cache.
     * Operation does not update instance local_cache attribute.
     * Automatically called when local_cache is set post init.
     *
     * Custom logic to specify exact key/value pairs for json file.
     *
     * @param ?string $path
     */
    public function store_token($path = null): void
    {
        $file_path = $path ? $path : $this->_local_cache;

        if ($file_path) {
            $dict = (object)[
                "scope" => $this->scope,
                "token_type" => $this->token_type,
                "access_token" => $this->access_token,
                "refresh_token" => $this->refresh_token,
                "expires_at" => $this->expires_at,
                "expires_in" => $this->expires_in,
            ];
            $json = json_encode($dict, JSON_PRETTY_PRINT);
            file_put_contents($file_path, $json);
        }
    }
}
