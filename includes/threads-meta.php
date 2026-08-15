<?php
/**
 * Meta Threads account connection and text publishing.
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_THREADS_ACCOUNT_OPTION', 'personal_cta_threads_account' );
define( 'PERSONAL_CTA_THREADS_APP_SECRET_OPTION', 'personal_cta_threads_app_secret' );
define( 'PERSONAL_CTA_THREADS_APP_SECRET_AAD', 'personal_cta_threads_app_secret|v1' );
define( 'PERSONAL_CTA_THREADS_TOKEN_AAD', 'personal_cta_threads_access_token|v1' );
define( 'PERSONAL_CTA_THREADS_API_BASE', 'https://graph.threads.com/v1.0' );
define( 'PERSONAL_CTA_THREADS_AUTHORIZE_URL', 'https://www.threads.com/oauth/authorize' );
define( 'PERSONAL_CTA_THREADS_TOKEN_URL', 'https://graph.threads.com/oauth/access_token' );
define( 'PERSONAL_CTA_THREADS_LONG_TOKEN_URL', 'https://graph.threads.com/access_token' );
define( 'PERSONAL_CTA_THREADS_REFRESH_URL', 'https://graph.threads.com/refresh_access_token' );
define( 'PERSONAL_CTA_THREADS_REFRESH_HOOK', 'personal_cta_threads_refresh_token' );
define( 'PERSONAL_CTA_THREADS_RECONCILE_HOOK', 'personal_cta_threads_reconcile_job' );

/**
 * Returns the configured Threads app ID.
 *
 * @return string
 */
function personal_cta_threads_app_id() {
	$config = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_APP_ID', 'PERSONAL_CTA_THREADS_APP_ID' );
	if ( '' !== $config ) {
		return $config;
	}
	$settings = personal_cta_threads_settings();

	return isset( $settings['meta_app_id'] ) ? trim( (string) $settings['meta_app_id'] ) : '';
}

/**
 * Saves a Meta app secret without autoloading or displaying it again.
 *
 * @param string $secret App secret.
 * @return true|WP_Error
 */
function personal_cta_threads_save_app_secret( $secret ) {
	$secret = trim( (string) $secret );
	if ( '' === $secret || strlen( $secret ) > 1024 || preg_match( '/[\x00-\x20\x7F]/', $secret ) ) {
		return new WP_Error( 'pct_meta_app_secret_invalid', 'Threads App Secret 형식을 확인하세요.' );
	}
	$encrypted = personal_cta_threads_encrypt_secret( $secret, PERSONAL_CTA_THREADS_APP_SECRET_AAD );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}
	if ( false === get_option( PERSONAL_CTA_THREADS_APP_SECRET_OPTION, false ) ) {
		return add_option( PERSONAL_CTA_THREADS_APP_SECRET_OPTION, $encrypted, '', false )
			? true
			: new WP_Error( 'pct_meta_app_secret_save_failed', 'Threads App Secret을 저장하지 못했습니다.' );
	}
	update_option( PERSONAL_CTA_THREADS_APP_SECRET_OPTION, $encrypted, false );

	return true;
}

/**
 * Whether an administrator-entered app secret exists.
 *
 * @return bool
 */
function personal_cta_threads_has_saved_app_secret() {
	$value = get_option( PERSONAL_CTA_THREADS_APP_SECRET_OPTION, array() );

	return is_array( $value ) && ! empty( $value['ciphertext'] );
}

/**
 * Returns the configured Threads app secret.
 *
 * @return string|WP_Error
 */
function personal_cta_threads_app_secret() {
	$config = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_APP_SECRET', 'PERSONAL_CTA_THREADS_APP_SECRET' );
	if ( '' !== $config ) {
		return $config;
	}
	$stored = get_option( PERSONAL_CTA_THREADS_APP_SECRET_OPTION, array() );

	return is_array( $stored ) && ! empty( $stored )
		? personal_cta_threads_decrypt_secret( $stored, PERSONAL_CTA_THREADS_APP_SECRET_AAD )
		: '';
}

