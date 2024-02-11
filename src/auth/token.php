<?php

namespace Ytmusicapi;

/**
 * Base class representation of the YouTubeMusicAPI OAuth token.
 */
class Token
{
    /**
     * @var string
     */
    public $scope;

    /**
     * @var string
     */
    public $token_type;

    public string $access_token;
    public string $refresh_token;
    public int $expires_at = 0;
    public int $expires_in = 0;

    public static function members()
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
        return "{$this->token_type} {$this->access_token}";
    }

    public function is_expiring()
    {
        return $this->expires_in < 60;
    }
}

/**
 * Wrapper for an OAuth token implementing expiration methods.
 */
class OAuthToken extends Token
{
    /**
     * Check if all keys in Token members exist in headers.
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
     */
    public function update($fresh_access)
    {
        $this->access_token = $fresh_access->access_token;
        $this->expires_at = time() + $fresh_access->expires_in;
    }

    public function is_expiring()
    {
        return $this->expires_at - time() < 60;
    }

    public static function from_json($file_path)
    {
        if (file_exists($file_path)) {
            $file_pack = json_decode(file_get_contents($file_path), true);
            return new self($file_pack);
        }
    }
}

/**
 * Compositional implementation of Token that automatically refreshes
 * an underlying OAuthToken when required (credential expiration <= 1 min)
 * upon access_token attribute access.
 */
class RefreshingToken extends OAuthToken
{
    /**
     * credentials used for access_token refreshing
     */
    public $credentials = null;

    /**
     * @var string
     * filename to store token json
     */
    public $_local_cache = null;

    public function refresh_token()
    {
        if ($this->is_expiring()) {
            $fresh = $this->credentials->refresh_token($this->refresh_token);
            $this->update($fresh);
            $this->store_token();
        }
    }

    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
    }

    public function set_local_cache($path, $store = true)
    {
        $this->_local_cache = $path;
        if ($store) {
            $this->store_token();
        }
    }

    /**
     * Method for CLI token creation via user inputs.
     * @param Credentials $credentials: Client credentials
     * @param bool $open_browser: Not supported
     * @param string $to_file: Optional. Path to store/sync json version of resulting token. (Default = None).
     */
    public static function prompt_for_token($credentials, $open_browser = false, $to_file = null)
    {
        $code = $credentials->get_code();
        $url = $code->verification_url . "?user_code=" . $code->user_code;

        echo "Go to " . $url . ", finish the login flow and press Enter when done, Ctrl-C to abort";
        readline();

        $raw_token = $credentials->token_from_code($code->device_code);

        $ref_token = new self();
        $ref_token->credentials = $credentials;
        foreach ((array)$raw_token as $key => $value) {
            $ref_token->$key = $value;
        }
        $ref_token->update($ref_token->as_dict());

        if ($to_file) {
            $ref_token->set_local_cache($to_file);
        }

        return $ref_token;
    }

    /**
     * Write token values to json file at specified path, defaulting to $this->local_cache.
     * Operation does not update instance local_cache attribute.
     * Automatically called when local_cache is set post init.
     */
    public function store_token($path = null)
    {
        $file_path = $path ? $path : $this->_local_cache;

        if ($file_path) {
            $dict = $this->as_dict();
            unset($dict->credentials);
            unset($dict->_local_cache);
            $json = json_encode($dict, JSON_PRETTY_PRINT);
            file_put_contents($file_path, $json);
        }
    }
}
