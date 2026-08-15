<?php
/**
 * Shared state, source normalization, locks, and background orchestration.
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_THREADS_SETTINGS_OPTION', 'personal_cta_threads_settings' );
define( 'PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION', 'personal_cta_threads_openai_key' );
define( 'PERSONAL_CTA_THREADS_OPENAI_KEY_AAD', 'personal_cta_threads_openai_key|v1' );
define( 'PERSONAL_CTA_THREADS_JOB_HOOK', 'personal_cta_threads_generate_job' );
define( 'PERSONAL_CTA_THREADS_WATCHDOG_HOOK', 'personal_cta_threads_watchdog' );

/**
 * Returns the saved Threads settings with stable defaults.
 *
 * @return array<string, mixed>
 */
function personal_cta_threads_settings() {
	$defaults = array(
		'enabled'        => false,
		'include_link'   => true,
		'add_utm'        => true,
		'model'          => 'gpt-5.6-sol',
		'style_examples' => array(),
	);
	$saved    = get_option( PERSONAL_CTA_THREADS_SETTINGS_OPTION, array() );

	return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

/**
 * Reads a secret from a wp-config constant or environment variable.
 *
 * @param string $constant Constant name.
 * @param string $environment Environment variable name.
 * @return string
 */
function personal_cta_threads_config_secret( $constant, $environment ) {
	foreach ( array_unique( array( $constant, $environment ) ) as $name ) {
		if ( defined( $name ) && is_string( constant( $name ) ) ) {
			$value = trim( constant( $name ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
	}

	foreach ( array_unique( array( $constant, $environment ) ) as $name ) {
		$value = getenv( $name );
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return trim( $value );
		}
	}

	return '';
}

/**
 * Derives the local encryption key from the WordPress secret salts.
 *
 * @return string|WP_Error
 */
function personal_cta_threads_openai_storage_key() {
	if ( ! function_exists( 'wp_salt' ) || ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) || ! function_exists( 'openssl_get_cipher_methods' ) ) {
		return new WP_Error( 'pct_openai_storage_unavailable', '서버에서 API 키를 안전하게 저장할 수 없습니다.' );
	}
	$ciphers = openssl_get_cipher_methods();
	if ( ! is_array( $ciphers ) || ! in_array( 'aes-256-gcm', array_map( 'strtolower', $ciphers ), true ) ) {
		return new WP_Error( 'pct_openai_storage_unavailable', '서버에서 API 키 암호화를 지원하지 않습니다.' );
	}

	$salt = wp_salt( 'auth' );
	if ( ! is_string( $salt ) || '' === $salt ) {
		return new WP_Error( 'pct_openai_storage_unavailable', 'WordPress 보안 키를 읽을 수 없습니다.' );
	}

	$key = function_exists( 'hash_hkdf' )
		? hash_hkdf( 'sha256', $salt, 32, 'personal-cta-threads-openai-key' )
		: hash( 'sha256', 'personal-cta-threads-openai|' . $salt, true );

	return is_string( $key ) && 32 === strlen( $key )
		? $key
		: new WP_Error( 'pct_openai_storage_unavailable', 'API 키 암호화 키를 만들 수 없습니다.' );
}

/**
 * Encrypts an API key before it enters the WordPress options table.
 *
 * @param string $api_key API key.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_encrypt_openai_key( $api_key ) {
	$key = personal_cta_threads_openai_storage_key();
	if ( is_wp_error( $key ) ) {
		return $key;
	}

	try {
		$iv = random_bytes( 12 );
	} catch ( Exception $exception ) {
		return new WP_Error( 'pct_openai_storage_failed', 'API 키 암호화 준비에 실패했습니다.' );
	}

	$tag        = '';
	$ciphertext = openssl_encrypt( (string) $api_key, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, PERSONAL_CTA_THREADS_OPENAI_KEY_AAD );
	if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
		return new WP_Error( 'pct_openai_storage_failed', 'API 키 암호화에 실패했습니다.' );
	}

	return array(
		'v'          => 1,
		'iv'         => base64_encode( $iv ),
		'tag'        => base64_encode( $tag ),
		'ciphertext' => base64_encode( $ciphertext ),
	);
}

/**
 * Decrypts a stored API key only for a server-side OpenAI request.
 *
 * @param mixed $envelope Stored encrypted key record.
 * @return string|WP_Error
 */
function personal_cta_threads_decrypt_openai_key( $envelope ) {
	$key = personal_cta_threads_openai_storage_key();
	if ( is_wp_error( $key ) ) {
		return $key;
	}
	if ( ! is_array( $envelope ) || 1 !== (int) ( $envelope['v'] ?? 0 ) ) {
		return new WP_Error( 'pct_openai_storage_invalid', '저장된 API 키 형식이 올바르지 않습니다.' );
	}

	$iv         = isset( $envelope['iv'] ) ? base64_decode( (string) $envelope['iv'], true ) : false;
	$tag        = isset( $envelope['tag'] ) ? base64_decode( (string) $envelope['tag'], true ) : false;
	$ciphertext = isset( $envelope['ciphertext'] ) ? base64_decode( (string) $envelope['ciphertext'], true ) : false;
	if ( false === $iv || false === $tag || false === $ciphertext || 12 !== strlen( $iv ) || 16 !== strlen( $tag ) ) {
		return new WP_Error( 'pct_openai_storage_invalid', '저장된 API 키 형식이 올바르지 않습니다.' );
	}

	$api_key = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, PERSONAL_CTA_THREADS_OPENAI_KEY_AAD );
	if ( false === $api_key || '' === $api_key ) {
		return new WP_Error( 'pct_openai_storage_invalid', '저장된 API 키를 읽을 수 없습니다. 새 키를 입력해 교체하세요.' );
	}

	return $api_key;
}

/**
 * Returns the encrypted administrator-entered API key without exposing it.
 *
 * @return string|WP_Error
 */
function personal_cta_threads_saved_openai_key() {
	$envelope = get_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION, array() );

	return is_array( $envelope ) && ! empty( $envelope ) ? personal_cta_threads_decrypt_openai_key( $envelope ) : '';
}

