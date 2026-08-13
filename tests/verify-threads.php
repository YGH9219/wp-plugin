<?php
/**
 * Lightweight regression tests for the Threads helpers without WordPress.
 */

define( 'ABSPATH', __DIR__ );
define( 'PERSONAL_CTA_BLOCKS_FILE', dirname( __DIR__ ) . '/personal-cta-blocks.php' );
define( 'OPENAI_API_KEY', 'test-openai-key' );
define( 'DAY_IN_SECONDS', 86400 );

$test_options          = array();
$test_meta             = array();
$test_remote_response  = array();
$test_remote_responses = array();
$test_remote_requests  = array();
$test_permalink        = '';
$test_post_title       = '테스트 글';

class Pct_Test_WPDB {
	public $options = 'options';

	public function prepare( $query, ...$args ) {
		return $query;
	}

	public function query( $query ) {
		return 1;
	}
}

$wpdb = new Pct_Test_WPDB();

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function register_deactivation_hook( $file, $callback ) {}
function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	global $test_options;
	if ( array_key_exists( $key, $test_options ) ) {
		return false;
	}
	$test_options[ $key ] = $value;
	return true;
}
function update_option( $key, $value, $autoload = null ) {
	global $test_options;
	$test_options[ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	global $test_options;
	unset( $test_options[ $key ] );
	return true;
}
function get_post_meta( $post_id, $key, $single = false ) {
	global $test_meta;
	return isset( $test_meta[ $post_id ][ $key ] ) ? $test_meta[ $post_id ][ $key ] : '';
}
function update_post_meta( $post_id, $key, $value ) {
	global $test_meta;
	$test_meta[ $post_id ][ $key ] = $value;
	return true;
}
function delete_post_meta( $post_id, $key ) {
	global $test_meta;
	unset( $test_meta[ $post_id ][ $key ] );
	return true;
}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value, $remove_breaks = false ) { return strip_tags( (string) $value ); }
function wp_json_encode( $value, $options = 0, $depth = 512 ) { return json_encode( $value, $options, $depth ); }
function esc_url_raw( $value ) { return (string) $value; }
function get_permalink( $post_id ) {
	global $test_permalink;
	return '' !== $test_permalink ? $test_permalink : 'https://example.test/sample-post/';
}
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_post( $post_id ) {
	return (object) array(
		'ID'           => (int) $post_id,
		'post_type'    => 'post',
		'post_content' => '<!-- wp:paragraph --><p>첫 문단입니다.</p><!-- /wp:paragraph --><!-- wp:shortcode -->[secret]<!-- /wp:shortcode -->',
	);
}
function get_the_title( $post_id ) {
	global $test_post_title;
	return $test_post_title;
}
function parse_blocks( $content ) {
	return array(
		array( 'blockName' => 'core/paragraph', 'innerHTML' => '<p>첫 문단입니다.</p>', 'innerBlocks' => array() ),
		array( 'blockName' => 'core/shortcode', 'innerHTML' => '[secret]', 'innerBlocks' => array() ),
	);
}
function current_user_can( $capability, $post_id = 0 ) { return true; }
function wp_next_scheduled( $hook, $args = array() ) { return false; }
function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) { return true; }
function wp_schedule_event( $timestamp, $recurrence, $hook ) { return true; }
function wp_unschedule_hook( $hook ) { return true; }
function get_posts( $args ) { return array(); }
function wp_cache_delete( $key, $group = '' ) { return true; }
function maybe_serialize( $value ) { return is_scalar( $value ) ? (string) $value : serialize( $value ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function wp_remote_post( $url, $args = array() ) {
	global $test_remote_response, $test_remote_responses, $test_remote_requests;
	$test_remote_requests[] = array( 'url' => $url, 'args' => $args );
	return ! empty( $test_remote_responses ) ? array_shift( $test_remote_responses ) : $test_remote_response;
}
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
function wp_remote_retrieve_response_code( $response ) { return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0; }

require dirname( __DIR__ ) . '/includes/threads-core.php';
require dirname( __DIR__ ) . '/includes/threads-openai.php';
require dirname( __DIR__ ) . '/includes/threads-admin.php';

function pct_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function pct_openai_response( $id, $data ) {
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'id'     => $id,
				'status' => 'completed',
				'usage'  => array( 'input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2 ),
				'output' => array(
					array(
						'type'    => 'message',
						'content' => array( array( 'type' => 'output_text', 'text' => wp_json_encode( $data ) ) ),
					),
				),
			)
		),
	);
}

function pct_copy( $hook_id, $structure_id, $text = '테스트 글은 첫 문단부터 확인해야 해.' ) {
	return array(
		'text'          => $text,
		'hook_angle_id' => $hook_id,
		'structure_id'  => $structure_id,
		'fact_ids'      => array( 'F1' ),
		'claims'        => array( array( 'text' => $text, 'fact_ids' => array( 'F1' ) ) ),
	);
}

function pct_verifier_result( $text, $pass = true ) {
	return array(
		'decision' => $pass ? 'pass' : 'block',
		'checks'   => array(
			array(
				'unit_id'      => 'T001',
				'claim'        => $text,
				'verdict'      => $pass ? 'supported' : 'unsupported',
				'fact_ids'     => $pass ? array( 'F1' ) : array(),
				'evidence_ids' => $pass ? array( 'S002' ) : array(),
				'reason'       => $pass ? 'direct source support' : 'not supported',
			),
		),
		'issues'   => $pass ? array() : array( 'unsupported claim' ),
	);
}

