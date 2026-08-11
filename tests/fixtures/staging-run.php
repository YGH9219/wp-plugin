<?php
/**
 * WP-CLI helper for the isolated staging workflow.
 */

$phase   = isset( $args[0] ) ? (string) $args[0] : 'status';
$post_id = isset( $args[1] ) ? absint( $args[1] ) : 5;

if ( 'prepare' === $phase ) {
	update_option(
		PERSONAL_CTA_THREADS_SETTINGS_OPTION,
		array(
			'enabled'        => true,
			'auto_publish'   => false,
			'one_click'      => false,
			'include_link'   => true,
			'link_mode'      => 'attachment',
			'add_utm'        => true,
			'model'          => 'gpt-5.6-sol',
			'style_examples' => array(),
		),
		false
	);
	foreach ( array_keys( get_post_meta( $post_id ) ) as $key ) {
		if ( 0 === strpos( $key, '_pct_threads_' ) ) {
			delete_post_meta( $post_id, $key );
		}
	}
	delete_option( 'personal_cta_threads_' . $post_id . '.lock' );
	delete_option( 'personal_cta_threads_trigger_' . $post_id );
	delete_option( 'pct_threads_fixture_log' );
	wp_set_current_user( 1 );
	$result = personal_cta_threads_queue( $post_id, false, false );
	echo is_wp_error( $result ) ? $result->get_error_code() : 'queued';
	return;
}

if ( 'publish' === $phase ) {
	wp_set_current_user( 1 );
	$result = personal_cta_threads_queue( $post_id, true, false );
	echo is_wp_error( $result ) ? $result->get_error_code() : 'publish_queued';
	return;
}

if ( 'duplicate' === $phase ) {
	$before = count( (array) get_option( 'pct_threads_fixture_log', array() ) );
	$result = personal_cta_threads_publish( $post_id, (string) personal_cta_threads_meta( $post_id, 'final_text' ) );
	$after  = count( (array) get_option( 'pct_threads_fixture_log', array() ) );
	echo wp_json_encode(
		array(
			'result_id'         => is_wp_error( $result ) ? '' : (string) $result['id'],
			'new_http_requests' => $after - $before,
		),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	return;
}

if ( 'auto_prepare' === $phase ) {
	$settings                 = personal_cta_threads_settings();
	$settings['enabled']      = true;
	$settings['auto_publish'] = true;
	update_option( PERSONAL_CTA_THREADS_SETTINGS_OPTION, $settings, false );
	$post_id = wp_insert_post(
		array(
			'post_title'   => 'Threads 자동 게시 스테이징 테스트',
			'post_content' => '<!-- wp:paragraph --><p>이 글은 최초 발행 자동 게시 흐름을 검증하기 위한 격리된 테스트 글입니다.</p><!-- /wp:paragraph -->',
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => 1,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		echo $post_id->get_error_code();
		return;
	}
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	update_option( 'pct_threads_fixture_auto_post_id', $post_id, false );
	echo (int) $post_id;
	return;
}

if ( 'auto_republish' === $phase ) {
	$post_id = (int) get_option( 'pct_threads_fixture_auto_post_id', 0 );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	echo wp_next_scheduled( PERSONAL_CTA_THREADS_JOB_HOOK, array( $post_id ) ) ? 'unexpected_queue' : 'no_duplicate_queue';
	return;
}

if ( 'auto_cleanup' === $phase ) {
	$post_id = (int) get_option( 'pct_threads_fixture_auto_post_id', 0 );
	if ( $post_id > 0 ) {
		wp_delete_post( $post_id, true );
		delete_option( 'personal_cta_threads_trigger_' . $post_id );
		delete_option( 'personal_cta_threads_' . $post_id . '.lock' );
	}
	delete_option( 'pct_threads_fixture_auto_post_id' );
	echo 'cleaned';
	return;
}

if ( 'auto_status' === $phase ) {
	$post_id = (int) get_option( 'pct_threads_fixture_auto_post_id', 0 );
}

$log        = (array) get_option( 'pct_threads_fixture_log', array() );
$openai     = array_values( array_filter( $log, static function ( $item ) { return isset( $item['kind'] ) && 'openai' === $item['kind']; } ) );
$meta       = array_values( array_filter( $log, static function ( $item ) { return isset( $item['kind'] ) && 'meta' === $item['kind']; } ) );
$state      = personal_cta_threads_state( $post_id );
$state['http_counts'] = array( 'openai' => count( $openai ), 'meta' => count( $meta ) );
$state['fact_count']  = count( (array) personal_cta_threads_meta( $post_id, 'fact_map', array() ) );
$state['draft_count'] = count( (array) personal_cta_threads_meta( $post_id, 'drafts', array() ) );
$state['link_mode_saved'] = (string) personal_cta_threads_meta( $post_id, 'publish_link_mode' );
$state['payload_has_url'] = false !== strpos( (string) personal_cta_threads_meta( $post_id, 'publish_payload_text' ), 'http' );
$state['outbound_has_utm'] = false !== strpos( (string) personal_cta_threads_meta( $post_id, 'publish_outbound_url' ), 'utm_source=threads' );
echo wp_json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