/**
 * Checks for an encrypted administrator-entered API key without decrypting it.
 *
 * @return bool
 */
function personal_cta_threads_has_saved_openai_key() {
	$envelope = get_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION, array() );

	return is_array( $envelope )
		&& 1 === (int) ( $envelope['v'] ?? 0 )
		&& ! empty( $envelope['iv'] )
		&& ! empty( $envelope['tag'] )
		&& ! empty( $envelope['ciphertext'] );
}

/**
 * Encrypts and stores a validated administrator-entered OpenAI API key.
 *
 * @param string $api_key API key.
 * @return true|WP_Error
 */
function personal_cta_threads_save_openai_key( $api_key ) {
	$api_key = trim( (string) $api_key );
	if ( '' === $api_key || strlen( $api_key ) > 1024 || preg_match( '/[\x00-\x20\x7F]/', $api_key ) ) {
		return new WP_Error( 'pct_openai_key_invalid', 'OpenAI API 키 형식을 확인하세요.' );
	}

	$encrypted = personal_cta_threads_encrypt_openai_key( $api_key );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}

	if ( false === get_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION, false ) ) {
		if ( ! add_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION, $encrypted, '', false ) ) {
			return new WP_Error( 'pct_openai_storage_failed', 'API 키를 저장하지 못했습니다.' );
		}
	} else {
		update_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION, $encrypted, false );
	}

	return true;
}

/**
 * Deletes the encrypted administrator-entered API key.
 *
 * @return void
 */
function personal_cta_threads_delete_openai_key() {
	delete_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION );
}

/**
 * Returns a single post-meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key Unprefixed key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function personal_cta_threads_meta( $post_id, $key, $default = '' ) {
	$value = get_post_meta( $post_id, '_pct_threads_' . $key, true );

	return '' === $value ? $default : $value;
}

/**
 * Saves a single post-meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key Unprefixed key.
 * @param mixed  $value Value.
 * @return void
 */