function pct_pipeline_responses( $post_id, $fact_map, $strategy, $editor, $quality, $verifier = null ) {
	$responses = array(
		pct_openai_response( 'resp_' . $post_id . '_fact', $fact_map ),
		pct_openai_response( 'resp_' . $post_id . '_strategy', $strategy ),
		pct_openai_response( 'resp_' . $post_id . '_writer_a', pct_copy( 'H1', 'reversal' ) ),
		pct_openai_response( 'resp_' . $post_id . '_writer_b', pct_copy( 'H2', 'mistake_prevention' ) ),
		pct_openai_response( 'resp_' . $post_id . '_writer_c', pct_copy( 'H3', 'short_discovery' ) ),
		pct_openai_response( 'resp_' . $post_id . '_editor', $editor ),
		pct_openai_response( 'resp_' . $post_id . '_quality', $quality ),
	);
	if ( null !== $verifier ) {
		$responses[] = pct_openai_response( 'resp_' . $post_id . '_verifier', $verifier );
	}

	return $responses;
}

function pct_drive_generation( $post_id, $limit = 12 ) {
	$result = null;
	for ( $i = 0; $i < $limit; $i++ ) {
		$result = personal_cta_threads_generate( $post_id, false );
		if ( is_wp_error( $result ) || ( is_array( $result ) && empty( $result['pending'] ) ) ) {
			return $result;
		}
	}

	return new WP_Error( 'pct_test_timeout', 'The mocked pipeline did not finish.' );
}

/* Low-level source, secret, parser, and queue safeguards. */
pct_assert( 3 === personal_cta_threads_character_length( '가나다' ), 'Unicode length fallback is invalid.' );
pct_assert( 5 === personal_cta_threads_length( '가😀' ), 'Threads emoji byte counting is invalid.' );
pct_assert( 'test-openai-key' === personal_cta_threads_config_secret( 'PERSONAL_CTA_OPENAI_API_KEY', 'OPENAI_API_KEY' ), 'The standard OpenAI wp-config constant must be recognized.' );
$stored_key = personal_cta_threads_save_openai_key( 'test-direct-openai-key' );
pct_assert( true === $stored_key, 'The administrator API key must be saved.' );
pct_assert( false === strpos( wp_json_encode( get_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION ) ), 'test-direct-openai-key' ), 'The administrator API key must never be stored in plaintext.' );
pct_assert( 'test-direct-openai-key' === personal_cta_threads_saved_openai_key(), 'The encrypted administrator API key must decrypt server-side.' );
pct_assert( 'test-openai-key' === personal_cta_threads_openai_key(), 'A wp-config API key must take priority over the saved administrator key.' );
$tampered_key               = get_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION );
$tampered_key['ciphertext'] = base64_encode( 'tampered' );
update_option( PERSONAL_CTA_THREADS_OPENAI_KEY_OPTION, $tampered_key );
pct_assert( is_wp_error( personal_cta_threads_saved_openai_key() ), 'A tampered administrator API key must be rejected.' );
personal_cta_threads_save_openai_key( 'test-direct-openai-key' );

$source = personal_cta_threads_source( 7 );
pct_assert( is_array( $source ) && false !== strpos( $source['text'], '[S001] 제목: 테스트 글' ) && false !== strpos( $source['text'], '[S002] 첫 문단입니다.' ), 'The source document must retain stable segment IDs.' );
pct_assert( false === strpos( $source['text'], '[secret]' ), 'Shortcodes must not enter the model source.' );
$payload = personal_cta_threads_payload_text( 7, '본문' );
pct_assert( is_array( $payload ) && false !== strpos( $payload['text'], 'utm_source=threads' ), 'The copied text must include the deterministic outbound URL.' );
$too_long = personal_cta_threads_payload_text( 7, str_repeat( 'a', 501 ) );
pct_assert( is_wp_error( $too_long ) && 'pct_text_too_long' === $too_long->get_error_code(), 'The server must reject text over 500 characters.' );
$manual_url = personal_cta_threads_payload_text( 7, '본문 https://other.example/' );
pct_assert( is_wp_error( $manual_url ) && 'pct_body_contains_url' === $manual_url->get_error_code(), 'Only the server may append a URL.' );

