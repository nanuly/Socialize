<?php

namespace Nanuly\Socialize\Two;

use GuzzleHttp\ClientInterface;

class KakaotalkProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base Facebook Graph URL.
     *
     * @var string
     */
    protected $authUrl = 'https://kauth.kakao.com';
    protected $apiUrl = 'https://kapi.kakao.com';

    /**
     * The Graph API version for the request.
     *
     * @var string
     */
    protected $version = 'v1';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->authUrl.'/oauth/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->authUrl.'/oauth/token';
    }

    /**
     * Get the access token for the given code.
     *
     * @param  string  $code
     * @return string
     */

    public function getAccessToken($code)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            $postKey => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFields($code)
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->apiUrl.'/v1/user/me?access_token='.$token, [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => array_get($user, 'nickname'),
            'name' => $user['displayName'],
            'email' => $user['emails'][0]['value'],
            'avatar' => array_get($user, 'image')['url'],
        ]);
    }
}