function personal_cta_threads_set_meta( $post_id, $key, $value ) {
	update_post_meta( $post_id, '_pct_threads_' . $key, $value );
}

/**
 * Records state and an optional safe error code/message.
 *
 * @param int    $post_id Post ID.
 * @param string $status State.
 * @param string $stage Stage.
 * @param string $error Error for an administrator; never pass secrets.
 * @return void
 */
function personal_cta_threads_set_state( $post_id, $status, $stage = '', $error = '' ) {
	personal_cta_threads_set_meta( $post_id, 'status', sanitize_key( $status ) );
	personal_cta_threads_set_meta( $post_id, 'stage', sanitize_key( $stage ) );
	personal_cta_threads_set_meta( $post_id, 'updated_at', time() );

	if ( '' !== $error ) {
		personal_cta_threads_set_meta( $post_id, 'last_error', sanitize_text_field( $error ) );
	} elseif ( 'failed' !== $status && 'blocked' !== $status && 'uncertain' !== $status ) {
		delete_post_meta( $post_id, '_pct_threads_last_error' );
	}
}

/**
 * Returns a public-safe state payload for the administrator UI.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function personal_cta_threads_state( $post_id ) {
	return array(
		'post_id'        => (int) $post_id,
		'status'         => (string) personal_cta_threads_meta( $post_id, 'status', 'idle' ),
		'stage'          => (string) personal_cta_threads_meta( $post_id, 'stage' ),
		'text'           => (string) personal_cta_threads_meta( $post_id, 'final_text' ),
		'ai_original'    => (string) personal_cta_threads_meta( $post_id, 'ai_original' ),
		'last_error'     => (string) personal_cta_threads_meta( $post_id, 'last_error' ),
		'remote_id'      => (string) personal_cta_threads_meta( $post_id, 'remote_id' ),
		'remote_url'     => (string) personal_cta_threads_meta( $post_id, 'remote_url' ),
		'published_at'   => (int) personal_cta_threads_meta( $post_id, 'published_at', 0 ),
		'updated_at'     => (int) personal_cta_threads_meta( $post_id, 'updated_at', 0 ),
		'last_heartbeat' => (int) personal_cta_threads_meta( $post_id, 'last_heartbeat', 0 ),
		'lease_until'    => (int) personal_cta_threads_meta( $post_id, 'lease_until', 0 ),
		'length'         => personal_cta_threads_length( (string) personal_cta_threads_meta( $post_id, 'final_text' ) ),
		'verifier_state' => (string) personal_cta_threads_meta( $post_id, 'verifier_state', 'not_run' ),
	);
}

/**
 * Whether a copy-generation job is still expected to run.
 *
 * @param string $status State.
 * @return bool
 */
function personal_cta_threads_is_working( $status ) {
	return in_array( (string) $status, array( 'queued', 'analyzing', 'drafting', 'editing' ), true );
}

/**
 * Normalizes whitespace while retaining useful line breaks.
 *
 * @param string $value Input text or HTML.
 * @return string
 */
function personal_cta_threads_clean_text( $value ) {
	if ( function_exists( 'strip_shortcodes' ) ) {
		$value = strip_shortcodes( (string) $value );
	}
	$value = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', (string) $value );
	$value = preg_replace( '/<!--.*?-->/s', ' ', (string) $value );
	$value = preg_replace( '#</(?:p|li|blockquote|tr|h[1-6])>#i', "\n", (string) $value );
	$value = preg_replace( '#</(?:td|th)>#i', ' | ', (string) $value );
	$value = html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$value = preg_replace( '/[\t ]+/u', ' ', $value );
	$value = preg_replace( '/ *\n */u', "\n", $value );
	$value = preg_replace( '/\n{3,}/u', "\n\n", $value );

	return trim( $value );
}

/**
 * Counts Unicode characters without requiring the optional mbstring extension.
 *
 * @param string $text Text.
 * @return int
 */