$parsed = personal_cta_threads_openai_parse_response(
	array(
		'id'     => 'resp_test',
		'status' => 'completed',
		'output' => array(
			array( 'type' => 'reasoning' ),
			array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => '{"ok":true}' ) ) ),
		),
	)
);
pct_assert( is_array( $parsed ) && true === $parsed['data']['ok'], 'Responses parsing must find structured output after reasoning items.' );
$usage = personal_cta_threads_openai_usage(
	array(
		'usage' => array(
			'input_tokens'          => 101,
			'output_tokens'         => 202,
			'total_tokens'          => 303,
			'input_tokens_details'  => array( 'cached_tokens' => 55 ),
			'output_tokens_details' => array( 'reasoning_tokens' => 144 ),
		),
	)
);
pct_assert( 144 === $usage['reasoning_tokens'] && 55 === $usage['cached_tokens'], 'Usage diagnostics must retain safe token counts.' );
$refusal = personal_cta_threads_openai_parse_response( array( 'status' => 'completed', 'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'refusal', 'refusal' => 'no' ) ) ) ) ) );
pct_assert( is_wp_error( $refusal ) && 'pct_openai_refusal' === $refusal->get_error_code(), 'A refusal must never be treated as copy.' );
$incomplete = personal_cta_threads_openai_parse_response( array( 'status' => 'incomplete', 'incomplete_details' => array( 'reason' => 'max_output_tokens' ) ) );
pct_assert( is_wp_error( $incomplete ) && personal_cta_threads_openai_is_output_limit_error( $incomplete ), 'Output-token exhaustion must remain recoverable.' );
$multiple = personal_cta_threads_openai_parse_response(
	array(
		'status' => 'completed',
		'output' => array(
			array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => '{"a":1}' ) ) ),
			array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => '{"b":2}' ) ) ),
		),
	)
);
pct_assert( is_wp_error( $multiple ), 'Ambiguous multiple outputs must be rejected.' );
$quota_error = personal_cta_threads_openai_parse_response( array( 'error' => array( 'code' => 'insufficient_quota' ) ), 429 );
$rate_error  = personal_cta_threads_openai_parse_response( array( 'error' => array( 'code' => 'rate_limit_exceeded' ) ), 429 );
pct_assert( 0 === personal_cta_threads_openai_retry_delay( $quota_error ) && 60 === personal_cta_threads_openai_retry_delay( $rate_error ), 'Only retryable provider failures receive backoff.' );
pct_assert( array( 'max_output_tokens' => 4096, 'reasoning_effort' => 'medium' ) === personal_cta_threads_openai_stage_options( 'quality' ), 'Final quality must use its bounded budget.' );
pct_assert( array( 'max_output_tokens' => 8192, 'reasoning_effort' => 'low' ) === personal_cta_threads_openai_stage_options( 'editor', true ), 'Editor recovery must be bounded.' );

/* v0.5 FACT, strategy, copy, and quality contracts. */
$fact_map = array(
	'topic'            => '테스트 글',
	'reader_situation' => '첫 문단을 확인하려는 독자',
	'context_fact_ids' => array( 'F1' ),
	'facts'            => array(
		array(
			'id'            => 'F1',
			'subject'       => '첫 문단',
			'statement'     => '첫 문단입니다.',
			'evidence'      => array( array( 'source_id' => 'S002', 'quote' => '첫 문단입니다.' ) ),
			'must_preserve' => array( '첫 문단' ),
		),
	),
	'blockers'         => array(),
);
$fact_schema = personal_cta_threads_fact_schema();
pct_assert( array( 'topic', 'reader_situation', 'context_fact_ids', 'facts', 'blockers' ) === $fact_schema['required'], 'FACT schema must contain only atomic extraction fields.' );
pct_assert( ! isset( $fact_schema['properties']['reader_problem'], $fact_schema['properties']['hook_angles'] ) && 12 === $fact_schema['properties']['facts']['maxItems'] && 1 === $fact_schema['properties']['facts']['items']['properties']['evidence']['maxItems'], 'FACT schema must not mix strategy with extraction.' );
pct_assert( '^F([1-9]|1[0-2])$' === $fact_schema['properties']['facts']['items']['properties']['id']['pattern'] && '\\S' === $fact_schema['properties']['facts']['items']['properties']['subject']['pattern'], 'FACT schema must prevent blank facts and constrain generated IDs.' );
pct_assert( true === personal_cta_threads_validate_fact_map( $fact_map, $source['text'] ), 'A grounded atomic FACT MAP must validate.' );
$literal_source = "[S001] 제목: 신청 기준\n\n[S002] 신청 수량은 5대 이상입니다.\n\n[S003] 접수 기간은 3일입니다.";
$literal_fact_map = array(
	'topic'            => '신청 기준',
	'reader_situation' => '신청 수량을 확인하는 독자',
	'context_fact_ids' => array( 'F1' ),
	'facts'            => array(
		array(
			'id'            => 'F1',
			'subject'       => '신청 수량',
			'statement'     => '신청 수량은 5대 이상입니다.',
			'evidence'      => array( array( 'source_id' => 'S002', 'quote' => '신청 수량은 5대 이상입니다.' ) ),
			'must_preserve' => array( '5대 이상' ),
		),
	),
	'blockers'         => array(),
);
pct_assert( true === personal_cta_threads_validate_fact_map( $literal_fact_map, $literal_source ), 'A literal number-and-condition span must validate.' );
$whitespace_literal_fact_map = $literal_fact_map;
$whitespace_literal_fact_map['facts'][0]['must_preserve'] = array( '5대   이상' );
pct_assert( true === personal_cta_threads_validate_fact_map( $whitespace_literal_fact_map, $literal_source ), 'Whitespace-only differences in a literal span must normalize safely.' );
$paraphrased_literal_fact_map = $literal_fact_map;
$paraphrased_literal_fact_map['facts'][0]['must_preserve'] = array( '5대 이하' );
$paraphrased_literal_error = personal_cta_threads_validate_fact_map( $paraphrased_literal_fact_map, $literal_source );
pct_assert( is_wp_error( $paraphrased_literal_error ) && 'pct_fact_preserve_not_grounded' === $paraphrased_literal_error->get_error_code(), 'A changed condition must not pass literal preservation.' );
$other_segment_literal_fact_map = $literal_fact_map;
$other_segment_literal_fact_map['facts'][0]['must_preserve'] = array( '3일' );
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $other_segment_literal_fact_map, $literal_source ) ), 'A token found only in another source segment must be rejected.' );
$noncanonical_fact_map                    = $fact_map;
$noncanonical_fact_map['facts'][0]['id'] = 'primary-fact';
$noncanonical_fact_map['facts'][]        = array(
	'id'            => 'secondary-fact',
	'subject'       => '첫 문단',
	'statement'     => '첫 문단입니다.',
	'evidence'      => array( array( 'source_id' => 'S002', 'quote' => '첫 문단입니다.' ) ),
	'must_preserve' => array(),
);
$noncanonical_fact_map['context_fact_ids'] = array( 'secondary-fact', 'primary-fact' );
$normalized_fact_map = personal_cta_threads_normalize_fact_ids( $noncanonical_fact_map );
pct_assert( is_array( $normalized_fact_map ) && array( 'F1', 'F2' ) === array_column( $normalized_fact_map['facts'], 'id' ) && array( 'F2', 'F1' ) === $normalized_fact_map['context_fact_ids'] && true === personal_cta_threads_validate_fact_map( $normalized_fact_map, $source['text'] ), 'Noncanonical model FACT IDs must be normalized by order with context references remapped.' );
$duplicate_fact_map = $noncanonical_fact_map;
$duplicate_fact_map['facts'][1]['id'] = 'primary-fact';
pct_assert( is_wp_error( personal_cta_threads_normalize_fact_ids( $duplicate_fact_map ) ), 'Duplicate model FACT IDs must be rejected.' );
$unknown_context_fact_map = $noncanonical_fact_map;
$unknown_context_fact_map['context_fact_ids'] = array( 'missing-fact' );
pct_assert( is_wp_error( personal_cta_threads_normalize_fact_ids( $unknown_context_fact_map ) ), 'Unknown model FACT context references must be rejected.' );
$bad_fact_map = $fact_map;
$bad_fact_map['context_fact_ids'] = array( 'F999' );
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $bad_fact_map, $source['text'] ) ), 'FACT context may cite only known facts.' );
$bad_fact_map = $fact_map;
$bad_fact_map['facts'][0]['evidence'][0]['quote'] = '원문에 없는 문장';
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $bad_fact_map, $source['text'] ) ), 'FACT evidence must be literal source text.' );

