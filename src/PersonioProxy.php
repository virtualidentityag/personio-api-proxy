<?php

namespace App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;


class PersonioProxy {

	protected $request;
    protected $response;
    protected array $args;

    /**
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __construct($request, $response, array $args) {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;
    }

	public function process() {

		// Create a guzzle client
		$guzzle = new \GuzzleHttp\Client(['defaults' => [
			'verify' => false
		]]);

		$personioProxyAuth = $this->request->getHeader('X-Personio-Proxy-Auth');
		if(empty($personioProxyAuth)) { throw new \Exception('ProxyAuthNotSet'); }

		$personioProxyAuth = explode('####', trim($personioProxyAuth[0]));
		if(empty($personioProxyAuth[1])) { throw new \Exception('ProxyAuthFormatNotCorrect'); }

		$authRes = $guzzle->request('POST', 'https://api.personio.de/v1/auth?client_id='.$personioProxyAuth[0].'&client_secret='.$personioProxyAuth[1], [
			'headers' => ['Accept' => 'application/json']
		]);

		$bearerToken = json_decode($authRes->getBody());
		
		if(!isset($bearerToken->data->token)) { throw new \Exception('AuthFailed'); }

		$uri = $this->request->getUri();

		$urlPath = $this->request->getUri()->getPath();
		$urlPath = substr($urlPath, strpos($urlPath, '/v1'));
		$uri = $uri->withPath($urlPath);

		//modified request
		$request = $this->request->withUri($uri)->withHeader('authorization', 'Bearer ' . $bearerToken->data->token);
		
		// Create the proxy instance
		$proxy = new Proxy(new GuzzleAdapter($guzzle));
		
		// Add a response filter that removes the encoding headers.
		$proxy->filter(new RemoveEncodingFilter());
		
		try {
			// Forward the request and get the response.
			$proxyResponse = $proxy->forward($request)->to('https://api.personio.de/');

				$this->response->getBody()->write((String) $proxyResponse->getBody());
		
			// Output response to the browser.
			(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($this->response);
		} catch(\GuzzleHttp\Exception\BadResponseException $e) {
			// Correct way to handle bad responses
			(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($e->getResponse());
		}

	}
}