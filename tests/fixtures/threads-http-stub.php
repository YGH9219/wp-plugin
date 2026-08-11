<?php
/**
 * Plugin Name: Personal CTA Threads HTTP Fixture
 * Description: Test-only OpenAI and Meta responses for an isolated WordPress staging site.
 */

defined( 'ABSPATH' ) || exit;

defined( 'PERSONAL_CTA_OPENAI_API_KEY' ) || define( 'PERSONAL_CTA_OPENAI_API_KEY', 'fixture-openai-key' );
defined( 'PERSONAL_CTA_THREADS_USER_ID' ) || define( 'PERSONAL_CTA_THREADS_USER_ID', '123456789' );
defined( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN' ) || define( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN', 'fixture-meta-token' );

/**
 * Returns a normal WordPress HTTP response.
 *
 * @param array<string, mixed> $data Body data.
 * @return array<string, mixed>
 */
function pct_threads_fixture_response( $data ) {
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Records only method and endpoint; credentials and request bodies are excluded.
 *
 * @param string $kind Request kind.
 * @param string $method HTTP method.
 * @param string $url URL.
 * @return void
 */
function pct_threads_fixture_log( $kind, $method, $url ) {
	$log   = get_option( 'pct_threads_fixture_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'kind'   => sanitize_key( $kind ),
		'method' => sanitize_key( strtolower( $method ) ),
		'path'   => (string) wp_parse_url( $url, PHP_URL_PATH ),
		'time'   => time(),
	);
	update_option( 'pct_threads_fixture_log', array_slice( $log, -100 ), false );
}

/**
 * Reads the dynamic context sent after the stable developer prompt.
 *
 * @param array<string, mixed> $payload Responses request.
 * @return array<string, mixed>
 */
function pct_threads_fixture_context( $payload ) {
	$text = isset( $payload['input'][1]['content'][0]['text'] ) ? (string) $payload['input'][1]['content'][0]['text'] : '';
	$pos  = strpos( $text, "\n" );
	$data = json_decode( false === $pos ? $text : substr( $text, $pos + 1 ), true );

	return is_array( $data ) ? $data : array();
}

/**
 * Produces deterministic Structured Outputs for the full six-call workflow.
 *
 * @param array<string, mixed> $payload Responses request.
 * @return array<string, mixed>
 */
function pct_threads_fixture_openai_data( $payload ) {
	$name    = isset( $payload['text']['format']['name'] ) ? (string) $payload['text']['format']['name'] : '';
	$context = pct_threads_fixture_context( $payload );
	$source  = isset( $context['source_document'] ) ? (string) $context['source_document'] : '';
	if ( preg_match( '/^\[(S[0-9]+)\]\s*(.*?)(?=\n\n\[S[0-9]+\]|\z)/ms', $source, $match ) ) {
		$source_id = $match[1];
		$quote     = trim( preg_replace( '/\s+/u', ' ', $match[2] ) );
	} else {
		$source_id = 'S001';
		$quote     = '테스트 원문 제목';
	}
	if ( function_exists( 'mb_substr' ) ) {
		$quote = mb_substr( $quote, 0, 60, 'UTF-8' );
	} else {
		$quote = substr( $quote, 0, 60 );
	}

	if ( 'threads_fact' === $name ) {
		return array(
			'topic'            => '스테이징 전체 흐름 검증',
			'reader_problem'   => '원문의 핵심을 빠르게 확인해야 함',
			'primary_solution' => $quote,
			'facts'            => array(
				array(
					'id'            => 'F1',
					'claim'         => $quote,
					'evidence'      => array( array( 'source_id' => $source_id, 'quote' => $quote ) ),
					'must_preserve' => array(),
				),
			),
			'hook_angles'      => array(
				array( 'id' => 'H1', 'type' => 'convenience', 'premise' => '핵심을 바로 확인', 'fact_ids' => array( 'F1' ) ),
				array( 'id' => 'H2', 'type' => 'warning', 'premise' => '놓치기 쉬운 핵심', 'fact_ids' => array( 'F1' ) ),
				array( 'id' => 'H3', 'type' => 'other', 'premise' => '저장할 정보', 'fact_ids' => array( 'F1' ) ),
			),
			'blockers'         => array(),
		);
	}

	if ( 'threads_writer' === $name ) {
		$hook_id = isset( $context['hook_angle']['id'] ) ? (string) $context['hook_angle']['id'] : 'H1';
		$endings = array(
			'H1' => '핵심만 빠르게 확인해봐.',
			'H2' => '놓치기 전에 원문을 확인해둬.',
			'H3' => '필요할 때 다시 보게 저장해둬.',
		);
		return array(
			'text'          => $quote . "\n" . ( isset( $endings[ $hook_id ] ) ? $endings[ $hook_id ] : $endings['H1'] ),
			'hook_angle_id' => $hook_id,
			'fact_ids'      => array( 'F1' ),
			'claims'        => array( array( 'text' => $quote, 'fact_ids' => array( 'F1' ) ) ),
		);
	}

	if ( 'threads_editor' === $name || 'threads_repair' === $name ) {
		return array(
			'text'          => $quote . "\n필요할 때 바로 찾을 수 있게 원문을 확인해둬.",
			'hook_angle_id' => 'H1',
			'fact_ids'      => array( 'F1' ),
			'claims'        => array( array( 'text' => $quote, 'fact_ids' => array( 'F1' ) ) ),
		);
	}

	if ( 'threads_verifier' === $name ) {
		$checks = array();
		foreach ( isset( $context['candidate_units'] ) && is_array( $context['candidate_units'] ) ? $context['candidate_units'] : array() as $index => $unit ) {
			$is_fact  = 0 === $index;
			$checks[] = array(
				'unit_id'      => (string) $unit['id'],
				'claim'        => (string) $unit['text'],
				'verdict'      => $is_fact ? 'supported' : 'non_factual',
				'fact_ids'     => $is_fact ? array( 'F1' ) : array(),
				'evidence_ids' => $is_fact ? array( $source_id ) : array(),
				'reason'       => $is_fact ? '원문의 직접 인용과 일치함' : '사실을 추가하지 않는 행동 안내',
			);
		}
		return array( 'decision' => 'pass', 'checks' => $checks, 'issues' => array() );
	}

	return array();
}

/**
 * Prevents any real external call in the isolated staging test.
 *
 * @param false|array|WP_Error $preempt Existing preemption value.
 * @param array<string, mixed> $args Request arguments.
 * @param string               $url URL.
 * @return false|array|WP_Error
 */
function pct_threads_fixture_pre_http_request( $preempt, $args, $url ) {
	$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$method = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'GET';

	if ( 'api.openai.com' === $host ) {
		$payload = json_decode( isset( $args['body'] ) ? (string) $args['body'] : '', true );
		$data    = pct_threads_fixture_openai_data( is_array( $payload ) ? $payload : array() );
		pct_threads_fixture_log( 'openai', $method, $url );

		return pct_threads_fixture_response(
			array(
				'id'     => 'resp_fixture_' . wp_generate_password( 8, false, false ),
				'status' => 'completed',
				'output' => array(
					array(
						'type'    => 'message',
						'content' => array( array( 'type' => 'output_text', 'text' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ),
					),
				),
				'usage'  => array( 'input_tokens' => 100, 'output_tokens' => 50, 'total_tokens' => 150, 'input_tokens_details' => array( 'cached_tokens' => 0 ) ),
			)
		);
	}

	if ( 'graph.threads.com' === $host ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		pct_threads_fixture_log( 'meta', $method, $url );
		if ( 'POST' === $method && false !== strpos( $path, '/threads_publish' ) ) {
			return pct_threads_fixture_response( array( 'id' => '987654321' ) );
		}
		if ( 'POST' === $method && preg_match( '#/\d+/threads$#', $path ) ) {
			return pct_threads_fixture_response( array( 'id' => 'fixture-container' ) );
		}
		if ( false !== strpos( $path, '/987654321' ) ) {
			return pct_threads_fixture_response( array( 'id' => '987654321', 'permalink' => 'https://www.threads.com/@fixture/post/test' ) );
		}
		if ( false !== strpos( $path, '/fixture-container' ) ) {
			return pct_threads_fixture_response( array( 'id' => 'fixture-container', 'status' => 'PUBLISHED' ) );
		}
		return pct_threads_fixture_response( array( 'data' => array() ) );
	}

	return $preempt;
}
add_filter( 'pre_http_request', 'pct_threads_fixture_pre_http_request', 10, 3 );