$note = array( 'text' => '첫 문단이 핵심이다.', 'fact_ids' => array( 'F1' ) );
$strategy = array(
	'core_tension'      => $note,
	'reader_assumption' => $note,
	'contrast'          => $note,
	'best_reveal'       => $note,
	'secondary_value'   => $note,
	'boring_fact_ids'   => array(),
	'hooks'             => array(),
	'writer_plans'      => array(
		array( 'writer_id' => 'A', 'structure_id' => 'reversal', 'hook_id' => 'H1' ),
		array( 'writer_id' => 'B', 'structure_id' => 'mistake_prevention', 'hook_id' => 'H2' ),
		array( 'writer_id' => 'C', 'structure_id' => 'short_discovery', 'hook_id' => 'H3' ),
	),
);
for ( $i = 1; $i <= 6; $i++ ) {
	$strategy['hooks'][] = array( 'id' => 'H' . $i, 'text' => 'Hook ' . $i, 'fact_ids' => array( 'F1' ) );
}
$strategy_schema = personal_cta_threads_strategy_schema();
pct_assert( 6 === $strategy_schema['properties']['hooks']['minItems'] && 6 === $strategy_schema['properties']['hooks']['maxItems'] && 3 === $strategy_schema['properties']['writer_plans']['minItems'], 'Strategy must produce six hooks and three plans.' );
pct_assert( true === personal_cta_threads_validate_strategy( $strategy, $fact_map ), 'A/B/C plans with distinct hooks and structures must validate.' );
$duplicate_plan = $strategy;
$duplicate_plan['writer_plans'][1]['structure_id'] = 'reversal';
pct_assert( is_wp_error( personal_cta_threads_validate_strategy( $duplicate_plan, $fact_map ) ), 'Writer plans must use distinct structures.' );
$unknown_hook_fact = $strategy;
$unknown_hook_fact['hooks'][0]['fact_ids'] = array( 'F999' );
pct_assert( is_wp_error( personal_cta_threads_validate_strategy( $unknown_hook_fact, $fact_map ) ), 'Hooks may cite only known facts.' );
$duplicate_hook_text = $strategy;
$duplicate_hook_text['hooks'][1]['text'] = $duplicate_hook_text['hooks'][0]['text'];
pct_assert( is_wp_error( personal_cta_threads_validate_strategy( $duplicate_hook_text, $fact_map ) ), 'Hook Lab must return six meaningfully distinct hook texts.' );

$copy = pct_copy( 'H1', 'reversal' );
$copy_schema = personal_cta_threads_copy_schema();
pct_assert( in_array( 'structure_id', $copy_schema['required'], true ), 'Every copy checkpoint must declare its structure.' );
pct_assert( true === personal_cta_threads_validate_copy( $copy, $fact_map, $strategy, 'H1', 'reversal', true ), 'Copy metadata must validate against strategy and FACT.' );
$missing_structure = $copy;
unset( $missing_structure['structure_id'] );
pct_assert( is_wp_error( personal_cta_threads_validate_copy( $missing_structure, $fact_map, $strategy ) ), 'A copy without structure_id must be rejected.' );
pct_assert( is_wp_error( personal_cta_threads_validate_copy( $copy, $fact_map, $strategy, 'H1', 'question_answer' ) ), 'A writer may not change its assigned structure.' );

