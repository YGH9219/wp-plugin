<?php
/**
 * Meta Threads OAuth, encrypted credentials, publishing, and reconciliation.
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_THREADS_ACCOUNT_OPTION', 'personal_cta_threads_account' );
define( 'PERSONAL_CTA_THREADS_API_BASE', 'https://graph.threads.com/v1.0' );
define( 'PERSONAL_CTA_THREADS_AUTHORIZE_URL', 'https://threads.com/oauth/authorize' );
define( 'PERSONAL_CTA_THREADS_TOKEN_URL', 'https://graph.threads.com/oauth/access_token' );
define( 'PERSONAL_CTA_THREADS_LONG_TOKEN_URL', 'https://graph.threads.com/access_token' );
define( 'PERSONAL_CTA_THREADS_REFRESH_URL', 'https://graph.threads.com/refresh_access_token' );
define( 'PERSONAL_CTA_THREADS_REFRESH_HOOK', 'personal_cta_threads_refresh_token' );
define( 'PERSONAL_CTA_THREADS_RECONCILE_HOOK', 'personal_cta_threads_reconcile_job' );

/**
 * Returns a configured Meta app ID.
 *
 * @return string
 */
function personal_cta_threads_app_id() {
	return personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_APP_ID', 'PERSONAL_CTA_THREADS_APP_ID' );
}

/**
 * Returns a configured Meta app secret.
 *
 * @return string
 */
function personal_cta_threads_app_secret() {
	return personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_APP_SECRET', 'PERSONAL_CTA_THREADS_APP_SECRET' );
}

/**
 * Returns the token-encryption key material.
 *
 * @return string
 */
function personal_cta_threads_master_key() {
	return personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_MASTER_KEY', 'PERSONAL_CTA_THREADS_MASTER_KEY' );
}

/**
 * Returns the saved account record. It must never be returned to a browser.
 *
 * @return array<string, mixed>
 */
function personal_cta_threads_saved_account() {
	$account = get_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, array() );

	return is_array( $account ) ? $account : array();
}

/**
 * Encrypts an OAuth token for storage using AES-256-GCM.
 *
 * @param string $token Plain token.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_encrypt_token( $token ) {
	$master = personal_cta_threads_master_key();
	if ( '' === $master ) {
		return new WP_Error( 'pct_meta_master_key_missing', 'PERSONAL_CTA_THREADS_MASTER_KEY 설정이 필요합니다.' );
	}
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		return new WP_Error( 'pct_meta_crypto_unavailable', '서버에서 안전한 토큰 암호화를 사용할 수 없습니다.' );
	}

	try {
		$iv = random_bytes( 12 );
	} catch ( Exception $exception ) {
		return new WP_Error( 'pct_meta_crypto_failed', '토큰 암호화 준비에 실패했습니다.' );
	}

	$tag        = '';
	$ciphertext = openssl_encrypt(
		(string) $token,
		'aes-256-gcm',
		hash( 'sha256', $master, true ),
		OPENSSL_RAW_DATA,
		$iv,
		$tag
	);
	if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
		return new WP_Error( 'pct_meta_crypto_failed', '토큰 암호화에 실패했습니다.' );
	}

	return array(
		'v'          => 1,
		'iv'         => base64_encode( $iv ),
		'tag'        => base64_encode( $tag ),
		'ciphertext' => base64_encode( $ciphertext ),
	);
}

/**
 * Decrypts a saved OAuth token.
 *
 * @param mixed $envelope Encrypted token envelope.
 * @return string|WP_Error
 */