function personal_cta_threads_character_length( $text ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $text, 'UTF-8' );
	}

	$result = preg_match_all( '/./us', $text, $matches );

	return false === $result ? strlen( $text ) : (int) $result;
}

/**
 * Converts parsed Gutenberg blocks to semantic text units without rendering
 * shortcodes or dynamic blocks.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @param array<int, string>               $units Output units.
 * @return void
 */
function personal_cta_threads_blocks_to_units( $blocks, &$units ) {
	$excluded = array(
		'personal-cta-blocks/pulse-button',
		'core/shortcode',
		'core/embed',
		'core/query',
		'core/post-content',
	);

	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( in_array( $name, $excluded, true ) ) {
			continue;
		}

		$inner_blocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		if ( ! empty( $inner_blocks ) ) {
			personal_cta_threads_blocks_to_units( $inner_blocks, $units );
			continue;
		}

		$text = personal_cta_threads_clean_text( isset( $block['innerHTML'] ) ? $block['innerHTML'] : '' );
		if ( '' === $text ) {
			continue;
		}

		if ( 'core/heading' === $name ) {
			$level = isset( $block['attrs']['level'] ) ? max( 2, min( 6, (int) $block['attrs']['level'] ) ) : 2;
			$text  = str_repeat( '#', $level ) . ' ' . $text;
		} elseif ( 'core/list-item' === $name ) {
			$text = '- ' . $text;
		} elseif ( 'core/quote' === $name ) {
			$text = '> ' . str_replace( "\n", "\n> ", $text );
		}

		$units[] = $text;
	}
}

/**
 * Builds the model source document and a stable content hash.
 *
 * @param int $post_id Post ID.
 * @return array<string, string>|WP_Error
 */
function personal_cta_threads_source( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'pct_invalid_post', '일반 WordPress 글만 Threads로 내보낼 수 있습니다.' );
	}

	$units = array( '제목: ' . personal_cta_threads_clean_text( get_the_title( $post_id ) ) );
	if ( function_exists( 'parse_blocks' ) ) {
		personal_cta_threads_blocks_to_units( parse_blocks( $post->post_content ), $units );
	} else {
		$units[] = personal_cta_threads_clean_text( $post->post_content );
	}

	$units = array_values( array_filter( array_map( 'trim', $units ) ) );
	$rows  = array();
	foreach ( $units as $index => $unit ) {
		$rows[] = sprintf( '[S%03d] %s', $index + 1, $unit );
	}

	$source = implode( "\n\n", $rows );
	if ( '' === $source ) {
		return new WP_Error( 'pct_empty_source', 'AI에 전달할 원문이 없습니다.' );
	}
	if ( personal_cta_threads_character_length( $source ) > 50000 ) {
		return new WP_Error( 'pct_source_too_long', '원문이 너무 깁니다. 50,000자 이하로 정리한 뒤 다시 시도하세요.' );
	}

	return array(
		'text' => $source,
		'hash' => hash( 'sha256', 'normalizer-1|' . $source ),
		'url'  => get_permalink( $post_id ),
	);
}

/**
 * Counts Threads text using its documented emoji-byte behavior.
 *
 * @param string $text Text.
 * @return int
 */
function personal_cta_threads_length( $text ) {
	if ( '' === $text ) {
		return 0;
	}

	$matched = preg_match_all( '/\X/u', $text, $matches );
	if ( false === $matched || empty( $matches[0] ) ) {
		return personal_cta_threads_character_length( $text );
	}
	$clusters = $matches[0];

	$length = 0;
	foreach ( $clusters as $cluster ) {
		$is_emoji = preg_match( '/[\x{1F000}-\x{1FAFF}\x{1F1E6}-\x{1F1FF}\x{2600}-\x{27BF}\x{20E3}]/u', $cluster );
		$length  += $is_emoji ? strlen( $cluster ) : personal_cta_threads_character_length( $cluster );
	}

	return $length;
}