$quality_schema = personal_cta_threads_quality_schema();
pct_assert( array( 'decision', 'issues', 'copy' ) === $quality_schema['required'] && in_array( 'generic_meta_cta', $quality_schema['properties']['issues']['items']['enum'], true ) && in_array( 'grounding_strengthened', $quality_schema['properties']['issues']['items']['enum'], true ), 'Final quality must return a bounded decision and catch wording stronger than its evidence.' );
$pass_quality = array( 'decision' => 'pass', 'issues' => array(), 'copy' => $copy );
pct_assert( true === personal_cta_threads_validate_quality_review( $pass_quality ), 'A pass with unchanged copy and no issues must validate.' );
$rewrite_copy         = $copy;
$rewrite_copy['text'] = '첫 문단을 지금 확인해야 해.';
$rewrite_quality      = array( 'decision' => 'rewrite', 'issues' => array( 'poor_rhythm' ), 'copy' => $rewrite_copy );
pct_assert( true === personal_cta_threads_validate_quality_review( $rewrite_quality ), 'A bounded rewrite with an issue must validate.' );
$bad_quality = array( 'decision' => 'pass', 'issues' => array( 'weak_hook' ), 'copy' => $copy );
pct_assert( is_wp_error( personal_cta_threads_validate_quality_review( $bad_quality ) ), 'A pass may not retain issues.' );
$meta_copy         = $copy;
$meta_copy['text'] = '첫 문단의 자세한 내용을 원문에서 확인해.';
pct_assert( in_array( 'generic_meta_cta', personal_cta_threads_local_quality_issues( $meta_copy ), true ), 'Generic source/link CTA text must be rejected locally.' );
$meta_variants = array( '원문과 대조해봐.', '본문을 살펴봐.', '자세한 내용은 읽어봐.', '아래 글을 참고해.', '아래에서 확인해.' );
foreach ( $meta_variants as $meta_variant ) {
	$meta_copy['text'] = $meta_variant;
	pct_assert( in_array( 'generic_meta_cta', personal_cta_threads_local_quality_issues( $meta_copy ), true ), 'Source-confirmation CTA variants must be rejected locally.' );
}
$allowed_actions = array( '링크를 열기 전에 주소를 확인해.', '아래 조건을 확인해.', '여기서 먼저 봐야 할 건 예외 조건이야.' );
foreach ( $allowed_actions as $allowed_action ) {
	pct_assert( ! in_array( 'generic_meta_cta', personal_cta_threads_local_quality_issues( array( 'text' => $allowed_action ) ), true ), 'Normal link or condition advice must not be mistaken for a meta CTA.' );
}
$quality_cases = require __DIR__ . '/fixtures/threads-quality-cases.php';
foreach ( array_slice( $quality_cases, 0, 2 ) as $quality_case ) {
	$fixture_copy         = $copy;
	$fixture_copy['text'] = $quality_case['bad'];
	$fixture_issues       = personal_cta_threads_local_quality_issues( $fixture_copy );
	pct_assert( in_array( 'generic_meta_cta', $fixture_issues, true ), $quality_case['id'] . ' must reject the measured source-confirmation CTA.' );
}
pct_assert( in_array( 'emoji_lead', personal_cta_threads_local_quality_issues( array( 'text' => $quality_cases[0]['bad'] ) ), true ), 'The measured TV copy must reject a decorative emoji opener.' );
pct_assert( in_array( 'weak_hook', personal_cta_threads_local_quality_issues( array( 'text' => $quality_cases[1]['bad'] ) ), true ), 'The measured diagnostic-letter opener must retain the weak-hook regression.' );

$units = personal_cta_threads_candidate_units( $copy['text'] );
$verifier_pass = pct_verifier_result( $copy['text'], true );
pct_assert( true === personal_cta_threads_validate_verifier( $verifier_pass, $fact_map, $source['text'], $units, array( 'F1' ) ), 'Every delivered fact must receive a supported verifier result.' );
$verifier_block = pct_verifier_result( $copy['text'], false );
$verifier_block_error = personal_cta_threads_validate_verifier( $verifier_block, $fact_map, $source['text'], $units, array( 'F1' ) );
pct_assert( is_wp_error( $verifier_block_error ) && 'pct_verifier_blocked' === $verifier_block_error->get_error_code() && 'T001' === $verifier_block_error->get_error_data()['unit_id'], 'An unsupported verifier result must block delivery with its exact unit.' );
$missing_fact_refs = $verifier_pass;
$missing_fact_refs['checks'][0]['fact_ids'] = array();
pct_assert( 'pct_invalid_verifier' === personal_cta_threads_validate_verifier( $missing_fact_refs, $fact_map, $source['text'], $units, array( 'F1' ) )->get_error_code(), 'A supported verdict without FACT IDs is malformed verifier output, not an unsupported claim.' );
$missing_source_refs = $verifier_pass;
$missing_source_refs['checks'][0]['evidence_ids'] = array();
pct_assert( 'pct_invalid_verifier' === personal_cta_threads_validate_verifier( $missing_source_refs, $fact_map, $source['text'], $units, array( 'F1' ) )->get_error_code(), 'A supported verdict without source IDs is malformed verifier output, not an unsupported claim.' );
$inconsistent_verifier             = $verifier_pass;
$inconsistent_verifier['decision'] = 'block';
$inconsistent_verifier['issues']   = array( 'contradictory summary' );
pct_assert( 'pct_invalid_verifier' === personal_cta_threads_validate_verifier( $inconsistent_verifier, $fact_map, $source['text'], $units, array( 'F1' ) )->get_error_code(), 'A block summary with only supported units is malformed verifier output.' );
$sentence_units = personal_cta_threads_candidate_units( "첫 문단입니다. 더 확인해봐 👇" );
$sentence_verifier = array(
	'decision' => 'pass',
	'checks'   => array(
		array( 'unit_id' => 'T001', 'claim' => '첫 문단입니다.', 'verdict' => 'supported', 'fact_ids' => array( 'F1' ), 'evidence_ids' => array( 'S002' ), 'reason' => 'direct source support' ),
		array( 'unit_id' => 'T002', 'claim' => '더 확인해봐 👇', 'verdict' => 'non_factual', 'fact_ids' => array(), 'evidence_ids' => array(), 'reason' => 'navigation CTA only' ),
	),
	'issues'   => array(),
);
pct_assert( 2 === count( $sentence_units ) && 'T002' === $sentence_units[1]['id'] && true === personal_cta_threads_validate_verifier( $sentence_verifier, $fact_map, $source['text'], $sentence_units, array( 'F1' ) ), 'Verifier units must split sentences and allow a separate non-factual CTA.' );