/**
 * Returns the saved account record without decrypting its token.
 *
 * @return array<string, mixed>
 */
function personal_cta_threads_saved_account() {
	$account = get_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, array() );

	return is_array( $account ) ? $account : array();
}

/**
 * Saves a connected account as a non-autoload option.
 *
 * @param array<string, mixed> $account Account record.
 * @return bool
 */
function personal_cta_threads_save_account( $account ) {
	if ( false === get_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, false ) ) {
		return add_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, $account, '', false );
	}

	return update_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION, $account, false );
}

/**
 * Reads a token saved by the pre-0.7 master-key envelope during one-way migration.
 *
 * @param mixed $envelope Legacy encrypted value.
 * @return string|WP_Error
 */
function personal_cta_threads_decrypt_legacy_token( $envelope ) {
	$master = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_MASTER_KEY', 'PERSONAL_CTA_THREADS_MASTER_KEY' );
	if ( '' === $master || ! is_array( $envelope ) || ! function_exists( 'openssl_decrypt' ) ) {
		return new WP_Error( 'pct_meta_legacy_token_unavailable', '기존 Threads 토큰을 읽을 수 없습니다.' );
	}
	$iv         = isset( $envelope['iv'] ) ? base64_decode( (string) $envelope['iv'], true ) : false;
	$tag        = isset( $envelope['tag'] ) ? base64_decode( (string) $envelope['tag'], true ) : false;
	$ciphertext = isset( $envelope['ciphertext'] ) ? base64_decode( (string) $envelope['ciphertext'], true ) : false;
	if ( false === $iv || false === $tag || false === $ciphertext || 12 !== strlen( $iv ) || 16 !== strlen( $tag ) ) {
		return new WP_Error( 'pct_meta_legacy_token_invalid', '기존 Threads 토큰 형식이 올바르지 않습니다.' );
	}
	$token = openssl_decrypt( $ciphertext, 'aes-256-gcm', hash( 'sha256', $master, true ), OPENSSL_RAW_DATA, $iv, $tag );

	return false !== $token && '' !== $token
		? $token
		: new WP_Error( 'pct_meta_legacy_token_invalid', '기존 Threads 토큰을 복호화할 수 없습니다.' );
}

/**
 * Performs one Meta request and preserves safe failure classification.
 *
 * @param string               $method HTTP method.
 * @param string               $endpoint Relative endpoint or absolute URL.
 * @param array<string, mixed> $parameters Parameters.
 * @param bool                 $versioned Whether to prefix the Graph API version.
 * @return array<string, mixed>|WP_Error
 */
function personal_cta_threads_meta_request( $method, $endpoint, $parameters = array(), $versioned = true ) {
	$method = strtoupper( (string) $method );
	$url    = $versioned ? PERSONAL_CTA_THREADS_API_BASE . '/' . ltrim( (string) $endpoint, '/' ) : (string) $endpoint;
	$args   = array(
		'method'      => $method,
		'timeout'     => 30,
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
		return new WP_Error( 'pct_meta_transport', 'Meta Threads API에 연결하지 못했습니다.', array( 'ambiguous' => 'GET' !== $method ) );
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $status < 200 || $status >= 300 ) {
		$code = is_array( $data ) && isset( $data['error']['code'] ) ? (int) $data['error']['code'] : 0;

		return new WP_Error(
			'pct_meta_http_' . $status,
			sprintf( 'Meta Threads API 요청이 실패했습니다. HTTP %d / code %d', $status, $code ),
			array( 'http_status' => $status, 'meta_code' => $code, 'ambiguous' => 'GET' !== $method && ( 408 === $status || 429 === $status || $status >= 500 ) )
		);
	}
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'pct_meta_invalid_response', 'Meta Threads API 응답 형식이 올바르지 않습니다.', array( 'ambiguous' => 'GET' !== $method ) );
	}

	return $data;
}