/**
 * Creates the exact public outbound URL once, including optional UTM values.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function personal_cta_threads_outbound_url( $post_id ) {
	$url      = get_permalink( $post_id );
	$settings = personal_cta_threads_settings();

	if ( ! empty( $settings['add_utm'] ) ) {
		$url = add_query_arg(
			array(
				'utm_source'   => 'threads',
				'utm_medium'   => 'social',
				'utm_campaign' => 'post_' . (int) $post_id,
			),
			$url
		);
	}

	$url   = esc_url_raw( $url );
	$parts = explode( '?', $url, 2 );

	// Keep Korean slugs readable when copied. Browsers encode this path on
	// request, while Threads no longer has to count every percent-encoded byte.
	$parts[0] = rawurldecode( $parts[0] );

	return implode( '?', $parts );
}

/**
 * Returns the manual-copy text without letting the model create URLs.
 *
 * @param int    $post_id Post ID.
 * @param string $body Model/user body.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_payload_text( $post_id, $body ) {
	$settings = personal_cta_threads_settings();
	$body     = trim( sanitize_textarea_field( $body ) );
	if ( '' === $body ) {
		return new WP_Error( 'pct_empty_text', 'Threads 본문이 비어 있습니다.' );
	}
	if ( preg_match( '#https?://#i', $body ) ) {
		return new WP_Error( 'pct_body_contains_url', '링크는 플러그인이 자동으로 붙입니다. 본문 URL을 제거하세요.' );
	}
	$url  = ! empty( $settings['include_link'] ) ? personal_cta_threads_outbound_url( $post_id ) : '';
	$text = $body;

	if ( '' !== $url ) {
		$text .= "\n\n" . $url;
	}

	$length = personal_cta_threads_length( $text );
	if ( $length > 500 ) {
		return new WP_Error( 'pct_text_too_long', sprintf( 'Threads 글이 500자를 초과했습니다. 현재 계산값: %d', $length ) );
	}

	return array(
		'body'         => $body,
		'text'         => $text,
		'outbound_url' => $url,
		'length'       => $length,
	);
}

/**
 * Acquires a per-post option lock. Native add_option() supplies the unique key.
 *
 * @param int $post_id Post ID.
 * @param int $ttl Lock lifetime.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_lock( $post_id, $ttl = 300 ) {
	global $wpdb;

	$key   = 'personal_cta_threads_' . (int) $post_id . '.lock';
	$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'pct_', true );
	$value = wp_json_encode( array( 'token' => $token, 'expires' => time() + max( 60, (int) $ttl ) ) );

	if ( add_option( $key, $value, '', false ) ) {
		return array( 'key' => $key, 'token' => $token, 'value' => $value );
	}

	$current = get_option( $key, '' );
	$decoded = is_string( $current ) ? json_decode( $current, true ) : null;
	if ( ! is_array( $decoded ) || empty( $decoded['expires'] ) || (int) $decoded['expires'] >= time() ) {
		return new WP_Error( 'pct_locked', '다른 Threads 작업이 이미 실행 중입니다.' );
	}

	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$key,
			maybe_serialize( $current )
		)
	);
	wp_cache_delete( $key, 'options' );

	if ( 1 === $deleted && add_option( $key, $value, '', false ) ) {
		return array( 'key' => $key, 'token' => $token, 'value' => $value );
	}

	return new WP_Error( 'pct_locked', '다른 Threads 작업이 이미 실행 중입니다.' );
}

/**
 * Releases only the lock still owned by this worker.
 *
 * @param array<string, string|int> $lock Lock data.
 * @return void
 */
function personal_cta_threads_unlock( $lock ) {
	global $wpdb;

	if ( empty( $lock['key'] ) || empty( $lock['value'] ) ) {
		return;
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$lock['key'],
			maybe_serialize( $lock['value'] )
		)
	);
	wp_cache_delete( $lock['key'], 'options' );
}

/**
 * Refreshes a job lease used by the recovery watchdog.
 *
 * @param int $post_id Post ID.
 * @param int $seconds Lease duration.
 * @return void
 */