/* Normal v0.5 generation: FACT, strategy, three writers, editor, quality, verifier. */
$normal_post_id        = 20;
$normal_editor         = pct_copy( 'H1', 'reversal' );
$normal_quality        = array( 'decision' => 'pass', 'issues' => array(), 'copy' => $normal_editor );
$test_remote_response  = new WP_Error( 'unexpected_request', 'The mock response queue was exhausted.' );
$test_remote_responses = pct_pipeline_responses( $normal_post_id, $fact_map, $strategy, $normal_editor, $normal_quality, pct_verifier_result( $normal_editor['text'], true ) );
$request_start         = count( $test_remote_requests );
$normal_result         = pct_drive_generation( $normal_post_id );
$normal_requests       = array_slice( $test_remote_requests, $request_start );
pct_assert( is_array( $normal_result ) && empty( $normal_result['pending'] ) && 'ready' === personal_cta_threads_meta( $normal_post_id, 'status' ), 'A supported generation must reach ready.' );
pct_assert( 8 === count( $normal_requests ) && 8 === (int) personal_cta_threads_meta( $normal_post_id, 'call_count' ) && empty( $test_remote_responses ), 'A normal generation must use exactly eight requests.' );
$request_stages = array();
foreach ( $normal_requests as $request ) {
	$request_payload  = json_decode( $request['args']['body'], true );
	$request_stages[] = $request_payload['text']['format']['name'];
	if ( in_array( $request_payload['text']['format']['name'], array( 'threads_writer', 'threads_editor', 'threads_quality' ), true ) ) {
		pct_assert( false === strpos( $request_payload['input'][1]['content'][0]['text'], 'source_document' ), 'Writers, editor, and quality may not receive the raw source.' );
	}
}
pct_assert( array( 'threads_fact', 'threads_strategy', 'threads_writer', 'threads_writer', 'threads_writer', 'threads_editor', 'threads_quality', 'threads_verifier' ) === $request_stages, 'The v0.5 request order is invalid.' );

/* One invalid literal-preservation result receives one FACT-only recovery. */
$fact_retry_post_id = 25;
$bad_preserve_map = $fact_map;
$bad_preserve_map['facts'][0]['must_preserve'] = array( '첫 문장이 아님' );
$test_remote_responses = array_merge(
	array( pct_openai_response( 'resp_' . $fact_retry_post_id . '_fact_invalid', $bad_preserve_map ) ),
	pct_pipeline_responses( $fact_retry_post_id, $fact_map, $strategy, $normal_editor, $normal_quality, pct_verifier_result( $normal_editor['text'], true ) )
);
$fact_retry_start    = count( $test_remote_requests );
$fact_retry_result   = pct_drive_generation( $fact_retry_post_id, 14 );
$fact_retry_requests = array_slice( $test_remote_requests, $fact_retry_start );
$fact_retry_stages   = array_map(
	function ( $request ) {
		$payload = json_decode( $request['args']['body'], true );
		return $payload['text']['format']['name'];
	},
	$fact_retry_requests
);
pct_assert( is_array( $fact_retry_result ) && empty( $fact_retry_result['pending'] ) && 9 === count( $fact_retry_requests ) && array( 'threads_fact', 'threads_fact' ) === array_slice( $fact_retry_stages, 0, 2 ), 'A nonliteral preserve token must retry only the FACT checkpoint once.' );
pct_assert( 9 === (int) personal_cta_threads_meta( $fact_retry_post_id, 'call_count' ) && '' === personal_cta_threads_meta( $fact_retry_post_id, 'fact_validation_retry' ), 'A successful FACT recovery must clear its retry marker.' );

$fact_retry_fail_post_id = 26;
$test_remote_responses = array(
	pct_openai_response( 'resp_' . $fact_retry_fail_post_id . '_fact_invalid_1', $bad_preserve_map ),
	pct_openai_response( 'resp_' . $fact_retry_fail_post_id . '_fact_invalid_2', $bad_preserve_map ),
);
$fact_retry_fail_start  = count( $test_remote_requests );
$fact_retry_fail_result = pct_drive_generation( $fact_retry_fail_post_id, 4 );
pct_assert( is_wp_error( $fact_retry_fail_result ) && 'pct_fact_preserve_not_grounded' === $fact_retry_fail_result->get_error_code() && 2 === count( $test_remote_requests ) - $fact_retry_fail_start, 'A second invalid FACT preserve result must fail without a third provider call.' );