/**
 * Returns internal publishing credentials.
 *
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_credentials() {
	$config_token = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN', 'PERSONAL_CTA_THREADS_ACCESS_TOKEN' );
	$config_user  = personal_cta_threads_config_secret( 'PERSONAL_CTA_THREADS_USER_ID', 'PERSONAL_CTA_THREADS_USER_ID' );
	if ( '' !== $config_token || '' !== $config_user ) {
		return '' !== $config_token && preg_match( '/^\d+$/', $config_user )
			? array( 'access_token' => $config_token, 'user_id' => $config_user, 'source' => 'config', 'expires_at' => 0 )
			: new WP_Error( 'pct_meta_config_incomplete', 'Threads 사용자 ID와 액세스 토큰 설정을 모두 확인하세요.' );
	}

	$account = personal_cta_threads_saved_account();
	if ( empty( $account['user_id'] ) || ! preg_match( '/^\d+$/', (string) $account['user_id'] ) || empty( $account['token'] ) ) {
		return new WP_Error( 'pct_meta_not_connected', 'Threads 계정을 먼저 연결하세요.' );
	}
	if ( ! empty( $account['expires_at'] ) && (int) $account['expires_at'] <= time() ) {
		return new WP_Error( 'pct_meta_token_expired', 'Threads 연결 토큰이 만료되었습니다. 다시 연결하세요.' );
	}
	$token = personal_cta_threads_decrypt_secret( $account['token'], PERSONAL_CTA_THREADS_TOKEN_AAD );
	if ( is_wp_error( $token ) ) {
		$token = personal_cta_threads_decrypt_legacy_token( $account['token'] );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$migrated = personal_cta_threads_encrypt_secret( $token, PERSONAL_CTA_THREADS_TOKEN_AAD );
		if ( ! is_wp_error( $migrated ) ) {
			$account['token'] = $migrated;
			personal_cta_threads_save_account( $account );
		}
	}

	return array(
		'access_token' => $token,
		'user_id'      => (string) $account['user_id'],
		'source'       => isset( $account['source'] ) ? (string) $account['source'] : 'manual',
		'expires_at'   => isset( $account['expires_at'] ) ? (int) $account['expires_at'] : 0,
	);
}

/**
 * Browser-safe account status.
 *
 * @return array<string, string|int|bool>
 */
function personal_cta_threads_account() {
	$saved       = personal_cta_threads_saved_account();
	$credentials = personal_cta_threads_credentials();
	$secret      = personal_cta_threads_app_secret();

	return array(
		'connected'      => ! is_wp_error( $credentials ),
		'user_id'        => is_wp_error( $credentials ) ? '' : (string) $credentials['user_id'],
		'username'       => isset( $saved['username'] ) ? (string) $saved['username'] : '',
		'expires_at'     => is_wp_error( $credentials ) ? 0 : (int) $credentials['expires_at'],
		'source'         => is_wp_error( $credentials ) ? '' : (string) $credentials['source'],
		'app_configured' => '' !== personal_cta_threads_app_id() && ! is_wp_error( $secret ) && '' !== $secret,
	);
}

/**
 * Validates and stores a manually supplied long-lived token.
 *
 * @param string $user_id Threads user ID.
 * @param string $access_token Token.
 * @param string $username Optional username.
 * @return true|WP_Error
 */
