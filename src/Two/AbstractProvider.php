<?php

namespace Nanuly\Socialize\Two;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Nanuly\Socialize\Contracts\Provider as ProviderContract;

abstract class AbstractProvider implements ProviderContract
{
    /**
     * The HTTP request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * The type of the encoding in the query.
     *
     * @var int Can be either PHP_QUERY_RFC3986 or PHP_QUERY_RFC1738.
     */
    protected $encodingType = PHP_QUERY_RFC1738;

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = false;

    /**
     * Create a new provider instance.
     *
     * @param  Request  $request
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $redirectUrl
     * @return void
     */
    public function __construct(Request $request, $clientId, $clientSecret, $redirectUrl)
    {
        $this->request = $request;
        $this->clientId = $clientId;
        $this->redirectUrl = $redirectUrl;
        $this->clientSecret = $clientSecret;

        $this->memberLog = new \Geeks\Http\Controllers\MemberLogController;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    abstract protected function getAuthUrl($state);

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    abstract protected function getTokenUrl();

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     * @return array
     */
    abstract protected function getUserByToken($token);

    /**
     * Map the raw user array to a Socialize User instance.
     *
     * @param  array  $user
     * @return \Nanuly\Socialize\User
     */
    abstract protected function mapUserToObject(array $user);

    /**
     * Redirect the user of the application to the provider's authentication screen.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect()
    {
        $state = null;

        if ($this->usesState()) {
            $this->request->getSession()->set('state', $state = Str::random(40));
        }

        return new RedirectResponse($this->getAuthUrl($state));
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $url
     * @param  string  $state
     * @return string
     */
    protected function buildAuthUrlFromBase($url, $state)
    {
        return $url.'?'.http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @param  string|null  $state
     * @return array
     */
    protected function getCodeFields($state = null)
    {
        $fields = [
            'client_id' => $this->clientId, 'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ];

        if ($this->usesState()) {
            $fields['state'] = $state;
        }

        return array_merge($fields, $this->parameters);
    }

    /**
     * Format the given scopes.
     *
     * @param  array  $scopes
     * @param  string  $scopeSeparator
     * @return string
     */
    protected function formatScopes(array $scopes, $scopeSeparator)
    {
        return implode($scopeSeparator, $scopes);
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = $this->getAccessToken($this->getCode())
        ));

        return $user->setToken($token);
    }

    /**
     * Determine if the current request / session has a mismatching "state".
     *
     * @return bool
     */
    protected function hasInvalidState()
    {
        if ($this->isStateless()) {
            return false;
        }

        $state = $this->request->getSession()->pull('state');

        return ! (strlen($state) > 0 && $this->request->input('state') === $state);
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
            'headers' => ['Accept' => 'application/json'],
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
        return [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'code' => $code, 'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * Get the access token from the token response body.
     *
     * @param  string  $body
     * @return string
     */
    protected function parseAccessToken($body)
    {
        return json_decode($body, true)['access_token'];
    }

    /**
     * Get the code from the request.
     *
     * @return string
     */
    protected function getCode()
    {
        return $this->request->input('code');
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param  array  $scopes
     * @return $this
     */
    public function scopes(array $scopes)
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Get a fresh instance of the Guzzle HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        return new \GuzzleHttp\Client;
    }

    /**
     * Set the request instance.
     *
     * @param  Request  $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Determine if the provider is operating with state.
     *
     * @return bool
     */
    protected function usesState()
    {
        return ! $this->stateless;
    }

    /**
     * Determine if the provider is operating as stateless.
     *
     * @return bool
     */
    protected function isStateless()
    {
        return $this->stateless;
    }

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return $this
     */
    public function stateless()
    {
        $this->stateless = true;

        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param  array  $parameters
     * @return $this
     */
    public function with(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     *
     * Kakao Unlink
     *
     */
    public function kakaoUnlink($token)
    {
        $apiUrl = 'https://kapi.kakao.com/v1/user/unlink';

        $authorization_header = "Authorization: Bearer " .  $token;
        $request_headers = array();
        array_push($request_headers, $authorization_header);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

        $result = curl_exec($curl);
        curl_close($curl);

        $authorization_header = "Authorization: KakaoAK " . $this->app['config']['services.kakaotalk']['admin_key'];
        $request_headers = array();
        array_push($request_headers, $authorization_header);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

        $result = curl_exec($curl);
        curl_close($curl);

        \Geeks\User::where('id', '=', \Auth::id())->update(array('deleted_at' => date('Y-m-d H:i:s')));
        $this->memberLog->insertLog(\Auth::id(), 17, 'Kakaotalk 연동 해제',1);

        \Auth::logout();
        //return redirect('/');
        return view('geeks.display')
                    ->with(['sType'=>'success',
                            'iCode'=>'101',
                            'sUrl'=>'/',
                            'sMsg'=>'연동해제가 완료되었습니다. 카카오톡에서 다시 한번 확인해주세요.']);
    }

    public function naverUnlink($token)
    {
        $apiUrl = 'https://nid.naver.com/oauth2.0/token?grant_type=delete&client_id=' . $this->clientId;
        $apiUrl .= '&client_secret=' . $this->clientSecret;
        $apiUrl .= '&access_token='. $token;
        $apiUrl .= '&service_provider=NAVER';

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $apiUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        curl_close($curl);

        if (strstr($result, 'success') === false ) {
            $this->memberLog->insertLog(\Auth::id(), 17, 'naver 연동 해제', 0);

            return view('geeks.display')
                    ->with(['sType'=>'fail',
                            'iCode'=>'201',
                            'sUrl'=>'/member',
                            'sMsg'=>'연동해제를 실패하였습니다. 네이버에 문의해주세요.']);

        } else {
            \Geeks\User::where('id', '=', \Auth::id())->update(array('deleted_at' => date('Y-m-d H:i:s')));
            $this->memberLog->insertLog(\Auth::id(), 17, 'naver 연동 해제',1);

            \Auth::logout();
            return view('geeks.display')
                    ->with(['sType'=>'success',
                            'iCode'=>'101',
                            'sUrl'=>'/',
                            'sMsg'=>'연동해제가 완료되었습니다. 네이버에서 다시 한번 확인해주세요.']);
        }
        //return redirect('/');
    }
}