function personal_cta_threads_heartbeat( $post_id, $seconds = 240 ) {
	personal_cta_threads_set_meta( $post_id, 'last_heartbeat', time() );
	personal_cta_threads_set_meta( $post_id, 'lease_until', time() + max( 60, (int) $seconds ) );
}

/**
 * Schedules the next short job step without creating duplicate events.
 *
 * @param int $post_id Post ID.
 * @param int $delay Delay in seconds.
 * @return true|WP_Error
 */
function personal_cta_threads_continue_job( $post_id, $delay = 1 ) {
	$args = array( (int) $post_id );
	$delay = max( 0, (int) $delay );
	if ( wp_next_scheduled( PERSONAL_CTA_THREADS_JOB_HOOK, $args ) ) {
		return true;
	}

	$result = wp_schedule_single_event( time() + $delay, PERSONAL_CTA_THREADS_JOB_HOOK, $args, true );

	return false === $result ? new WP_Error( 'pct_schedule_failed', '다음 Threads 작업 예약에 실패했습니다.' ) : $result;
}

/**
 * Starts due jobs after the caller has released its post lock.
 *
 * @return void
 */
function personal_cta_threads_kick_cron() {
	if ( function_exists( 'spawn_cron' ) ) {
		spawn_cron();
	}
}

/**
 * Queues copy generation.
 *
 * @param int  $post_id Post ID.
 * @param bool $regenerate Reuse FACT map where possible.
 * @return true|WP_Error
 */
function personal_cta_threads_queue( $post_id, $regenerate = false ) {
	if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'pct_forbidden', '이 글의 문구를 만들 권한이 없습니다.' );
	}

	personal_cta_threads_set_meta( $post_id, 'regenerate', $regenerate ? 1 : 0 );
	personal_cta_threads_set_state( $post_id, 'queued', 'queued' );
	personal_cta_threads_heartbeat( $post_id, 600 );

	$result = personal_cta_threads_continue_job( $post_id, 0 );
	if ( is_wp_error( $result ) ) {
		delete_post_meta( $post_id, '_pct_threads_lease_until' );
		personal_cta_threads_set_state( $post_id, 'failed', 'queue', $result->get_error_message() );
		return $result;
	}

	return true;
}

/**
 * Schedules one bounded retry for a transient OpenAI transport failure.
 *
 * The marker is generation-wide rather than per stage. It avoids a cost loop
 * when a provider outage affects several consecutive checkpoints.
 *
 * @param int      $post_id Post ID.
 * @param WP_Error $error OpenAI request error.
 * @return true|false|WP_Error True when a retry was scheduled.
 */
function personal_cta_threads_retry_transient_error( $post_id, $error ) {
	if ( ! is_wp_error( $error ) || ! function_exists( 'personal_cta_threads_openai_retry_delay' ) ) {
		return false;
	}

	$delay = personal_cta_threads_openai_retry_delay( $error );
	if ( 0 >= $delay ) {
		return false;
	}

	$generation_id = (string) personal_cta_threads_meta( $post_id, 'generation_id' );
	$marker        = personal_cta_threads_meta( $post_id, 'transport_retry', array() );
	if ( is_array( $marker ) && '' !== $generation_id && $generation_id === ( isset( $marker['generation_id'] ) ? (string) $marker['generation_id'] : '' ) ) {
		return false;
	}

	$status = (string) personal_cta_threads_meta( $post_id, 'status', 'queued' );
	if ( ! personal_cta_threads_is_working( $status ) ) {
		return false;
	}

	personal_cta_threads_set_meta( $post_id, 'transport_retry', array(
		'generation_id' => $generation_id,
		'stage'         => (string) personal_cta_threads_meta( $post_id, 'stage', 'generation' ),
		'delay'         => (int) $delay,
	) );
	personal_cta_threads_set_state( $post_id, $status, 'retry_wait' );
	personal_cta_threads_heartbeat( $post_id, max( 90, (int) $delay + 60 ) );

	return personal_cta_threads_continue_job( $post_id, $delay );
}