function personal_cta_threads_connect_token( $user_id, $access_token, $username = '' ) {
	$user_id      = trim( (string) $user_id );
	$access_token = trim( (string) $access_token );
	if ( ! preg_match( '/^\d{1,32}$/', $user_id ) || '' === $access_token || strlen( $access_token ) > 4096 || preg_match( '/[\x00-\x20\x7F]/', $access_token ) ) {
		return new WP_Error( 'pct_meta_token_invalid', 'Threads 사용자 ID와 장기 액세스 토큰을 확인하세요.' );
	}
	$profile = personal_cta_threads_meta_request( 'GET', 'me', array( 'fields' => 'id,username', 'access_token' => $access_token ) );
	if ( is_wp_error( $profile ) ) {
		return $profile;
	}
	if ( empty( $profile['id'] ) || ! hash_equals( $user_id, (string) $profile['id'] ) ) {
		return new WP_Error( 'pct_meta_profile_mismatch', '토큰의 Threads 사용자 ID가 입력값과 다릅니다.' );
	}
	$encrypted = personal_cta_threads_encrypt_secret( $access_token, PERSONAL_CTA_THREADS_TOKEN_AAD );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}
	if ( ! personal_cta_threads_save_account( array(
		'user_id'    => $user_id,
		'username'   => '' !== $username ? sanitize_text_field( $username ) : sanitize_text_field( (string) ( $profile['username'] ?? '' ) ),
		'token'      => $encrypted,
		'issued_at'  => time(),
		'expires_at' => 0,
		'source'     => 'manual',
	) ) ) {
		return new WP_Error( 'pct_meta_account_save_failed', 'Threads 계정 연결을 저장하지 못했습니다.' );
	}
	personal_cta_threads_ensure_token_refresh();

	return true;
}

/**
 * Builds the OAuth authorization URL.
 *
 * @param string $redirect_uri Callback URI.
 * @param string $state CSRF state.
 * @return string|WP_Error
 */
function personal_cta_threads_oauth_url( $redirect_uri, $state ) {
	$secret = personal_cta_threads_app_secret();
	if ( '' === personal_cta_threads_app_id() || is_wp_error( $secret ) || '' === $secret ) {
		return new WP_Error( 'pct_meta_app_missing', 'Threads App ID와 App Secret을 먼저 저장하세요.' );
	}

	return add_query_arg( array(
		'client_id'     => personal_cta_threads_app_id(),
		'redirect_uri'  => esc_url_raw( $redirect_uri ),
		'scope'         => 'threads_basic,threads_content_publish',
		'response_type' => 'code',
		'state'         => sanitize_text_field( $state ),
	), PERSONAL_CTA_THREADS_AUTHORIZE_URL );
}

/**
 * Exchanges an OAuth code for a long-lived token.
 *
 * @param string $code Authorization code.
 * @param string $redirect_uri Callback URI.
 * @return true|WP_Error
 */
function personal_cta_threads_oauth_callback( $code, $redirect_uri ) {
	$secret = personal_cta_threads_app_secret();
	if ( is_wp_error( $secret ) || '' === $secret || '' === personal_cta_threads_app_id() ) {
		return new WP_Error( 'pct_meta_app_missing', 'Threads App ID와 App Secret을 확인하세요.' );
	}
	$short = personal_cta_threads_meta_request( 'POST', PERSONAL_CTA_THREADS_TOKEN_URL, array(
		'client_id' => personal_cta_threads_app_id(), 'client_secret' => $secret, 'grant_type' => 'authorization_code',
		'redirect_uri' => esc_url_raw( $redirect_uri ), 'code' => trim( (string) $code ),
	), false );
	if ( is_wp_error( $short ) || empty( $short['access_token'] ) ) {
		return is_wp_error( $short ) ? $short : new WP_Error( 'pct_meta_oauth_failed', 'Meta 단기 토큰을 받지 못했습니다.' );
	}
	$long = personal_cta_threads_meta_request( 'GET', PERSONAL_CTA_THREADS_LONG_TOKEN_URL, array(
		'grant_type' => 'th_exchange_token', 'client_secret' => $secret, 'access_token' => (string) $short['access_token'],
	), false );
	if ( is_wp_error( $long ) || empty( $long['access_token'] ) ) {
		return is_wp_error( $long ) ? $long : new WP_Error( 'pct_meta_oauth_failed', 'Meta 장기 토큰을 받지 못했습니다.' );
	}
	$profile = personal_cta_threads_meta_request( 'GET', 'me', array( 'fields' => 'id,username', 'access_token' => (string) $long['access_token'] ) );
	if ( is_wp_error( $profile ) || empty( $profile['id'] ) ) {
		return is_wp_error( $profile ) ? $profile : new WP_Error( 'pct_meta_profile_failed', 'Threads 계정 정보를 확인하지 못했습니다.' );
	}
	$encrypted = personal_cta_threads_encrypt_secret( (string) $long['access_token'], PERSONAL_CTA_THREADS_TOKEN_AAD );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}
	$expires_in = isset( $long['expires_in'] ) ? max( 0, (int) $long['expires_in'] ) : 0;
	if ( ! personal_cta_threads_save_account( array(
		'user_id' => (string) $profile['id'], 'username' => sanitize_text_field( (string) ( $profile['username'] ?? '' ) ),
		'token' => $encrypted, 'issued_at' => time(), 'expires_at' => $expires_in ? time() + $expires_in : 0, 'source' => 'oauth',
	) ) ) {
		return new WP_Error( 'pct_meta_account_save_failed', 'Threads 계정 연결을 저장하지 못했습니다.' );
	}
	personal_cta_threads_ensure_token_refresh();

	return true;
}