function personal_cta_threads_decrypt_token( $envelope ) {
	$master = personal_cta_threads_master_key();
	if ( '' === $master ) {
		return new WP_Error( 'pct_meta_master_key_missing', 'PERSONAL_CTA_THREADS_MASTER_KEY 설정이 필요합니다.' );
	}
	if ( ! function_exists( 'openssl_decrypt' ) || ! is_array( $envelope ) || 1 !== (int) ( isset( $envelope['v'] ) ? $envelope['v'] : 0 ) ) {
		return new WP_Error( 'pct_meta_token_invalid', '저장된 Threads 토큰을 읽을 수 없습니다.' );
	}

	$iv         = isset( $envelope['iv'] ) ? base64_decode( (string) $envelope['iv'], true ) : false;
	$tag        = isset( $envelope['tag'] ) ? base64_decode( (string) $envelope['tag'], true ) : false;
	$ciphertext = isset( $envelope['ciphertext'] ) ? base64_decode( (string) $envelope['ciphertext'], true ) : false;
	if ( false === $iv || false === $tag || false === $ciphertext || 12 !== strlen( $iv ) || 16 !== strlen( $tag ) ) {
		return new WP_Error( 'pct_meta_token_invalid', '저장된 Threads 토큰 형식이 올바르지 않습니다.' );
	}

	$token = openssl_decrypt(
		$ciphertext,
		'aes-256-gcm',
		hash( 'sha256', $master, true ),
		OPENSSL_RAW_DATA,
		$iv,
		$tag
	);
	if ( false === $token || '' === $token ) {
		return new WP_Error( 'pct_meta_token_invalid', '저장된 Threads 토큰을 복호화할 수 없습니다.' );
	}

	return $token;
}

/**
 * Saves a fully prepared OAuth account without autoloading its token.
 *
 * @param array<string, mixed> $account Account record.
 * @return void
 */
function personal_cta_threads_save_account( $account ) {
	if ( false === get_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, false ) ) {
		add_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, $account, '', false );
		return;
	}

	update_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, $account );
}

/**
 * Validates and encrypts a token entered on the plugin settings screen.
 *
 * @param string $user_id Threads user ID.
 * @param string $access_token Long-lived access token.
 * @param string $username Optional public username.
 * @param int    $expires_at Optional Unix expiry time.
 * @return true|WP_Error
 */
function personal_cta_threads_connect_token( $user_id, $access_token, $username = '', $expires_at = 0 ) {
	$user_id      = trim( (string) $user_id );
	$access_token = trim( (string) $access_token );
	if ( ! preg_match( '/^\d+$/', $user_id ) ) {
		return new WP_Error( 'pct_meta_user_id_invalid', 'Threads 사용자 ID가 올바르지 않습니다.' );
	}
	if ( '' === $access_token || strlen( $access_token ) > 4096 || preg_match( '/[\x00-\x20]/', $access_token ) ) {
		return new WP_Error( 'pct_meta_token_invalid', 'Threads 액세스 토큰 형식이 올바르지 않습니다.' );
	}

	$encrypted = personal_cta_threads_encrypt_token( $access_token );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}

	personal_cta_threads_save_account(
		array(
			'user_id'    => $user_id,
			'username'   => sanitize_text_field( $username ),
			'name'       => '',
			'token'      => $encrypted,
			'issued_at'  => time(),
			'expires_at' => max( 0, (int) $expires_at ),
			'source'     => 'manual',
		)
	);
	personal_cta_threads_ensure_token_refresh();

	return true;
}

/**
 * Returns internal publishing credentials from config or encrypted OAuth data.
 *
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_credentials() {
	$config_token   = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN', 'PERSONAL_CTA_THREADS_ACCESS_TOKEN' );
	$config_user_id = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_USER_ID', 'PERSONAL_CTA_THREADS_USER_ID' );
	if ( '' !== $config_token || '' !== $config_user_id ) {
		if ( '' === $config_token || ! preg_match( '/^\d+$/', $config_user_id ) ) {
			return new WP_Error( 'pct_meta_config_incomplete', 'Threads 액세스 토큰과 사용자 ID 설정을 모두 확인하세요.' );
		}

		return array(
			'access_token' => $config_token,
			'user_id'      => $config_user_id,
			'source'       => 'config',
			'expires_at'   => 0,
		);
	}

	$account = personal_cta_threads_saved_account();
	if ( empty( $account['user_id'] ) || ! preg_match( '/^\d+$/', (string) $account['user_id'] ) || empty( $account['token'] ) ) {
		return new WP_Error( 'pct_meta_not_connected', '먼저 Meta Threads 계정을 연결하세요.' );
	}
	if ( ! empty( $account['expires_at'] ) && (int) $account['expires_at'] <= time() ) {
		return new WP_Error( 'pct_meta_token_expired', 'Threads 연결 토큰이 만료되었습니다. 계정을 다시 연결하세요.' );
	}

	$token = personal_cta_threads_decrypt_token( $account['token'] );
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$source = isset( $account['source'] ) && in_array( $account['source'], array( 'manual', 'oauth' ), true ) ? (string) $account['source'] : 'oauth';

	return array(
		'access_token' => $token,
		'user_id'      => (string) $account['user_id'],
		'source'       => $source,
		'expires_at'   => isset( $account['expires_at'] ) ? (int) $account['expires_at'] : 0,
	);
}

/**
 * Returns browser-safe connection details; never includes a token or secret.
 *
 * @return array<string, string|int|bool>
 */
