<?php

namespace WPOAuth2\GrantType;

use WPOAuth2\RequestInterface;
use WPOAuth2\ResponseInterface;
use WPOAuth2\ResponseType\AccessTokenInterface;
use WPOAuth2\Storage\AuthorizationCodeInterface;

/**
 *
 * @author Brent Shaffer <bshafs at gmail dot com>
 */
class AuthorizationCode implements GrantTypeInterface
{

	protected $storage;
	protected $authCode;

	public function __construct(AuthorizationCodeInterface $storage)
	{
		$this->storage = $storage;
	}

	public function getQuerystringIdentifier()
	{
		return 'authorization_code';
	}

	public function validateRequest(RequestInterface $request, ResponseInterface $response)
	{

		if (!$request->request('code')) {
			$response->setError(400, 'invalid_request', 'Missing parameter: "code" is required');

			return false;
		}

		$code = $request->request('code');

		// Returns a string when presented and NULL if the parameter is empty
		$code_verifier = $request->request('code_verifier');

		if (!$authCode = $this->storage->getAuthorizationCode($code, $code_verifier)) {
			$response->setError(400, 'invalid_grant', 'Authorization code doesn\'t exist or is invalid for the client');

			return false;
		}

		// Remove id_token if not wanted
		$scopes = explode(' ', trim($authCode['scope']));
		if (!in_array('openid', $scopes)) {
			unset($authCode['id_token']);
		}

		/*
		* 4.1.3 - ensure that the "redirect_uri" parameter is present if the "redirect_uri" parameter was included in the initial authorization request
		* @uri - http://tools.ietf.org/html/rfc6749#section-4.1.3
		*/
		if (isset($authCode['redirect_uri']) && $authCode['redirect_uri']) {
			// if ( ! $request->request( 'redirect_uri' ) || urldecode( $request->request( 'redirect_uri' ) ) != $authCode['redirect_uri'] ) {
			// 	$response->setError( 400, 'redirect_uri_mismatch', 'The redirect URI is missing or do not match', '#section-4.1.3' );

			// 	return false;
			// }
		}

		if (!isset($authCode['expires'])) {
			throw new \Exception('Storage must return authcode with a value for "expires"');
		}

		if ($authCode['expires'] < current_time('timestamp')) {
			$response->setError(400, 'invalid_grant', 'The authorization code has expired');

			return false;
		}

		if (!isset($authCode['code'])) {
			$authCode['code'] = $code; // used to expire the code after the access token is granted
		}

		$this->authCode = $authCode;

		return true;
	}

	public function getClientId()
	{
		return $this->authCode['client_id'];
	}

	public function getScope()
	{
		return isset($this->authCode['scope']) ? $this->authCode['scope'] : null;
	}

	public function getUserId()
	{
		return isset($this->authCode['user_id']) ? $this->authCode['user_id'] : null;
	}

	public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
	{
		$token = $accessToken->createAccessToken($client_id, $user_id, $scope);
		$this->storage->expireAuthorizationCode($this->authCode['code']);

		return $token;
	}
}