/** Disconnects the saved account. */
function personal_cta_threads_disconnect() {
	delete_option( PERSONAL_CTA_THREADS_ACCOUNT_OPTION );
	wp_unschedule_hook( PERSONAL_CTA_THREADS_REFRESH_HOOK );
}

/** Refreshes a saved long-lived token when due. */
function personal_cta_threads_refresh_token() {
	$account = personal_cta_threads_saved_account();
	if ( empty( $account['token'] ) || empty( $account['issued_at'] ) || time() - (int) $account['issued_at'] < DAY_IN_SECONDS ) {
		return false;
	}
	if ( ! empty( $account['expires_at'] ) && (int) $account['expires_at'] - time() > 30 * DAY_IN_SECONDS ) {
		return false;
	}
	$token = personal_cta_threads_decrypt_secret( $account['token'], PERSONAL_CTA_THREADS_TOKEN_AAD );
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$result = personal_cta_threads_meta_request( 'GET', PERSONAL_CTA_THREADS_REFRESH_URL, array( 'grant_type' => 'th_refresh_token', 'access_token' => $token ), false );
	if ( is_wp_error( $result ) || empty( $result['access_token'] ) ) {
		return is_wp_error( $result ) ? $result : new WP_Error( 'pct_meta_refresh_failed', 'Threads 토큰을 갱신하지 못했습니다.' );
	}
	$encrypted = personal_cta_threads_encrypt_secret( (string) $result['access_token'], PERSONAL_CTA_THREADS_TOKEN_AAD );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}
	$account['token']      = $encrypted;
	$account['issued_at']  = time();
	$account['expires_at'] = ! empty( $result['expires_in'] ) ? time() + (int) $result['expires_in'] : 0;
	personal_cta_threads_save_account( $account );

	return true;
}
add_action( PERSONAL_CTA_THREADS_REFRESH_HOOK, 'personal_cta_threads_refresh_token' );

/** Ensures the daily token refresh check exists. */
function personal_cta_threads_ensure_token_refresh() {
	if ( ! is_admin() && ! wp_doing_cron() ) {
		return;
	}
	if ( ! empty( personal_cta_threads_saved_account()['token'] ) && ! wp_next_scheduled( PERSONAL_CTA_THREADS_REFRESH_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PERSONAL_CTA_THREADS_REFRESH_HOOK );
	}
}
add_action( 'init', 'personal_cta_threads_ensure_token_refresh' );

/** Creates an unpublished text container. */
function personal_cta_threads_create_container( $text, $link_attachment = '' ) {
	$credentials = personal_cta_threads_credentials();
	if ( is_wp_error( $credentials ) ) {
		return $credentials;
	}
	$parameters = array( 'media_type' => 'TEXT', 'text' => (string) $text, 'access_token' => (string) $credentials['access_token'] );
	if ( '' !== $link_attachment ) {
		$parameters['link_attachment'] = (string) $link_attachment;
	}
	$result = personal_cta_threads_meta_request( 'POST', rawurlencode( (string) $credentials['user_id'] ) . '/threads', $parameters );

	return is_wp_error( $result ) || empty( $result['id'] )
		? ( is_wp_error( $result ) ? $result : new WP_Error( 'pct_meta_container_failed', 'Threads 게시 컨테이너를 만들지 못했습니다.' ) )
		: array( 'id' => (string) $result['id'] );
}