function personal_cta_threads_account() {
	$saved       = personal_cta_threads_saved_account();
	$credentials = personal_cta_threads_credentials();
	$source      = is_wp_error( $credentials ) ? '' : (string) $credentials['source'];

	return array(
		'connected'             => ! is_wp_error( $credentials ),
		'user_id'               => is_wp_error( $credentials ) ? '' : (string) $credentials['user_id'],
		'username'              => 'config' !== $source && isset( $saved['username'] ) ? (string) $saved['username'] : '',
		'name'                  => 'config' !== $source && isset( $saved['name'] ) ? (string) $saved['name'] : '',
		'expires_at'            => is_wp_error( $credentials ) ? 0 : (int) $credentials['expires_at'],
		'source'                => $source,
		'app_configured'        => '' !== personal_cta_threads_app_id() && '' !== personal_cta_threads_app_secret(),
		'encryption_configured' => '' !== personal_cta_threads_master_key(),
	);
}

/**
 * Performs a Meta request and returns decoded JSON without exposing secrets.
 *
 * @param string               $method HTTP method.
 * @param string               $endpoint Versioned path or absolute URL.
 * @param array<string, mixed> $parameters Request parameters.
 * @param bool                 $versioned Prefix the Threads API base.
 * @return array<string, mixed>|WP_Error
 */
function personal_cta_threads_meta_request( $method, $endpoint, $parameters = array(), $versioned = true ) {
	$method = strtoupper( (string) $method );
	$url    = $versioned ? PERSONAL_CTA_THREADS_API_BASE . '/' . ltrim( (string) $endpoint, '/' ) : (string) $endpoint;
	$args   = array(
		'method'      => $method,
		'timeout'     => 20,
		'redirection' => 0,
		'headers'     => array( 'Accept' => 'application/json' ),
	);

	if ( 'GET' === $method ) {
		$url = add_query_arg( $parameters, $url );
	} else {
		$args['body'] = $parameters;
	}

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'pct_meta_transport',
			'Meta Threads API에 연결하지 못했습니다.',
			array( 'ambiguous' => 'GET' !== $method )
		);
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$body   = (string) wp_remote_retrieve_body( $response );
	$data   = json_decode( $body, true );
	if ( $status < 200 || $status >= 300 ) {
		$meta_code    = is_array( $data ) && isset( $data['error']['code'] ) ? (int) $data['error']['code'] : 0;
		$meta_subcode = is_array( $data ) && isset( $data['error']['error_subcode'] ) ? (int) $data['error']['error_subcode'] : 0;

		return new WP_Error(
			'pct_meta_http',
			sprintf( 'Meta Threads API 요청이 실패했습니다. (HTTP %d, code %d)', $status, $meta_code ),
			array(
				'http_status' => $status,
				'meta_code'   => $meta_code,
				'meta_subcode' => $meta_subcode,
				'ambiguous'   => 'GET' !== $method && ( 408 === $status || 429 === $status || $status >= 500 ),
			)
		);
	}

	if ( ! is_array( $data ) ) {
		return new WP_Error(
			'pct_meta_invalid_response',
			'Meta Threads API 응답 형식이 올바르지 않습니다.',
			array( 'ambiguous' => 'GET' !== $method )
		);
	}

	return $data;
}

/**
 * Builds the OAuth authorization URL.
 *
 * @param string $redirect_uri Exact callback URI registered with Meta.
 * @param string $state One-time CSRF state verified by the caller.
 * @return string|WP_Error
 */
