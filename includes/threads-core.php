<?php
/**
 * Shared state, source normalization, locks, and background orchestration.
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_THREADS_SETTINGS_OPTION', 'personal_cta_threads_settings' );
define( 'PERSONAL_CTA_THREADS_JOB_HOOK', 'personal_cta_threads_generate_job' );
define( 'PERSONAL_CTA_THREADS_WATCHDOG_HOOK', 'personal_cta_threads_watchdog' );

/**
 * Returns the saved Threads settings with stable defaults.
 *
 * @return array<string, mixed>
 */
function personal_cta_threads_settings() {
	$defaults = array(
		'enabled'      => false,
		'auto_publish' => false,
		'one_click'    => false,
		'include_link' => true,
		'link_mode'    => 'attachment',
		'add_utm'      => true,
		'model'        => 'gpt-5.6-sol',
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
	if ( defined( $constant ) && is_string( constant( $constant ) ) ) {
		return trim( constant( $constant ) );
	}

	$value = getenv( $environment );

	return is_string( $value ) ? trim( $value ) : '';
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
		'length'         => personal_cta_threads_length( (string) personal_cta_threads_meta( $post_id, 'final_text' ) ),
		'verifier_state' => (string) personal_cta_threads_meta( $post_id, 'verifier_state', 'not_run' ),
	);
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
 * Counts Threads text using Meta's documented emoji-byte behavior.
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

	return esc_url_raw( $url );
}

/**
 * Returns final Meta text and link attachment without letting the model create URLs.
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
	$url      = ! empty( $settings['include_link'] ) ? personal_cta_threads_outbound_url( $post_id ) : '';
	$mode     = ! empty( $settings['include_link'] ) ? (string) $settings['link_mode'] : 'none';
	$text     = $body;

	if ( '' !== $url && 'raw' === $mode ) {
		$text .= "\n\n" . $url;
	}

	$length = personal_cta_threads_length( $text );
	if ( $length > 500 ) {
		return new WP_Error( 'pct_text_too_long', sprintf( 'Threads 글이 500자를 초과했습니다. 현재 계산값: %d', $length ) );
	}

	return array(
		'body'            => $body,
		'text'            => $text,
		'link_attachment' => 'attachment' === $mode ? $url : '',
		'outbound_url'    => $url,
		'link_mode'       => $mode,
		'length'          => $length,
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
	if ( wp_next_scheduled( PERSONAL_CTA_THREADS_JOB_HOOK, $args ) ) {
		return true;
	}

	$result = wp_schedule_single_event( time() + max( 1, (int) $delay ), PERSONAL_CTA_THREADS_JOB_HOOK, $args, true );

	return false === $result ? new WP_Error( 'pct_schedule_failed', '다음 Threads 작업 예약에 실패했습니다.' ) : $result;
}

/**
 * Queues generation and optional publish.
 *
 * @param int  $post_id Post ID.
 * @param bool $publish Publish after generation and grounding verification.
 * @param bool $regenerate Reuse FACT map where possible.
 * @return true|WP_Error
 */
function personal_cta_threads_queue( $post_id, $publish = false, $regenerate = false ) {
	if ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'pct_forbidden', '이 글을 내보낼 권한이 없습니다.' );
	}
	if ( personal_cta_threads_meta( $post_id, 'remote_id' ) ) {
		return new WP_Error( 'pct_already_published', '이미 Threads에 게시된 글입니다.' );
	}

	personal_cta_threads_set_meta( $post_id, 'publish_after_generate', $publish ? 1 : 0 );
	personal_cta_threads_set_meta( $post_id, 'regenerate', $regenerate ? 1 : 0 );
	personal_cta_threads_set_state( $post_id, 'queued', 'queued' );

	$result = personal_cta_threads_continue_job( $post_id );
	if ( is_wp_error( $result ) ) {
		personal_cta_threads_set_state( $post_id, 'failed', 'queue', $result->get_error_message() );
		return $result;
	}

	return true;
}

/**
 * Handles the first publish transition only when automatic posting is enabled.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post Post.
 * @return void
 */
function personal_cta_threads_transition_post_status( $new_status, $old_status, $post ) {
	$settings = personal_cta_threads_settings();
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type || empty( $settings['enabled'] ) || empty( $settings['auto_publish'] ) ) {
		return;
	}
	if ( personal_cta_threads_meta( $post->ID, 'remote_id' ) ) {
		return;
	}

	$marker = 'personal_cta_threads_trigger_' . (int) $post->ID;
	if ( ! add_option( $marker, time(), '', false ) ) {
		return;
	}

	personal_cta_threads_set_meta( $post->ID, 'publish_after_generate', 1 );
	personal_cta_threads_set_meta( $post->ID, 'regenerate', 0 );
	personal_cta_threads_set_state( $post->ID, 'queued', 'auto_publish' );
	$result = personal_cta_threads_continue_job( $post->ID );
	if ( is_wp_error( $result ) || false === $result ) {
		personal_cta_threads_set_state( $post->ID, 'failed', 'queue', '자동 작업 예약에 실패했습니다. 설정 화면에서 재개하세요.' );
	}
}
add_action( 'transition_post_status', 'personal_cta_threads_transition_post_status', 10, 3 );

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
		return;
	}
	$keep_lease = false;

	try {
		personal_cta_threads_heartbeat( $post_id, 600 );
		$result = personal_cta_threads_generate( $post_id, (bool) personal_cta_threads_meta( $post_id, 'regenerate', 0 ) );
		if ( is_wp_error( $result ) ) {
			personal_cta_threads_set_state( $post_id, 'failed', 'generation', $result->get_error_message() );
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

		if ( personal_cta_threads_meta( $post_id, 'publish_after_generate', 0 ) ) {
			$verified = personal_cta_threads_verify( $post_id, $result['text'] );
			if ( is_wp_error( $verified ) ) {
				personal_cta_threads_set_state( $post_id, 'blocked', 'verifier', $verified->get_error_message() );
				return;
			}

			personal_cta_threads_set_state( $post_id, 'publishing', 'verified' );
			$published = personal_cta_threads_publish( $post_id, $result['text'] );
			if ( is_wp_error( $published ) ) {
				$status = 'pct_uncertain' === $published->get_error_code() ? 'uncertain' : 'failed';
				personal_cta_threads_set_state( $post_id, $status, 'publishing', $published->get_error_message() );
			}
		}
	} finally {
		if ( ! $keep_lease ) {
			delete_post_meta( $post_id, '_pct_threads_lease_until' );
		}
		personal_cta_threads_unlock( $lock );
	}
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
				array(
					'key'     => '_pct_threads_lease_until',
					'value'   => time(),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	foreach ( $post_ids as $post_id ) {
		$status = personal_cta_threads_meta( $post_id, 'status' );
		if ( 'publishing' === $status ) {
			if ( personal_cta_threads_meta( $post_id, 'creation_id' ) ) {
				wp_schedule_single_event( time() + 1, 'personal_cta_threads_reconcile_job', array( (int) $post_id ), true );
			} else {
				personal_cta_threads_continue_job( $post_id );
			}
		} elseif ( in_array( $status, array( 'queued', 'analyzing', 'drafting', 'editing', 'verifying' ), true ) ) {
			personal_cta_threads_continue_job( $post_id );
		} elseif ( 'ready' === $status && personal_cta_threads_meta( $post_id, 'publish_after_generate', 0 ) ) {
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