/**
 * Executes a resumable generation job.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function personal_cta_threads_run_job( $post_id ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 );
	}
	$lock = personal_cta_threads_lock( $post_id, 1800 );
	if ( is_wp_error( $lock ) ) {
		$status         = (string) personal_cta_threads_meta( $post_id, 'status', 'idle' );
		$last_heartbeat = (int) personal_cta_threads_meta( $post_id, 'last_heartbeat', 0 );
		if ( personal_cta_threads_is_working( $status ) ) {
			if ( 0 === $last_heartbeat || $last_heartbeat + 600 < time() ) {
				personal_cta_threads_set_state( $post_id, $status, 'waiting_lock' );
				personal_cta_threads_heartbeat( $post_id, 90 );
			}
			$retry = personal_cta_threads_continue_job( $post_id, 90 );
			if ( is_wp_error( $retry ) ) {
				delete_post_meta( $post_id, '_pct_threads_lease_until' );
				personal_cta_threads_set_state( $post_id, 'failed', 'queue', $retry->get_error_message() );
			}
		}

		return;
	}
	$keep_lease = false;

	try {
		personal_cta_threads_heartbeat( $post_id, 600 );
		$result = personal_cta_threads_generate( $post_id, (bool) personal_cta_threads_meta( $post_id, 'regenerate', 0 ) );
		if ( is_wp_error( $result ) ) {
			$retry = personal_cta_threads_retry_transient_error( $post_id, $result );
			if ( true === $retry ) {
				$keep_lease = true;
				return;
			}
			if ( is_wp_error( $retry ) ) {
				personal_cta_threads_set_state( $post_id, 'failed', 'queue', $retry->get_error_message() );
				return;
			}

			$stage = (string) personal_cta_threads_meta( $post_id, 'stage', 'generation' );
			personal_cta_threads_set_state( $post_id, 'failed', '' !== $stage ? $stage : 'generation', $result->get_error_message() );
			return;
		}
		if ( ! empty( $result['pending'] ) ) {
			$keep_lease = true;
			return;
		}
		if ( empty( $result['text'] ) || ! is_string( $result['text'] ) ) {
			personal_cta_threads_set_state( $post_id, 'failed', 'generation', 'AI 생성 결과가 비어 있습니다.' );
			return;
		}

	} catch ( Throwable $error ) {
		$stage = (string) personal_cta_threads_meta( $post_id, 'stage', 'generation' );
		personal_cta_threads_set_state( $post_id, 'failed', '' !== $stage ? $stage : 'generation', '예기치 않은 서버 오류가 발생했습니다. 다시 생성하세요.' );
	} finally {
		if ( ! $keep_lease ) {
			delete_post_meta( $post_id, '_pct_threads_lease_until' );
		}
		personal_cta_threads_unlock( $lock );
	}
}

/**
 * Requeues a stalled job without discarding completed model checkpoints.
 *
 * @param int $post_id Post ID.
 * @return true|WP_Error
 */
function personal_cta_threads_resume( $post_id ) {
	$status      = (string) personal_cta_threads_meta( $post_id, 'status', 'idle' );
	$stage       = (string) personal_cta_threads_meta( $post_id, 'stage', 'queued' );
	$lease_until = (int) personal_cta_threads_meta( $post_id, 'lease_until', 0 );
	if ( ! personal_cta_threads_is_working( $status ) ) {
		return new WP_Error( 'pct_not_pending', '다시 예약할 Threads 작업이 없습니다.' );
	}
	if ( $lease_until >= time() ) {
		return new WP_Error( 'pct_busy', 'Threads 작업이 아직 진행 중입니다.' );
	}

	personal_cta_threads_set_state( $post_id, $status, '' !== $stage ? $stage : 'queued' );
	personal_cta_threads_heartbeat( $post_id, 600 );
	$result = personal_cta_threads_continue_job( $post_id, 0 );
	if ( is_wp_error( $result ) ) {
		delete_post_meta( $post_id, '_pct_threads_lease_until' );
		personal_cta_threads_set_state( $post_id, 'failed', 'queue', $result->get_error_message() );
		return $result;
	}

	return true;
}
add_action( PERSONAL_CTA_THREADS_JOB_HOOK, 'personal_cta_threads_run_job', 10, 1 );