function personal_cta_threads_oauth_url( $redirect_uri, $state ) {
	$app_id = personal_cta_threads_app_id();
	if ( '' === $app_id || '' === personal_cta_threads_app_secret() ) {
		return new WP_Error( 'pct_meta_app_missing', 'Meta 앱 ID와 앱 시크릿을 먼저 설정하세요.' );
	}
	if ( '' === personal_cta_threads_master_key() ) {
		return new WP_Error( 'pct_meta_master_key_missing', 'PERSONAL_CTA_THREADS_MASTER_KEY 설정이 필요합니다.' );
	}

	$redirect_uri = esc_url_raw( (string) $redirect_uri );
	$state        = sanitize_text_field( (string) $state );
	if ( '' === $redirect_uri || '' === $state ) {
		return new WP_Error( 'pct_meta_oauth_invalid', 'OAuth 콜백 주소 또는 상태값이 올바르지 않습니다.' );
	}

	return add_query_arg(
		array(
			'client_id'     => $app_id,
			'redirect_uri'  => $redirect_uri,
			'scope'         => 'threads_basic,threads_content_publish',
			'response_type' => 'code',
			'state'         => $state,
		),
		PERSONAL_CTA_THREADS_AUTHORIZE_URL
	);
}

/**
 * Exchanges an OAuth code for a long-lived token and saves the account.
 * The caller must verify the state before invoking this function.
 *
 * @param string $code OAuth authorization code.
 * @param string $redirect_uri Exact callback URI used during authorization.
 * @return true|WP_Error
 */
function personal_cta_threads_oauth_callback( $code, $redirect_uri ) {
	$app_id       = personal_cta_threads_app_id();
	$app_secret   = personal_cta_threads_app_secret();
	$code         = trim( (string) $code );
	$redirect_uri = esc_url_raw( (string) $redirect_uri );
	if ( '' === $app_id || '' === $app_secret || '' === $code || '' === $redirect_uri ) {
		return new WP_Error( 'pct_meta_oauth_invalid', 'OAuth 연결 정보가 올바르지 않습니다.' );
	}

	$short = personal_cta_threads_meta_request(
		'POST',
		PERSONAL_CTA_THREADS_TOKEN_URL,
		array(
			'client_id'     => $app_id,
			'client_secret' => $app_secret,
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => $redirect_uri,
			'code'          => $code,
		),
		false
	);
	if ( is_wp_error( $short ) || empty( $short['access_token'] ) ) {
		return is_wp_error( $short ) ? $short : new WP_Error( 'pct_meta_oauth_failed', 'Meta 단기 토큰을 받지 못했습니다.' );
	}

	$long = personal_cta_threads_meta_request(
		'GET',
		PERSONAL_CTA_THREADS_LONG_TOKEN_URL,
		array(
			'grant_type'    => 'th_exchange_token',
			'client_secret' => $app_secret,
			'access_token'  => (string) $short['access_token'],
		),
		false
	);
	if ( is_wp_error( $long ) || empty( $long['access_token'] ) ) {
		return is_wp_error( $long ) ? $long : new WP_Error( 'pct_meta_oauth_failed', 'Meta 장기 토큰을 받지 못했습니다.' );
	}

	$profile = personal_cta_threads_meta_request(
		'GET',
		'me',
		array(
			'fields'       => 'id,username,name',
			'access_token' => (string) $long['access_token'],
		)
	);
	if ( is_wp_error( $profile ) || empty( $profile['id'] ) || ! preg_match( '/^\d+$/', (string) $profile['id'] ) ) {
		return is_wp_error( $profile ) ? $profile : new WP_Error( 'pct_meta_profile_failed', 'Threads 계정 정보를 확인하지 못했습니다.' );
	}

	$encrypted = personal_cta_threads_encrypt_token( (string) $long['access_token'] );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}

	$expires_in = isset( $long['expires_in'] ) ? max( 0, (int) $long['expires_in'] ) : 0;
	personal_cta_threads_save_account(
		array(
			'user_id'    => (string) $profile['id'],
			'username'   => isset( $profile['username'] ) ? sanitize_text_field( $profile['username'] ) : '',
			'name'       => isset( $profile['name'] ) ? sanitize_text_field( $profile['name'] ) : '',
			'token'      => $encrypted,
			'issued_at'  => time(),
			'expires_at' => $expires_in > 0 ? time() + $expires_in : 0,
			'source'     => 'oauth',
		)
	);
	personal_cta_threads_ensure_token_refresh();

	return true;
}

/**
 * Removes only OAuth credentials stored by this plugin.
 * Config-defined credentials are intentionally unaffected.
 *
 * @return void
 */
function personal_cta_threads_disconnect() {
	delete_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION );
	wp_unschedule_hook( PERSONAL_CTA_THREADS_REFRESH_HOOK );
}

/**
 * Refreshes a saved long-lived token when it is at least a day old and nearing expiry.
 *
 * @return true|false|WP_Error True if refreshed, false if no refresh was due.
 */
