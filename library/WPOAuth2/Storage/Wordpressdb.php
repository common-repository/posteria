<?php

namespace WPOAuth2\Storage;

use WPOAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;
use WPOAuth2\OpenID\Storage\UserClaimsInterface;

/**
 * Simple WordPress Database Object Layer
 *
 * NOTE: This class is a modified version of the PDO object by Brent Shaffer
 *
 * @org-author Brent Shaffer <bshafs at gmail dot com>
 * @author     Justin Greer <justin@justin-greer.com>
 */
class Wordpressdb implements
	AuthorizationCodeInterface,
	AccessTokenInterface,
	ClientCredentialsInterface,
	UserCredentialsInterface,
	RefreshTokenInterface,
	JwtBearerInterface,
	ScopeInterface,
	PublicKeyInterface,
	UserClaimsInterface,
	OpenIDAuthorizationCodeInterface
{

	protected $db;
	protected $config;

	/**
	 * [__construct description]
	 *
	 * @param array $config Configuration for the WPDB Storage Object
	 *
	 * @todo Remove client table from config and test
	 */
	public function __construct($config = array())
	{
		global $wpdb;
		$this->db     = $wpdb;
		$this->config = array_merge(
			array(
				'client_table'        => $this->db->prefix . 'oauth_clients',
				'access_token_table'  => $this->db->prefix . 'oauth_access_tokens',
				'refresh_token_table' => $this->db->prefix . 'oauth_refresh_tokens',
				'code_table'          => $this->db->prefix . 'oauth_authorization_codes',
				'user_table'          => $this->db->prefix . 'oauth_users',
				'jwt_table'           => $this->db->prefix . 'oauth_jwt',
				'jwi_table'           => $this->db->prefix . 'oauth_jwi', // Needs implanted
				'scope_table'         => $this->db->prefix . 'oauth_scopes',
				'public_key_table'    => $this->db->prefix . 'oauth_public_keys',
			),
			$config
		);
	}

	/**
	 * [checkClientCredentials description]
	 *
	 * @param [type] $client_id     [description]
	 * @param [type] $client_secret [description]
	 *
	 * @return [type]                [description]
	 */
	public function checkClientCredentials($client_id, $client_secret = null)
	{
		$client = new \WP_Query(
			array(
				'post_type'   => 'wo_client',
				'post_status' => 'any',
				'meta_query'  => array(
					array(
						'key'   => 'client_id',
						'value' => $client_id,
					),
					array(
						'key'   => 'client_secret',
						'value' => $client_secret,
					),
				),
			)
		);

		if ($client->have_posts()) {
			// $client = $client->posts[0];
			return true;
		}

		return false;
	}

	/**
	 * [isPublicClient description]
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return boolean            [description]
	 */
	public function isPublicClient($client_id)
	{
		$query   = new \WP_Query();
		$clients = $query->query(
			array(
				'post_type'   => 'wo_client',
				'post_status' => 'any',
				'meta_query'  => array(
					array(
						'key'   => 'client_id',
						'value' => $client_id,
					),
				),
			)
		);

		if ($clients) {
			$client = $clients[0];

			$client_secret = get_post_meta($client->ID, 'client_secret', true);
			$check         = empty($client_secret);

			return $check;
		}
	}

	/**
	 * [getClientDetails description]
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return [type]            [description]
	 */
	public function getClientDetails($client_id)
	{
		$query   = new \WP_Query();
		$clients = $query->query(
			array(
				'post_type'   => 'wo_client',
				'post_status' => 'any',
				'meta_query'  => array(
					array(
						'key'   => 'client_id',
						'value' => $client_id,
					),
				),
			)
		);

		if ($clients) {
			$client                = $clients[0];
			$client->client_secret = get_post_meta($client->ID, 'client_secret', true);
			$client->redirect_uri  = get_post_meta($client->ID, 'redirect_uri', true);
			$client->grant_types   = get_post_meta($client->ID, 'grant_types', true);
			$client->user_id       = get_post_meta($client->ID, 'user_id', true);
			$client->scope         = get_post_meta($client->ID, 'scope', true);
			$client->meta          = get_post_meta($client->ID);

			return (array) $client;
		}
	}

	/**
	 * [checkRestrictedGrantType description]
	 *
	 * @param [type] $client_id  [description]
	 * @param [type] $grant_type [description]
	 *
	 * @return [type]             [description]
	 */
	public function checkRestrictedGrantType($client_id, $grant_type)
	{

		// print_r( $grant_type ); exit;

		// * password
		// * authorization_code
		// * client_credentials
		// * refresh_token
		$details = $this->getClientDetails($client_id);
		if (isset($details['grant_types'])) {
			return in_array($grant_type, $details['grant_types']);
		}

		return true;
	}

	/**
	 * [getAccessToken description]
	 *
	 * @param [type] $access_token [description]
	 *
	 * @return [type]               [description]
	 */
	public function getAccessToken($access_token)
	{
		$stmt  = $this->db->prepare("SELECT * FROM {$this->db->prefix}oauth_access_tokens WHERE access_token = %s", array($access_token));
		$token = $this->db->get_row($stmt, ARRAY_A);

		if (null != $token) {


			if (is_int($token['expires'])) {
				$token['expires'] = date('Y-m-d H:i:s', $token['expires']);
			} elseif (is_string($token['expires'])) {
				$token['expires'] = strtotime($token['expires']);
			} else {
				$to = 'zied@isoluce.net';
				$subject = 'Sujet de l\'e-mail';
				$message = 'erreur avec le wordpress dun client' . home_url();

				// Ajouter des en-têtes pour le format et l'expéditeur de l'e-mail
				$headers = 'From: Nom de l\'expéditeur <expediteur@email.com>' . "\r\n";
				$headers .= 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

				// Envoyer l'e-mail en utilisant la fonction mail()
				if (mail($to, $subject, $message, $headers)) {
					// L'e-mail a été envoyé avec succès
				}
			}
			$filtered_token   = apply_filters('wo_get_access_token_expires_return',  $token['expires']);
			$token['expires'] = $filtered_token;
		}

		return $token;
	}

	/**
	 * [setAccessToken description]
	 *
	 * @param [type] $access_token [description]
	 * @param [type] $client_id    [description]
	 * @param [type] $user_id      [description]
	 * @param [type] $expires      [description]
	 * @param [type] $scope        [description]
	 */
	public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null)
	{

		/**
		 * wo_set_access_token Action
		 * Returns access_token, client_id, $user_id
		 *
		 * @since 3.1.9
		 */
		do_action(
			'wo_set_access_token',
			array(
				'access_token' => $access_token,
				'client_id'    => $client_id,
				'user_id'      => $user_id,
			)
		);

		$expires = date('Y-m-d H:i:s', $expires);
		if ($this->getAccessToken($access_token)) {
			$stmt = $this->db->prepare(
				"UPDATE {$this->db->prefix}oauth_access_tokens SET client_id=%s, expires=%s, user_id=%s, scope=%s where access_token=%s",
				array(
					$client_id,
					$expires,
					$user_id,
					$scope,
					$access_token,
				)
			);
		} else {
			$stmt = $this->db->prepare(
				"INSERT INTO {$this->db->prefix}oauth_access_tokens (access_token, client_id, expires, user_id, scope) VALUES (%s, %s, %s, %s, %s)",
				array(
					$access_token,
					$client_id,
					$expires,
					$user_id,
					$scope,
				)
			);
		}

		// Give return a value
		$results = $this->db->query($stmt);

		// Return Results
		return $results;
	}

	/**
	 * Unset an access token
	 *
	 * @param $token
	 *
	 * @return bool|int
	 */
	public function unsetAccessToken($token)
	{
		$stmt = $this->db->prepare("DELETE FROM {$this->db->prefix}oauth_access_tokens WHERE access_token = %s", array($token));

		return $this->db->query($stmt);
	}

	/**
	 * [getAuthorizationCode description]
	 *
	 * @param [type]                             $code [description]
	 * @param bool to return id_token key or not. Now that is the question!
	 *
	 * @return [type]       [description]
	 */
	public function getAuthorizationCode($code, $code_verifier = null)
	{
		$stmt = $this->db->prepare("SELECT * from {$this->db->prefix}oauth_authorization_codes WHERE authorization_code = %s", array($code));
		$stmt = $this->db->get_row($stmt, ARRAY_A);

		if (null != $stmt) {
			$stmt['expires'] = strtotime($stmt['expires']);
		}

		/*
		* Check for PKCE Requirements
		* If the code query returns with a code challenge then we know the request to use it will be sending the
		* code_verifier parameter when requesting a token. This addition checks the request and uses the given method
		* to verifier the request.
		*
		* @url https://tools.ietf.org/html/rfc7636
		* #url https://www.oauth.com/oauth2-servers/pkce/
		*
		* @since 4.1.0
		*/
		if (!empty($stmt['code_challenge'])) {
			$code_challenge_method = $stmt['code_challenge_method'];
			if ($code_challenge_method == 'S256') {
				$hash           = hash('sha256', $code_verifier, true);
				$code_challenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
			} else {
				$code_challenge = $code_verifier;
			}

			// Cross check the provided code challenge with the stored code challenge
			if ($code_challenge != $stmt['code_challenge']) {
				return false;
			}
		}

		return $stmt;
	}

	/**
	 * [setAuthorizationCode description]
	 *
	 * @param [type] $code         [description]
	 * @param [type] $client_id    [description]
	 * @param [type] $user_id      [description]
	 * @param [type] $redirect_uri [description]
	 * @param [type] $expires      [description]
	 * @param [type] $scope        [description]
	 * @param [type] $id_token     [description]
	 */
	public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null, $code_challenge = null, $code_challenage_method = null)
	{

		if (!is_null($id_token)) {
			return call_user_func_array(array($this, 'setAuthorizationCodeWithIdToken'), func_get_args());
		}

		$expires = date('Y-m-d H:i:s', $expires);

		// if it exists, update it.
		if ($this->getAuthorizationCode($code)) {
			$stmt = $this->db->prepare(
				"UPDATE {$this->db->prefix}oauth_authorization_codes SET client_id=%s, user_id=%s, redirect_uri=%s, expires=%s, scope=%s where authorization_code=%s",
				array(
					$client_id,
					$user_id,
					$redirect_uri,
					$expires,
					$code,
				)
			);
		} else {
			$stmt = $this->db->prepare(
				"INSERT INTO {$this->db->prefix}oauth_authorization_codes (authorization_code, client_id, user_id, redirect_uri, expires, scope, code_challenge, code_challenge_method) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
				array(
					$code,
					$client_id,
					$user_id,
					$redirect_uri,
					$expires,
					$scope,
					$code_challenge,
					$code_challenage_method,
				)
			);
		}

		return $this->db->query($stmt);
	}

	/**
	 * [setAuthorizationCodeWithIdToken description]
	 *
	 * @param [type] $code         [description]
	 * @param [type] $client_id    [description]
	 * @param [type] $user_id      [description]
	 * @param [type] $redirect_uri [description]
	 * @param [type] $expires      [description]
	 * @param [type] $scope        [description]
	 * @param [type] $id_token     [description]
	 */
	private function setAuthorizationCodeWithIdToken($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null, $code_challenge = null, $code_challenge_method = null)
	{

		// convert expires to date string
		$expires = date('Y-m-d H:i:s', $expires);

		// if it exists, update it.
		if ($this->getAuthorizationCode($code)) {
			$stmt = $this->db->prepare(
				"UPDATE {$this->db->prefix}oauth_authorization_codes SET client_id=%s, user_id=%s, redirect_uri=%s, expires=%s, scope=%s, id_token=%s, code_challenge=%s, code_challenge_method=%s where authorization_code=%s",
				array(
					$client_id,
					$user_id,
					$redirect_uri,
					$expires,
					$scope,
					$id_token,
					$code_challenge,
					$code_challenge_method,
					$code,
				)
			);
		} else {
			$stmt = $this->db->prepare(
				"INSERT INTO {$this->db->prefix}oauth_authorization_codes (authorization_code, client_id, user_id, redirect_uri, expires, scope, id_token, code_challenge, code_challenge_method) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
				array(
					$code,
					$client_id,
					$user_id,
					$redirect_uri,
					$expires,
					$scope,
					$id_token,
					$code_challenge,
					$code_challenge_method,
				)
			);
		}

		return $this->db->query($stmt);
	}

	/**
	 * [expireAuthorizationCode description]
	 *
	 * @param [type] $code [description]
	 *
	 * @return [type]       [description]
	 */
	public function expireAuthorizationCode($code)
	{
		$stmt = $this->db->prepare("DELETE FROM {$this->db->prefix}oauth_authorization_codes WHERE authorization_code = %s", array($code));

		return $this->db->query($stmt);
	}

	/**
	 * [checkUserCredentials description]
	 *
	 * @param [type] $username [description]
	 * @param [type] $password [description]
	 *
	 * @return [type]           [description]
	 */
	public function checkUserCredentials($username, $password)
	{

		if ($user = $this->getUser($username)) {
			$login_check = $this->checkPassword($user, $password);

			// @since 3.1.94 the parameter $user is being passed
			if (!$login_check) {
				do_action('wo_failed_login', $user);

				return $login_check;
			} else {

				return apply_filters('wo_login_check', $login_check, $user['user_id']);
			}
		}
		do_action('wo_user_not_found');

		return false;
	}

	/**
	 * [getUserDetails description]
	 *
	 * @param [type] $username [description]
	 *
	 * @return [type]           [description]
	 */
	public function getUserDetails($username)
	{
		return $this->getUser($username);
	}

	/**
	 * [getUserClaims description]
	 *
	 * @param [type] $user_id [description]
	 * @param [type] $claims  [description]
	 *
	 * @return [type]          [description]
	 *
	 * @since 3.0.5-alpha Claims are handled manually since it just makes more sense this way
	 */
	public function getUserClaims($user_id, $claims)
	{

		// Grab the user information for the ID
		$userInfo = get_userdata($user_id);

		// Split up the claims
		$claims = explode(' ', trim($claims));

		// User claims array
		$userClaims = array();

		// If the scope "email" is found
		if (in_array('email', $claims)) {
			$userClaims += array(
				'email'          => $userInfo->user_email,
				'email_verified' => '',
			);
		}

		// If the scope "profile" is found
		if (in_array('profile', $claims)) {
			$userClaims += array(
				'name'               => $userInfo->display_name,
				'family_name'        => '',
				'given_name'         => '',
				'middle_name'        => '',
				'nickname'           => '',
				'preferred_username' => $userInfo->display_name,
				'profile'            => '',
				'picture'            => 'http://www.gravatar.com/avatar/' . md5(strtolower(trim($userInfo->user_email))) . '?s=40',
				'website'            => $userInfo->user_url,
				'gender'             => '',
				'birthdate'          => '',
				'zoneinfo'           => get_option('timezone_string'),
				'updated_at'         => $userInfo->user_registered,
			);
		}

		// If the scope "address" is found
		if (in_array('address', $claims)) {
			$userClaims += array(
				'formatted'      => '',
				'street_address' => '',
				'locality'       => '',
				'region'         => '',
				'postal_code'    => '',
				'country'        => '',
			);
		}

		// If the scope "phone" is found
		if (in_array('phone', $claims)) {
			$userClaims += array(
				'phone_number'          => '',
				'phone_number_verified' => '',
			);
		}

		return $userClaims;
	}

	/**
	 * [getUserClaim description]
	 *
	 * @param [type] $claim       [description]
	 * @param [type] $userDetails [description]
	 *
	 * @return [type]              [description]
	 *
	 * @todo Check
	 */
	protected function getUserClaim($claim, $userDetails)
	{
		$userClaims        = array();
		$claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
		$claimValues       = explode(' ', $claimValuesString);

		foreach ($claimValues as $value) {
			$userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
		}

		return $userClaims;
	}

	/**
	 * [getRefreshToken description]
	 *
	 * @param [type] $refresh_token [description]
	 *
	 * @return [type]                [description]
	 */
	public function getRefreshToken($refresh_token)
	{
		$stmt = $this->db->prepare(
			"SELECT * FROM {$this->db->prefix}oauth_refresh_tokens WHERE refresh_token = %s",
			array($refresh_token)
		);

		$stmt = $this->db->get_row($stmt, ARRAY_A);

		$token = null;
		if ($stmt) {
			$token            = $stmt;
			$token['expires'] = strtotime($stmt['expires']);
		}

		return $token;
	}

	/**
	 * [setRefreshToken description]
	 *
	 * @param [type] $refresh_token [description]
	 * @param [type] $client_id     [description]
	 * @param [type] $user_id       [description]
	 * @param [type] $expires       [description]
	 * @param [type] $scope         [description]
	 */
	public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
	{
		$expires = date('Y-m-d H:i:s', $expires);
		$stmt    = $this->db->prepare(
			"INSERT INTO {$this->db->prefix}oauth_refresh_tokens (refresh_token, client_id, user_id, expires, scope) VALUES (%s, %s, %s, %s, %s)",
			array(
				$refresh_token,
				$client_id,
				$user_id,
				$expires,
				$scope,
			)
		);

		return $this->db->query($stmt);
	}

	/**
	 * [unsetRefreshToken description]
	 *
	 * @param [type] $refresh_token [description]
	 *
	 * @return [type]                [description]
	 */
	public function unsetRefreshToken($refresh_token)
	{
		$stmt = $this->db->prepare("DELETE FROM {$this->db->prefix}oauth_refresh_tokens WHERE refresh_token = %s", array($refresh_token));

		return $this->db->query($stmt);
	}

	/**
	 * Check the user login credentials
	 *
	 * @param [type] $user     [description]
	 * @param [type] $password [description]
	 *
	 * @return [type]           [description]
	 */
	protected function checkPassword($user, $password)
	{
		$login_check = wp_check_password($password, $user['user_pass'], $user['ID']);
		if (!$login_check) {
			do_action('wp_login_failed', $user['user_login']);
		}

		return $login_check;
	}

	/**
	 * Retrieve a user ID from the database
	 *
	 * @param [type] $username [description]
	 *
	 * @return [type]           [description]
	 */
	/**
	 * public function getUser( $username ) {
	 * $user  = get_user_by( 'login', $username );
	 * if ( false === $user ) {
	 * return false;
	 * }
	 * $userInfo = (array) $user->data;
	 *
	 * return array_merge( array(
	 * 'user_id' => $userInfo['ID']
	 * ),
	 * $userInfo
	 * );
	 * }
	 */
	public function getUser($userInput)
	{
		$field = (false === filter_var($userInput, FILTER_VALIDATE_EMAIL)) ? 'login' : 'email';
		$user  = get_user_by($field, $userInput);
		if (false === $user) {
			return false;
		}
		$userInfo = (array) $user->data;

		return array_merge(
			array(
				'user_id' => $userInfo['ID'],
			),
			$userInfo
		);
	}

	/**
	 * Check to see is a scope exists in the database
	 *
	 * @param [type] $scope [description]
	 *
	 * @return [type]        [description]
	 */
	public function scopeExists($scope)
	{
		$scope   = explode(' ', $scope);
		$whereIn = implode(',', array_fill(0, count($scope), '?'));
		$stmt    = $this->db->prepare("SELECT count(scope) as count FROM {$this->db->prefix}oauth_scopes WHERE scope IN (%s)", array($whereIn));
		$stmt    = $this->db->query($stmt, ARRAY_A);

		if (null != $stmt) {
			return $stmt['count'] == count($scope);
		}

		return false;
	}

	/**
	 * Get the default scope from the database
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return [type]            [description]
	 */
	public function getDefaultScope($client_id = null)
	{
		$client_scopes = $this->getClientScope($client_id);

		$client_default_scopes = maybe_unserialize($client_scopes);

		return $client_default_scopes;
	}

	/**
	 * Retrieve a client key
	 *
	 * @param [type] $client_id [description]
	 * @param [type] $subject   [description]
	 *
	 * @return [type]            [description]
	 */
	public function getClientKey($client_id, $subject)
	{
		$stmt = $this->db->prepare(
			"SELECT public_key from {$this->db->prefix}oauth_jwt where client_id=%s AND subject=%s",
			array(
				$client_id,
				$subject,
			)
		);

		return $this->db->get_col($stmt);
	}

	/**
	 * Set a client key
	 *
	 * @param $public_key
	 * @param $client_id
	 * @param $subject
	 *
	 * @todo Finish the implementation of this feature.
	 */
	public function setClientKey($public_key, $client_id, $subject)
	{
	}

	/**
	 * [getClientScope description]
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return [type]            [description]
	 */
	public function getClientScope($client_id)
	{
		if (!$clientDetails = $this->getClientDetails($client_id)) {
			return false;
		}

		if (isset($clientDetails['scope'])) {
			return $clientDetails['scope'];
		}

		return null;
	}

	/**
	 * [getJti description]
	 *
	 * @param [type] $client_id [description]
	 * @param [type] $subject   [description]
	 * @param [type] $audience  [description]
	 * @param [type] $expires   [description]
	 * @param [type] $jti       [description]
	 *
	 * @return [type]            [description]
	 *
	 * @todo Check for Removal
	 */
	public function getJti($client_id, $subject, $audience, $expires, $jti)
	{
		$stmt = $this->db->prepare($sql = sprintf('SELECT * FROM %s WHERE issuer=:client_id AND subject=:subject AND audience=:audience AND expires=:expires AND jti=:jti', $this->config['jti_table']));

		$stmt->execute(compact('client_id', 'subject', 'audience', 'expires', 'jti'));

		if ($result = $stmt->fetch()) {
			return array(
				'issuer'   => $result['issuer'],
				'subject'  => $result['subject'],
				'audience' => $result['audience'],
				'expires'  => $result['expires'],
				'jti'      => $result['jti'],
			);
		}

		return null;
	}

	/**
	 * [setJti description]
	 *
	 * @param [type] $client_id [description]
	 * @param [type] $subject   [description]
	 * @param [type] $audience  [description]
	 * @param [type] $expires   [description]
	 * @param [type] $jti       [description]
	 *
	 * @todo Check for removal
	 */
	public function setJti($client_id, $subject, $audience, $expires, $jti)
	{
		$stmt = $this->db->prepare(sprintf('INSERT INTO %s (issuer, subject, audience, expires, jti) VALUES (:client_id, :subject, :audience, :expires, :jti)', $this->config['jti_table']));

		return $stmt->execute(compact('client_id', 'subject', 'audience', 'expires', 'jti'));
	}

	/**
	 * [getPublicKey description]
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return [type]            [description]
	 */
	public function getPublicKey($client_id = null)
	{
		$stmt = $this->db->prepare("SELECT public_key FROM {$this->db->prefix}oauth_public_keys WHERE client_id=%s OR client_id IS NULL ORDER BY client_id IS NOT NULL DESC", array($client_id));
		$stmt = $this->db->get_row($stmt, ARRAY_A);

		if (null != $stmt) {
			return $stmt['public_key'];
		}
	}

	/**
	 * [getPrivateKey description]
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return [type]            [description]
	 */
	public function getPrivateKey($client_id = null)
	{
		$stmt = $this->db->prepare("SELECT private_key FROM {$this->db->prefix}oauth_public_keys WHERE client_id=%s OR client_id IS NULL ORDER BY client_id IS NOT NULL DESC", array($client_id));
		$stmt = $this->db->get_row($stmt, ARRAY_A);

		if (null != $stmt) {
			return $stmt['private_key'];
		}
	}

	/**
	 * [getEncryptionAlgorithm description]
	 *
	 * @param [type] $client_id [description]
	 *
	 * @return [type]            [description]
	 */
	public function getEncryptionAlgorithm($client_id = null)
	{
		$stmt = $this->db->prepare("SELECT encryption_algorithm FROM {$this->db->prefix}oauth_public_keys WHERE client_id=%s OR client_id IS NULL ORDER BY client_id IS NOT NULL DESC", array($client_id));
		$stmt = $this->db->get_row($stmt, ARRAY_A);

		if (null != $stmt) {
			return $stmt['encryption_algorithm'];
		}
	}
}
