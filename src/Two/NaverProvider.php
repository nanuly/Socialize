<?php

namespace Nanuly\Socialize\Two;

use GuzzleHttp\ClientInterface;

class NaverProvider extends AbstractProvider implements ProviderInterface
{
    protected $url = 'https://nid.naver.com';
    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://nid.naver.com/oauth2.0/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->url.'/oauth2.0/token';
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
        $apiUrl = 'https://openapi.naver.com/v1/nid/getUserProfile.xml';

        $authorization_header = "Authorization: Bearer " .  $token;
        $request_headers = array();
        array_push($request_headers, $authorization_header);
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $apiUrl);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

        $result = curl_exec($curl);
        curl_close($curl);
        $xml = simplexml_load_string($result, null, LIBXML_NOCDATA);
        $json = json_encode($xml);

        return json_decode($json, true);
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