function personal_cta_threads_refresh_token() {
	$account = personal_cta_threads_saved_account();
	if ( empty( $account['token'] ) || empty( $account['issued_at'] ) ) {
		return false;
	}

	$issued_at  = (int) $account['issued_at'];
	$expires_at = isset( $account['expires_at'] ) ? (int) $account['expires_at'] : 0;
	if ( time() - $issued_at < 86400 || ( $expires_at > 0 && $expires_at - time() > 30 * DAY_IN_SECONDS ) ) {
		return false;
	}
	if ( $expires_at > 0 && $expires_at <= time() ) {
		return new WP_Error( 'pct_meta_token_expired', 'Threads 연결 토큰이 만료되었습니다. 계정을 다시 연결하세요.' );
	}

	$token = personal_cta_threads_decrypt_token( $account['token'] );
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$refreshed = personal_cta_threads_meta_request(
		'GET',
		PERSONAL_CTA_THREADS_REFRESH_URL,
		array(
			'grant_type'   => 'th_refresh_token',
			'access_token' => $token,
		),
		false
	);
	if ( is_wp_error( $refreshed ) || empty( $refreshed['access_token'] ) ) {
		return is_wp_error( $refreshed ) ? $refreshed : new WP_Error( 'pct_meta_refresh_failed', 'Threads 토큰을 갱신하지 못했습니다.' );
	}

	$encrypted = personal_cta_threads_encrypt_token( (string) $refreshed['access_token'] );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}

	$expires_in          = isset( $refreshed['expires_in'] ) ? max( 0, (int) $refreshed['expires_in'] ) : 0;
	$account['token']      = $encrypted;
	$account['issued_at']  = time();
	$account['expires_at'] = $expires_in > 0 ? time() + $expires_in : 0;
	personal_cta_threads_save_account( $account );

	return true;
}
add_action( PERSONAL_CTA_THREADS_REFRESH_HOOK, 'personal_cta_threads_refresh_token' );

/**
 * Makes sure connected OAuth accounts receive a daily refresh check.
 *
 * @return void
 */
function personal_cta_threads_ensure_token_refresh() {
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}
	$account = personal_cta_threads_saved_account();
	if ( ! empty( $account['token'] ) && ! wp_next_scheduled( PERSONAL_CTA_THREADS_REFRESH_HOOK ) ) {
		wp_schedule_event( time() + 3600, 'daily', PERSONAL_CTA_THREADS_REFRESH_HOOK );
	}
}
add_action( 'init', 'personal_cta_threads_ensure_token_refresh' );

/**
 * Converts a Meta media response to the plugin's published state.
 *
 * @param int                  $post_id Post ID.
 * @param string               $media_id Published media ID.
 * @param array<string, mixed> $media Optional media details.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_finalize_publish( $post_id, $media_id, $media = array() ) {
	$media_id = (string) $media_id;
	if ( '' === $media_id || ! personal_cta_threads_publish_checkpoint( $post_id, 'remote_id', $media_id ) ) {
		return new WP_Error( 'pct_meta_checkpoint_failed', 'Threads 게시 ID를 로컬에 저장하지 못했습니다.' );
	}
	$published_at = (int) personal_cta_threads_meta( $post_id, 'published_at', 0 );
	if ( 0 === $published_at ) {
		$published_at = time();
		personal_cta_threads_set_meta( $post_id, 'published_at', $published_at );
	}
	personal_cta_threads_set_meta( $post_id, 'text_hash', (string) personal_cta_threads_meta( $post_id, 'publish_text_hash' ) );
	personal_cta_threads_set_meta( $post_id, 'outbound_url', (string) personal_cta_threads_meta( $post_id, 'publish_outbound_url' ) );
	personal_cta_threads_set_meta( $post_id, 'link_mode', (string) personal_cta_threads_meta( $post_id, 'publish_link_mode' ) );
	if ( ! empty( $media['permalink'] ) ) {
		personal_cta_threads_set_meta( $post_id, 'remote_url', esc_url_raw( (string) $media['permalink'] ) );
	}
	delete_post_meta( $post_id, '_pct_threads_reconcile_attempts' );
	personal_cta_threads_set_meta( $post_id, 'publish_after_generate', 0 );
	personal_cta_threads_set_state( $post_id, 'published', 'published' );

	return array(
		'id'        => $media_id,
		'permalink' => (string) personal_cta_threads_meta( $post_id, 'remote_url' ),
		'published_at' => $published_at,
	);
}

/**
 * Fetches optional public details after a confirmed publish.
 *
 * @param string               $media_id Media ID.
 * @param array<string, mixed> $credentials Credentials.
 * @return array<string, mixed>
 */
