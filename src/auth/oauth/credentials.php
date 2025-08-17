<?php

namespace Ytmusicapi;

/**
 * Base class representation of the YouTubeMusicAPI OAuth Credentials
 */
class Credentials
{
    /**
     * @var string
     */
    public $client_id;

    /**
     * @var string
     */
    public $client_secret;

    public function __construct($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }

    /**
     * Method for obtaining a new user auth code. First step of token creation.
     *
     * @return AuthCodeDict
     */
    public function get_code()
    {
        throw new \Exception("Not implemented");
    }

    /**
     * Method for verifying user auth code and conversion into a FullTokenDict.
     *
     * @return array
     */
    public function token_from_code($device_code)
    {
        throw new \Exception("Not implemented");
    }

    /**
     * Method for requesting a new access token for a given refresh_token.
     * Token must have been created by the same OAuth client.
     *
     * @return array
     */
    public function refresh_token($refresh_token)
    {
        throw new \Exception("Not implemented");
    }
}

/**
 * Class for handling OAuth credential retrieval and refreshing.
 */
class OAuthCredentials extends Credentials
{
    public $_session;

    /**
     * @param string $client_id Set the GoogleAPI client_id used for auth flows.
     * @param string $client_secret Corresponding secret for provided client_id.
     * @param \WpOrg\Requests\Session $session Optional. Connection pooling with an active session.
     * @param array $proxies Optional. Modify the Session with proxy parameters.
     */
    public function __construct(
        $client_id,
        $client_secret,
        $session = null,
        $proxies = null
    ) {
        if ($client_id === null || $client_secret === null) {
            throw new \Exception("OAuthCredential init failure. Provide both client_id and client_secret.");
        }

        // bind instance to OAuth client for auth flows
        $this->client_id = trim($client_id);
        $this->client_secret = trim($client_secret);

        $this->_session = $session ?: new \WpOrg\Requests\Session();
        if ($proxies) {
            $this->_session->proxies = $proxies;
        }
    }

    /**
     * Method for obtaining a new user auth code. First step of token creation.
     *
     * @return AuthCodeDict
     */
    public function get_code()
    {
        $code_response = $this->_send_request(OAUTH_CODE_URL, ["scope" => OAUTH_SCOPE]);
        return typingCast(AuthCodeDict::class, json_decode($code_response->body));
    }

    /**
     * Method for sending post requests with required client_id and User-Agent modifications
     *
     * @param string $url
     * @param array $data
     * @return \WpOrg\Requests\Response
     */
    public function _send_request($url, $data)
    {
        $data = (array)$data;
        $data["client_id"] = $this->client_id;

        $response = $this->_session->post(
            $url,
            [ "User-Agent" => \Ytmusicapi\OAUTH_USER_AGENT ],
            $data
        );

        // This logic differs for Python implementation.
        if ($response->status_code >= 400) {
            $data = json_decode($response->body);

            echo "Error creating OAuth credentials:\n";
            echo "status_code: " . $response->status_code . "\n";
            echo "url: " . $url . "\n";
            echo "content: " . $data->error . "\n";
            if (!empty($data->error_description)) {
                echo "error: " . $data->error_description . "\n";
            } else {
                print_r($data);
            }

            exit;
        }

        return $response;
    }

    /**
     * Method for verifying user auth code and conversion into a FullTokenDict.
     * 
     * @param string $device_code
     * @return RefreshableTokenDict
     */
    public function token_from_code($device_code)
    {
        $response = $this->_send_request(
            OAUTH_TOKEN_URL,
            [
                "client_secret" => $this->client_secret,
                "grant_type" => "http://oauth.net/grant_type/device/1.0",
                "code" => $device_code
            ]
        );

        return typingCast(RefreshableTokenDict::class, json_decode($response->body));
    }

    /**
     * Method for requesting a new access token for a given refresh_token.
     * Token must have been created by the same OAuth client.
     *
     * @param string $refresh_token Corresponding refresh_token for a matching access_token.
     * @return BaseTokenDict
     */
    public function refresh_token($refresh_token)
    {
        $response = $this->_send_request(
            OAUTH_TOKEN_URL,
            [
                "client_secret" => $this->client_secret,
                "grant_type" => "refresh_token",
                "refresh_token" => $refresh_token
            ]
        );
        return typingCast(BaseTokenDict::class, json_decode($response->body));
    }
}