$diagnostics = personal_cta_threads_admin_diagnostics( $normal_post_id );
$diagnostic_json = wp_json_encode( $diagnostics );
$diagnostic_check = $diagnostics['verifier']['checks'][0];
pct_assert( 3 === count( $diagnostics['drafts'] ) && 'A' === $diagnostics['drafts'][0]['id'] && 'pass' === $diagnostics['final_quality']['decision'] && 'pass' === $diagnostics['verifier']['decision'] && 'T001' === $diagnostic_check['unit_id'] && 'direct source support' === $diagnostic_check['reason'] && array( 'F1' ) === $diagnostic_check['fact_ids'] && array( 'S002' ) === $diagnostic_check['source_ids'], 'Diagnostics must expose the v0.5 checkpoints and safe sentence-level verifier reasons.' );
pct_assert( false === strpos( $diagnostic_json, 'evidence' ) && false === strpos( $diagnostic_json, 'must_preserve' ) && false === strpos( $diagnostic_json, 'response_id' ), 'Diagnostics must hide source evidence and provider metadata.' );
$reuse_start = count( $test_remote_requests );
$reused      = personal_cta_threads_generate( $normal_post_id, false );
pct_assert( is_array( $reused ) && ! empty( $reused['reused'] ) && $reuse_start === count( $test_remote_requests ), 'A passed verifier result must be reused without another provider call.' );

/* A reusable copy must not bypass recovery from an invalid saved FACT MAP. */
$invalid_cache_post_id  = 24;
$invalid_cache_source   = personal_cta_threads_source( $invalid_cache_post_id );
$invalid_cache_text     = $normal_editor['text'];
$invalid_cache_settings = personal_cta_threads_settings();
$invalid_cache_delivery = ! empty( $invalid_cache_settings['include_link'] ) ? personal_cta_threads_outbound_url( $invalid_cache_post_id ) : '';
$invalid_cache_run_key  = hash( 'sha256', $invalid_cache_source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . wp_json_encode( personal_cta_threads_prompt_versions() ) . '|' . hash( 'sha256', personal_cta_threads_style_examples_text() ) . '|' . $invalid_cache_delivery );
$invalid_cache_fact_key = hash( 'sha256', $invalid_cache_source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . PERSONAL_CTA_THREADS_FACT_PROMPT_VERSION . '|' . PERSONAL_CTA_THREADS_SCHEMA_VERSION );
$legacy_fact_map = $fact_map;
$legacy_fact_map['reader_problem']    = $legacy_fact_map['reader_situation'];
$legacy_fact_map['facts'][0]['claim'] = $legacy_fact_map['facts'][0]['statement'];
unset( $legacy_fact_map['reader_situation'], $legacy_fact_map['facts'][0]['subject'], $legacy_fact_map['facts'][0]['statement'] );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'generation_key', $invalid_cache_run_key );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'fact_cache_key', $invalid_cache_fact_key );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'source_hash', $invalid_cache_source['hash'] );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'text_hash', hash( 'sha256', $invalid_cache_text ) );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'final_text', $invalid_cache_text );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'final_copy', $normal_editor );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'fact_map', $legacy_fact_map );
personal_cta_threads_set_meta( $invalid_cache_post_id, 'verifier_state', 'failed' );
personal_cta_threads_set_state( $invalid_cache_post_id, 'failed', 'verifier' );
$test_remote_responses = array( pct_openai_response( 'resp_' . $invalid_cache_post_id . '_fact', $fact_map ) );
$invalid_cache_start   = count( $test_remote_requests );
$invalid_cache_result  = personal_cta_threads_generate( $invalid_cache_post_id, false );
$invalid_cache_requests = array_slice( $test_remote_requests, $invalid_cache_start );
$invalid_cache_payload = isset( $invalid_cache_requests[0]['args']['body'] ) ? json_decode( $invalid_cache_requests[0]['args']['body'], true ) : array();
pct_assert( is_array( $invalid_cache_result ) && ! empty( $invalid_cache_result['pending'] ) && 1 === count( $invalid_cache_requests ) && 'threads_fact' === $invalid_cache_payload['text']['format']['name'], 'An invalid saved FACT MAP must restart FACT extraction instead of failing again at the verifier.' );

$test_post_title = '변경된 테스트 글';
$stale_state     = personal_cta_threads_admin_state( $normal_post_id );
pct_assert( 'failed' === $stale_state['status'] && '' === $stale_state['copy_text'] && 'not_run' === personal_cta_threads_meta( $normal_post_id, 'verifier_state' ), 'A source edit must immediately hide an older ready copy.' );
$test_post_title = '테스트 글';

/* A pass decision may not silently mutate the editor candidate. */
$mutation_post_id   = 21;
$mutation_editor    = pct_copy( 'H1', 'reversal' );
$mutated_copy       = pct_copy( 'H1', 'reversal', '첫 문단을 지금 확인해야 해.' );
$mutation_quality   = array( 'decision' => 'pass', 'issues' => array(), 'copy' => $mutated_copy );
$test_remote_responses = pct_pipeline_responses( $mutation_post_id, $fact_map, $strategy, $mutation_editor, $mutation_quality );
$mutation_start     = count( $test_remote_requests );
$mutation_result    = pct_drive_generation( $mutation_post_id );
pct_assert( is_wp_error( $mutation_result ) && 'pct_invalid_quality_review' === $mutation_result->get_error_code() && 7 === count( $test_remote_requests ) - $mutation_start, 'A pass that changes copy must fail at final quality.' );