function personal_cta_threads_media_details( $media_id, $credentials ) {
	$details = personal_cta_threads_meta_request(
		'GET',
		rawurlencode( (string) $media_id ),
		array(
			'fields'       => 'id,permalink,timestamp',
			'access_token' => (string) $credentials['access_token'],
		)
	);

	return is_wp_error( $details ) ? array() : $details;
}

/**
 * Persists and reads back a scalar checkpoint before a non-idempotent request.
 *
 * @param int              $post_id Post ID.
 * @param string           $key Meta key without prefix.
 * @param string|int|float $value Checkpoint value.
 * @return bool
 */
function personal_cta_threads_publish_checkpoint( $post_id, $key, $value ) {
	personal_cta_threads_set_meta( $post_id, $key, $value );

	return (string) $value === (string) personal_cta_threads_meta( $post_id, $key );
}

/**
 * Publishes a verified text exactly once from this plugin's perspective.
 *
 * @param int    $post_id Post ID.
 * @param string $text Verified Threads body without a raw link suffix.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_publish( $post_id, $text ) {
	$existing_id = (string) personal_cta_threads_meta( $post_id, 'remote_id' );
	if ( '' !== $existing_id ) {
		return personal_cta_threads_finalize_publish( $post_id, $existing_id );
	}

	if ( personal_cta_threads_meta( $post_id, 'publish_started_at', 0 ) ) {
		return personal_cta_threads_reconcile( $post_id );
	}

	$payload = personal_cta_threads_payload_text( $post_id, $text );
	if ( is_wp_error( $payload ) ) {
		return $payload;
	}
	$credentials = personal_cta_threads_credentials();
	if ( is_wp_error( $credentials ) ) {
		return $credentials;
	}

	$checkpoints = array(
		'publish_payload_text' => (string) $payload['text'],
		'publish_outbound_url' => (string) $payload['outbound_url'],
		'publish_link_mode'    => (string) $payload['link_mode'],
		'publish_text_hash'    => hash( 'sha256', (string) $payload['text'] ),
	);
	foreach ( $checkpoints as $key => $value ) {
		if ( ! personal_cta_threads_publish_checkpoint( $post_id, $key, $value ) ) {
			return new WP_Error( 'pct_meta_checkpoint_failed', '게시 안전 정보를 저장하지 못해 Threads 게시를 중단했습니다.' );
		}
	}
	personal_cta_threads_set_state( $post_id, 'publishing', 'creating_container' );

	$container_parameters = array(
		'media_type'  => 'TEXT',
		'text'        => (string) $payload['text'],
		'access_token' => (string) $credentials['access_token'],
	);
	if ( '' !== $payload['link_attachment'] ) {
		$container_parameters['link_attachment'] = (string) $payload['link_attachment'];
	}

	$container = personal_cta_threads_meta_request(
		'POST',
		rawurlencode( (string) $credentials['user_id'] ) . '/threads',
		$container_parameters
	);
	if ( is_wp_error( $container ) || empty( $container['id'] ) ) {
		return is_wp_error( $container ) ? $container : new WP_Error( 'pct_meta_container_failed', 'Threads 게시 컨테이너를 만들지 못했습니다.' );
	}

	$creation_id = (string) $container['id'];
	if ( ! personal_cta_threads_publish_checkpoint( $post_id, 'creation_id', $creation_id ) ) {
		return new WP_Error( 'pct_meta_checkpoint_failed', '게시 컨테이너 정보를 저장하지 못해 공개 게시를 중단했습니다.' );
	}
	personal_cta_threads_set_state( $post_id, 'publishing', 'publishing' );

	// This durable marker is written immediately before the non-idempotent call.
	$started_at = time();
	if ( ! personal_cta_threads_publish_checkpoint( $post_id, 'publish_started_at', $started_at ) ) {
		return new WP_Error( 'pct_meta_checkpoint_failed', '게시 시작 정보를 저장하지 못해 공개 게시를 중단했습니다.' );
	}
	$published = personal_cta_threads_meta_request(
		'POST',
		rawurlencode( (string) $credentials['user_id'] ) . '/threads_publish',
		array(
			'creation_id' => $creation_id,
			'access_token' => (string) $credentials['access_token'],
		)
	);

	if ( ! is_wp_error( $published ) && ! empty( $published['id'] ) ) {
		$media_id = (string) $published['id'];
		$confirmed = personal_cta_threads_finalize_publish( $post_id, $media_id );
		if ( is_wp_error( $confirmed ) ) {
			personal_cta_threads_schedule_reconcile( $post_id );
			return new WP_Error( 'pct_uncertain', 'Threads 게시 ID를 받았지만 로컬 저장을 확인하지 못했습니다. 재게시 없이 상태를 조회합니다.' );
		}
		$details   = personal_cta_threads_media_details( $media_id, $credentials );
		if ( ! empty( $details['permalink'] ) ) {
			$confirmed['permalink'] = esc_url_raw( (string) $details['permalink'] );
			personal_cta_threads_set_meta( $post_id, 'remote_url', $confirmed['permalink'] );
		}

		return $confirmed;
	}

	$error      = is_wp_error( $published ) ? $published : new WP_Error( 'pct_meta_invalid_response', 'Meta 게시 응답에 미디어 ID가 없습니다.', array( 'ambiguous' => true ) );
	$error_data = $error->get_error_data();
	$ambiguous = is_array( $error_data ) && ! empty( $error_data['ambiguous'] );
	if ( $ambiguous ) {
		$reconciled = personal_cta_threads_reconcile( $post_id );
		if ( ! is_wp_error( $reconciled ) ) {
			return $reconciled;
		}
		personal_cta_threads_schedule_reconcile( $post_id );
		return new WP_Error( 'pct_uncertain', 'Meta가 게시 요청을 받았는지 아직 확인할 수 없습니다. 자동 재게시 없이 조회로만 확인합니다.' );
	}

	// A definite 4xx rejection did not publish; only a later explicit action may retry.
	delete_post_meta( $post_id, '_pct_threads_publish_started_at' );
	delete_post_meta( $post_id, '_pct_threads_creation_id' );

	return new WP_Error( 'pct_meta_publish_failed', $error->get_error_message() );
}

/**
 * Finds an exact matching recent post after an ambiguous publish response.
 *
 * @param int                  $post_id Post ID.
 * @param array<string, mixed> $credentials Credentials.
 * @return array<string, mixed>|WP_Error|null
 */