/**
 * Adds a five-minute watchdog schedule.
 *
 * @param array<string, array<string, int|string>> $schedules Schedules.
 * @return array<string, array<string, int|string>>
 */
function personal_cta_threads_cron_schedules( $schedules ) {
	$schedules['personal_cta_five_minutes'] = array(
		'interval' => 300,
		'display'  => 'Every five minutes',
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'personal_cta_threads_cron_schedules' );

/**
 * Makes sure the watchdog exists without requiring an activation migration.
 *
 * @return void
 */
function personal_cta_threads_ensure_watchdog() {
	$settings = personal_cta_threads_settings();
	if ( empty( $settings['enabled'] ) || ( ! is_admin() && ! wp_doing_cron() ) ) {
		return;
	}
	if ( ! wp_next_scheduled( PERSONAL_CTA_THREADS_WATCHDOG_HOOK ) ) {
		wp_schedule_event( time() + 300, 'personal_cta_five_minutes', PERSONAL_CTA_THREADS_WATCHDOG_HOOK );
	}
}
add_action( 'init', 'personal_cta_threads_ensure_watchdog' );

/**
 * Requeues jobs whose process ended after consuming its Cron event.
 *
 * @return void
 */
function personal_cta_threads_watchdog() {
	$settings = personal_cta_threads_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	$post_ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 20,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_pct_threads_status',
					'value'   => array( 'queued', 'analyzing', 'drafting', 'editing' ),
					'compare' => 'IN',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_pct_threads_lease_until',
						'value'   => time(),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_pct_threads_lease_until',
						'compare' => 'NOT EXISTS',
					),
				),
			),
		)
	);

	foreach ( $post_ids as $post_id ) {
		$status = personal_cta_threads_meta( $post_id, 'status' );
		if ( personal_cta_threads_is_working( $status ) ) {
			personal_cta_threads_continue_job( $post_id );
		}
	}
}
add_action( PERSONAL_CTA_THREADS_WATCHDOG_HOOK, 'personal_cta_threads_watchdog' );

/**
 * Removes per-post option markers when a post is permanently deleted.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function personal_cta_threads_delete_post_options( $post_id ) {
	delete_option( 'personal_cta_threads_trigger_' . (int) $post_id );
	delete_option( 'personal_cta_threads_' . (int) $post_id . '.lock' );
}
add_action( 'before_delete_post', 'personal_cta_threads_delete_post_options' );

/**
 * Clears temporary schedules and locks when the plugin is disabled.
 *
 * @return void
 */
function personal_cta_threads_deactivate() {
	wp_unschedule_hook( PERSONAL_CTA_THREADS_JOB_HOOK );
	wp_unschedule_hook( PERSONAL_CTA_THREADS_WATCHDOG_HOOK );
	wp_unschedule_hook( 'personal_cta_threads_reconcile_job' );
	wp_unschedule_hook( 'personal_cta_threads_refresh_token' );
}
register_deactivation_hook( PERSONAL_CTA_BLOCKS_FILE, 'personal_cta_threads_deactivate' );

/**
 * Removes legacy Meta-only schedules once, without deleting saved credentials.
 *
 * @return void
 */
function personal_cta_threads_clear_legacy_meta_jobs() {
	if ( get_option( 'personal_cta_threads_meta_jobs_cleared' ) ) {
		return;
	}

	wp_unschedule_hook( 'personal_cta_threads_reconcile_job' );
	wp_unschedule_hook( 'personal_cta_threads_refresh_token' );
	add_option( 'personal_cta_threads_meta_jobs_cleared', time(), '', false );
}
add_action( 'init', 'personal_cta_threads_clear_legacy_meta_jobs', 1 );