/* The final verifier is automatic and can block an otherwise valid copy. */
$blocked_post_id       = 22;
$blocked_editor        = pct_copy( 'H1', 'reversal' );
$blocked_quality       = array( 'decision' => 'pass', 'issues' => array(), 'copy' => $blocked_editor );
$test_remote_responses = pct_pipeline_responses( $blocked_post_id, $fact_map, $strategy, $blocked_editor, $blocked_quality, pct_verifier_result( $blocked_editor['text'], false ) );
$blocked_start         = count( $test_remote_requests );
$blocked_result        = pct_drive_generation( $blocked_post_id );
pct_assert( is_wp_error( $blocked_result ) && 'pct_verifier_blocked' === $blocked_result->get_error_code() && 'blocked' === personal_cta_threads_meta( $blocked_post_id, 'verifier_state' ), 'An unsupported final verifier result must block readiness.' );
pct_assert( 'ready' !== personal_cta_threads_meta( $blocked_post_id, 'status' ) && 8 === count( $test_remote_requests ) - $blocked_start, 'A blocked verifier must not expose a ready generation.' );
$blocked_diagnostics = personal_cta_threads_admin_diagnostics( $blocked_post_id );
pct_assert( 'blocked' === $blocked_diagnostics['verifier']['state'] && 'unsupported' === $blocked_diagnostics['verifier']['checks'][0]['verdict'] && 'not supported' === $blocked_diagnostics['verifier']['checks'][0]['reason'], 'Blocked diagnostics must retain the exact safe verifier verdict and reason.' );

/* Repairs and retries cannot exceed the per-generation provider-call ceiling. */
$cap_post_id = 23;
personal_cta_threads_set_meta( $cap_post_id, 'call_count', PERSONAL_CTA_THREADS_CALL_LIMIT );
$cap_start = count( $test_remote_requests );
$cap_error = personal_cta_threads_pipeline_request(
	$cap_post_id,
	'fact',
	'test',
	array(),
	array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'ok' ),
		'properties'           => array( 'ok' => array( 'type' => 'boolean' ) ),
	)
);
pct_assert( is_wp_error( $cap_error ) && 'pct_call_limit' === $cap_error->get_error_code() && $cap_start === count( $test_remote_requests ), 'The call cap must stop before another provider request.' );

/* One final verifier call remains available after one bounded recovery/repair combination. */
$reserve_post_id = 29;
$reserve_schema  = array(
	'type'                 => 'object',
	'additionalProperties' => false,
	'required'             => array( 'ok' ),
	'properties'           => array( 'ok' => array( 'type' => 'boolean' ) ),
);
personal_cta_threads_set_meta( $reserve_post_id, 'call_count', PERSONAL_CTA_THREADS_CALL_LIMIT - 2 );
$test_remote_responses = array(
	pct_openai_response( 'resp_reserved_repair', array( 'ok' => true ) ),
	pct_openai_response( 'resp_reserved_verifier', array( 'ok' => true ) ),
);
$reserve_start  = count( $test_remote_requests );
$reserve_repair = personal_cta_threads_pipeline_request( $reserve_post_id, 'repair', 'test', array(), $reserve_schema );
$reserve_verify = personal_cta_threads_pipeline_request( $reserve_post_id, 'verifier', 'test', array(), $reserve_schema );
$reserve_block  = personal_cta_threads_pipeline_request( $reserve_post_id, 'repair', 'test', array(), $reserve_schema );
pct_assert( is_array( $reserve_repair ) && is_array( $reserve_verify ) && 2 === count( $test_remote_requests ) - $reserve_start, 'A bounded repair followed by the reserved verifier must both reach the provider.' );
pct_assert( PERSONAL_CTA_THREADS_CALL_LIMIT === (int) personal_cta_threads_meta( $reserve_post_id, 'call_count' ) && is_wp_error( $reserve_block ) && 'pct_call_limit' === $reserve_block->get_error_code(), 'The reserved verifier may use the final slot, but no later provider call may run.' );

$queued = personal_cta_threads_queue( 30, false );
pct_assert( true === $queued && 'queued' === personal_cta_threads_meta( 30, 'status' ), 'A generation request must queue without publishing.' );
pct_assert( (int) personal_cta_threads_meta( 30, 'last_heartbeat', 0 ) > 0 && (int) personal_cta_threads_meta( 30, 'lease_until', 0 ) > time(), 'A queued job must remain recoverable.' );
personal_cta_threads_set_state( 30, 'drafting', 'writer_b_complete' );
delete_post_meta( 30, '_pct_threads_lease_until' );
pct_assert( true === personal_cta_threads_resume( 30 ) && 'writer_b_complete' === personal_cta_threads_meta( 30, 'stage' ), 'Resume must preserve the checkpoint.' );

personal_cta_threads_set_meta( 31, 'generation_id', 'retry-fixture' );
personal_cta_threads_set_state( 31, 'editing', 'editor' );
$first_retry  = personal_cta_threads_retry_transient_error( 31, new WP_Error( 'pct_openai_network', 'test' ) );
$second_retry = personal_cta_threads_retry_transient_error( 31, new WP_Error( 'pct_openai_network', 'test' ) );
pct_assert( true === $first_retry && false === $second_retry && 'retry_wait' === personal_cta_threads_meta( 31, 'stage' ), 'A generation may spend only one automatic transport retry.' );

echo "Threads v0.5 copy-generation safeguards are valid.\n";