function personal_cta_threads_find_recent_match( $post_id, $credentials ) {
	$recent = personal_cta_threads_meta_request(
		'GET',
		rawurlencode( (string) $credentials['user_id'] ) . '/threads',
		array(
			'fields'       => 'id,media_type,permalink,text,timestamp,link_attachment_url',
			'limit'        => 25,
			'access_token' => (string) $credentials['access_token'],
		)
	);
	if ( is_wp_error( $recent ) ) {
		return $recent;
	}

	$expected_text = (string) personal_cta_threads_meta( $post_id, 'publish_payload_text' );
	$expected_url  = (string) personal_cta_threads_meta( $post_id, 'publish_outbound_url' );
	$link_mode     = (string) personal_cta_threads_meta( $post_id, 'publish_link_mode' );
	$started_at    = (int) personal_cta_threads_meta( $post_id, 'publish_started_at', 0 );
	$matches       = array();
	$items         = isset( $recent['data'] ) && is_array( $recent['data'] ) ? $recent['data'] : array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || empty( $item['id'] ) || ! isset( $item['text'] ) || (string) $item['text'] !== $expected_text ) {
			continue;
		}
		if ( 'attachment' === $link_mode && ( ! isset( $item['link_attachment_url'] ) || (string) $item['link_attachment_url'] !== $expected_url ) ) {
			continue;
		}

		$timestamp = ! empty( $item['timestamp'] ) ? strtotime( (string) $item['timestamp'] ) : false;
		if ( $started_at > 0 && ( false === $timestamp || $timestamp < $started_at - 300 || $timestamp > time() + 300 ) ) {
			continue;
		}
		$matches[] = $item;
	}

	if ( 1 === count( $matches ) ) {
		return $matches[0];
	}
	if ( count( $matches ) > 1 ) {
		return new WP_Error( 'pct_uncertain', '동일한 Threads 게시물이 여러 개여서 자동으로 연결할 수 없습니다.' );
	}

	return null;
}