/** Publishes one previously saved container. */
function personal_cta_threads_publish_container( $creation_id ) {
	$credentials = personal_cta_threads_credentials();
	if ( is_wp_error( $credentials ) ) {
		return $credentials;
	}
	$result = personal_cta_threads_meta_request( 'POST', rawurlencode( (string) $credentials['user_id'] ) . '/threads_publish', array(
		'creation_id' => (string) $creation_id, 'access_token' => (string) $credentials['access_token'],
	) );

	return is_wp_error( $result ) || empty( $result['id'] )
		? ( is_wp_error( $result ) ? $result : new WP_Error( 'pct_meta_publish_failed', 'Threads 게시 결과를 확인하지 못했습니다.', array( 'ambiguous' => true ) ) )
		: array( 'id' => (string) $result['id'] );
}

/** Fetches the public permalink after a confirmed publish. */
function personal_cta_threads_media_url( $media_id ) {
	$credentials = personal_cta_threads_credentials();
	if ( is_wp_error( $credentials ) ) {
		return '';
	}
	$result = personal_cta_threads_meta_request( 'GET', rawurlencode( (string) $media_id ), array( 'fields' => 'id,permalink', 'access_token' => (string) $credentials['access_token'] ) );

	return is_array( $result ) && ! empty( $result['permalink'] ) ? esc_url_raw( (string) $result['permalink'] ) : '';
}

/** Finalizes a post-bound publish. */
function personal_cta_threads_finalize_post_publish( $post_id, $media_id ) {
	personal_cta_threads_set_meta( $post_id, 'remote_id', (string) $media_id );
	personal_cta_threads_set_meta( $post_id, 'published_at', time() );
	$url = personal_cta_threads_media_url( $media_id );
	if ( '' !== $url ) {
		personal_cta_threads_set_meta( $post_id, 'remote_url', $url );
	}
	personal_cta_threads_set_state( $post_id, 'published', 'published' );

	return array( 'id' => (string) $media_id, 'permalink' => $url );
}

/** Publishes a generated WordPress-post copy at most once. */
function personal_cta_threads_publish_post( $post_id, $body ) {
	$existing = (string) personal_cta_threads_meta( $post_id, 'remote_id' );
	if ( '' !== $existing ) {
		return array( 'id' => $existing, 'permalink' => (string) personal_cta_threads_meta( $post_id, 'remote_url' ) );
	}
	$payload = personal_cta_threads_payload_text( $post_id, $body );
	if ( is_wp_error( $payload ) ) {
		return $payload;
	}
	$creation_id = (string) personal_cta_threads_meta( $post_id, 'creation_id' );
	if ( '' === $creation_id ) {
		$container = personal_cta_threads_create_container( (string) $payload['text'] );
		if ( is_wp_error( $container ) ) {
			return $container;
		}
		$creation_id = (string) $container['id'];
		personal_cta_threads_set_meta( $post_id, 'creation_id', $creation_id );
	}
	personal_cta_threads_set_state( $post_id, 'publishing', 'publishing' );
	personal_cta_threads_set_meta( $post_id, 'publish_started_at', time() );
	$result = personal_cta_threads_publish_container( $creation_id );
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( is_array( $data ) && ! empty( $data['ambiguous'] ) ) {
			personal_cta_threads_set_state( $post_id, 'uncertain', 'publishing', '게시 여부를 확인할 수 없어 중복 발행을 중단했습니다.' );
			return new WP_Error( 'pct_uncertain', 'Threads 게시 여부를 확인할 수 없습니다. 같은 글을 자동으로 다시 보내지 않습니다.' );
		}
		delete_post_meta( $post_id, '_pct_threads_publish_started_at' );
		return $result;
	}

	return personal_cta_threads_finalize_post_publish( $post_id, (string) $result['id'] );
}