/**
 * Reconciles an in-flight publish using only read requests.
 *
 * @param int $post_id Post ID.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_reconcile( $post_id ) {
	$remote_id = (string) personal_cta_threads_meta( $post_id, 'remote_id' );
	if ( '' !== $remote_id ) {
		return personal_cta_threads_finalize_publish( $post_id, $remote_id );
	}

	$creation_id = (string) personal_cta_threads_meta( $post_id, 'creation_id' );
	$started_at  = (int) personal_cta_threads_meta( $post_id, 'publish_started_at', 0 );
	if ( '' === $creation_id ) {
		return new WP_Error( 'pct_meta_nothing_to_reconcile', '확인할 Threads 게시 요청이 없습니다.' );
	}
	if ( 0 === $started_at ) {
		delete_post_meta( $post_id, '_pct_threads_creation_id' );
		personal_cta_threads_set_state( $post_id, 'failed', 'publishing', '공개 게시 전 작업이 중단되었습니다. 다시 게시할 수 있습니다.' );
		return new WP_Error( 'pct_meta_publish_not_started', '공개 게시 전 작업이 중단되었습니다. 다시 게시하세요.' );
	}

	$credentials = personal_cta_threads_credentials();
	if ( is_wp_error( $credentials ) ) {
		return $credentials;
	}

	$status_result = personal_cta_threads_meta_request(
		'GET',
		rawurlencode( $creation_id ),
		array(
			'fields'       => 'id,status,error_message',
			'access_token' => (string) $credentials['access_token'],
		)
	);
	$status = is_wp_error( $status_result ) || empty( $status_result['status'] ) ? '' : strtoupper( (string) $status_result['status'] );

	$match = personal_cta_threads_find_recent_match( $post_id, $credentials );
	if ( is_array( $match ) && ! empty( $match['id'] ) ) {
		return personal_cta_threads_finalize_publish( $post_id, (string) $match['id'], $match );
	}

	if ( in_array( $status, array( 'ERROR', 'EXPIRED' ), true ) && null === $match ) {
		delete_post_meta( $post_id, '_pct_threads_publish_started_at' );
		delete_post_meta( $post_id, '_pct_threads_creation_id' );
		personal_cta_threads_set_state( $post_id, 'failed', 'publishing', 'Meta가 Threads 게시 요청을 완료하지 못했습니다.' );
		return new WP_Error( 'pct_meta_publish_failed', 'Meta가 Threads 게시 요청을 완료하지 못했습니다.' );
	}

	personal_cta_threads_set_state( $post_id, 'uncertain', 'reconciling', '게시 여부를 조회 중입니다. 같은 글을 다시 보내지 않습니다.' );
	return new WP_Error( 'pct_uncertain', '게시 여부를 아직 확인할 수 없습니다. 같은 글을 다시 보내지 않고 조회만 계속합니다.' );
}

/**
 * Schedules one read-only reconciliation if none is already queued.
 *
 * @param int $post_id Post ID.
 * @param int $delay Delay in seconds.
 * @return void
 */
function personal_cta_threads_schedule_reconcile( $post_id, $delay = 60 ) {
	$args = array( (int) $post_id );
	if ( ! wp_next_scheduled( PERSONAL_CTA_THREADS_RECONCILE_HOOK, $args ) ) {
		wp_schedule_single_event( time() + max( 10, (int) $delay ), PERSONAL_CTA_THREADS_RECONCILE_HOOK, $args, true );
	}
}

/**
 * Runs bounded, read-only recovery without ever reissuing threads_publish.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function personal_cta_threads_reconcile_job( $post_id ) {
	$result = personal_cta_threads_reconcile( $post_id );
	if ( ! is_wp_error( $result ) || 'pct_uncertain' !== $result->get_error_code() ) {
		return;
	}

	$attempts = (int) personal_cta_threads_meta( $post_id, 'reconcile_attempts', 0 ) + 1;
	personal_cta_threads_set_meta( $post_id, 'reconcile_attempts', $attempts );
	if ( $attempts < 10 ) {
		personal_cta_threads_schedule_reconcile( $post_id, min( 300, 30 * $attempts ) );
	}
}
add_action( PERSONAL_CTA_THREADS_RECONCILE_HOOK, 'personal_cta_threads_reconcile_job', 10, 1 );
