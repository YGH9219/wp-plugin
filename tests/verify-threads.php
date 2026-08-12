<?php
/**
 * Lightweight regression tests for the Threads helpers without WordPress.
 */

define( 'ABSPATH', __DIR__ );
define( 'PERSONAL_CTA_BLOCKS_FILE', dirname( __DIR__ ) . '/personal-cta-blocks.php' );
define( 'OPENAI_API_KEY', 'test-openai-key' );
define( 'DAY_IN_SECONDS', 86400 );

$test_options = array();
$test_meta    = array();
$test_remote_response = array();
$test_remote_requests = array();
$test_permalink       = '';

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
function get_the_title( $post_id ) { return '테스트 글'; }
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
	global $test_remote_response, $test_remote_requests;
	$test_remote_requests[] = array( 'url' => $url, 'args' => $args );
	return $test_remote_response;
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
pct_assert( "첫째\n둘째" === personal_cta_threads_clean_text( '<p>첫째</p><p>둘째</p>' ), 'HTML normalization is invalid.' );

$source = personal_cta_threads_source( 7 );
pct_assert( is_array( $source ), 'A valid post must produce a source document.' );
pct_assert( false !== strpos( $source['text'], '[S001] 제목: 테스트 글' ), 'The source title is missing.' );
pct_assert( false !== strpos( $source['text'], '첫 문단입니다.' ), 'The source paragraph is missing.' );
pct_assert( false === strpos( $source['text'], '[secret]' ), 'Shortcodes must not enter the model source.' );

$payload = personal_cta_threads_payload_text( 7, '본문' );
pct_assert( is_array( $payload ), 'A short Threads payload must be valid.' );
pct_assert( false !== strpos( $payload['text'], 'https://example.test/sample-post/?utm_source=threads' ), 'The copied text must include the deterministic outbound URL.' );

$too_long = personal_cta_threads_payload_text( 7, str_repeat( 'a', 501 ) );
pct_assert( is_wp_error( $too_long ) && 'pct_text_too_long' === $too_long->get_error_code(), 'The server must reject text over 500 characters.' );

$manual_url = personal_cta_threads_payload_text( 7, '본문 https://other.example/' );
pct_assert( is_wp_error( $manual_url ) && 'pct_body_contains_url' === $manual_url->get_error_code(), 'Only the server may append a URL.' );

$parsed = personal_cta_threads_openai_parse_response(
	array(
		'id'     => 'resp_test',
		'source' => 'fixture',
		'status' => 'completed',
		'output' => array(
			array( 'type' => 'reasoning' ),
			array(
				'type'    => 'message',
				'content' => array( array( 'type' => 'output_text', 'text' => '{"ok":true}' ) ),
			),
		),
	)
);
pct_assert( is_array( $parsed ) && true === $parsed['data']['ok'], 'Responses parsing must find a structured message after reasoning items.' );
$usage_fixture = personal_cta_threads_openai_usage(
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
pct_assert( 144 === $usage_fixture['reasoning_tokens'] && 55 === $usage_fixture['cached_tokens'], 'Usage diagnostics must retain only safe reasoning and cache token counts.' );

$refusal = personal_cta_threads_openai_parse_response(
	array(
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'refusal', 'refusal' => 'no' ) ) ) ),
	)
);
pct_assert( is_wp_error( $refusal ) && 'pct_openai_refusal' === $refusal->get_error_code(), 'A model refusal must never be treated as copy.' );

$incomplete = personal_cta_threads_openai_parse_response(
	array(
		'status'             => 'incomplete',
		'incomplete_details' => array( 'reason' => 'max_output_tokens' ),
	)
);
pct_assert( is_wp_error( $incomplete ) && 'pct_openai_incomplete' === $incomplete->get_error_code() && false !== strpos( $incomplete->get_error_message(), '출력 한도' ) && true === personal_cta_threads_openai_is_output_limit_error( $incomplete ), 'Output-token exhaustion must surface as a recoverable OpenAI error.' );
$legacy_output_limit = personal_cta_threads_openai_parse_response(
	array(
		'status'             => 'incomplete',
		'incomplete_details' => array( 'reason' => 'max_tokens' ),
	)
);
pct_assert( is_wp_error( $legacy_output_limit ) && true === personal_cta_threads_openai_is_output_limit_error( $legacy_output_limit ), 'Both documented output-limit reason names must use the bounded editor recovery.' );
$html_gateway_error = personal_cta_threads_openai_parse_response( '<html>gateway error</html>', 502 );
pct_assert( is_wp_error( $html_gateway_error ) && 'pct_openai_http_502' === $html_gateway_error->get_error_code() && 502 === (int) $html_gateway_error->get_error_data()['http_status'], 'A non-JSON gateway failure must retain its HTTP status.' );
$quota_error = personal_cta_threads_openai_parse_response( array( 'error' => array( 'code' => 'insufficient_quota' ) ), 429 );
pct_assert( 0 === personal_cta_threads_openai_retry_delay( $quota_error ), 'Quota errors must not spend an automatic retry.' );
$rate_limit_error = personal_cta_threads_openai_parse_response( array( 'error' => array( 'code' => 'rate_limit_exceeded' ) ), 429 );
pct_assert( 60 === personal_cta_threads_openai_retry_delay( $rate_limit_error ) && 30 === personal_cta_threads_openai_retry_delay( new WP_Error( 'pct_openai_network', 'test' ) ), 'Only rate limits and transport failures receive a bounded backoff.' );
$content_filter_error = personal_cta_threads_openai_parse_response( array( 'status' => 'incomplete', 'incomplete_details' => array( 'reason' => 'content_filter' ) ) );
$auth_error = personal_cta_threads_openai_parse_response( array( 'error' => array( 'code' => 'invalid_api_key' ) ), 401 );
pct_assert( 0 === personal_cta_threads_openai_retry_delay( $content_filter_error ) && 0 === personal_cta_threads_openai_retry_delay( $auth_error ), 'Safety, credential, and invalid-request errors must remain final.' );
pct_assert( array( 'max_output_tokens' => 6144, 'reasoning_effort' => 'medium' ) === personal_cta_threads_openai_stage_options( 'editor' ), 'The normal editor must have a dedicated medium-effort output budget.' );
pct_assert( array( 'max_output_tokens' => 8192, 'reasoning_effort' => 'low' ) === personal_cta_threads_openai_stage_options( 'editor', true ), 'The bounded editor recovery must trade lower reasoning for a larger budget.' );

$failed_response = personal_cta_threads_openai_parse_response(
	array(
		'status' => 'failed',
		'error'  => array( 'code' => 'server_error' ),
	)
);
pct_assert( is_wp_error( $failed_response ) && 'pct_openai_failed' === $failed_response->get_error_code() && false !== strpos( $failed_response->get_error_message(), '상태: failed / server_error' ), 'A non-completed response must expose its status and remote error code.' );

$quality_schema = personal_cta_threads_quality_schema();
pct_assert( false === $quality_schema['additionalProperties'] && array( 'decision', 'issues' ) === $quality_schema['required'] && array( 'pass', 'rewrite' ) === $quality_schema['properties']['decision']['enum'] && 6 === $quality_schema['properties']['issues']['maxItems'], 'The quality review schema must remain bounded and machine-readable.' );
pct_assert( array( 'explanation_first', 'missing_why', 'missing_action', 'weak_cta', 'poor_rhythm', 'emoji_rule' ) === $quality_schema['properties']['issues']['items']['enum'], 'The quality review must expose only actionable conversion issues.' );
pct_assert( true === personal_cta_threads_validate_quality_review( array( 'decision' => 'pass', 'issues' => array() ) ), 'A passing quality review must have no issues.' );
pct_assert( true === personal_cta_threads_validate_quality_review( array( 'decision' => 'rewrite', 'issues' => array( 'weak_cta' ) ) ), 'A rewrite quality review must accept one valid issue.' );
$pass_with_issue = personal_cta_threads_validate_quality_review( array( 'decision' => 'pass', 'issues' => array( 'weak_cta' ) ) );
pct_assert( is_wp_error( $pass_with_issue ) && 'pct_invalid_quality_review' === $pass_with_issue->get_error_code(), 'A pass quality review must reject issues.' );
$rewrite_without_issue = personal_cta_threads_validate_quality_review( array( 'decision' => 'rewrite', 'issues' => array() ) );
pct_assert( is_wp_error( $rewrite_without_issue ) && 'pct_invalid_quality_review' === $rewrite_without_issue->get_error_code(), 'A rewrite quality review must require an issue.' );
$unknown_quality_issue = personal_cta_threads_validate_quality_review( array( 'decision' => 'rewrite', 'issues' => array( 'unknown_issue' ) ) );
pct_assert( is_wp_error( $unknown_quality_issue ) && 'pct_invalid_quality_review' === $unknown_quality_issue->get_error_code(), 'A quality review must reject unknown issues.' );
$duplicate_quality_issue = personal_cta_threads_validate_quality_review( array( 'decision' => 'rewrite', 'issues' => array( 'weak_cta', 'weak_cta' ) ) );
pct_assert( is_wp_error( $duplicate_quality_issue ) && 'pct_invalid_quality_review' === $duplicate_quality_issue->get_error_code(), 'A quality review must reject duplicate issues.' );

$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array(
		'id'     => 'resp_quality_effort',
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => '{"ok":true}' ) ) ) ),
	) ),
);
$quality_request = personal_cta_threads_openai_request(
	'quality',
	'test quality prompt',
	array(),
	array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'ok' ),
		'properties'           => array( 'ok' => array( 'type' => 'boolean' ) ),
	)
);
$quality_payload = json_decode( $test_remote_requests[ count( $test_remote_requests ) - 1 ]['args']['body'], true );
pct_assert( is_array( $quality_request ) && 'medium' === $quality_payload['reasoning']['effort'] && 2048 === $quality_payload['max_output_tokens'], 'The bounded quality check must use its compact medium-effort budget.' );

$multiple_outputs = personal_cta_threads_openai_parse_response(
	array(
		'status' => 'completed',
		'output' => array(
			array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => '{"a":1}' ) ) ),
			array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => '{"b":2}' ) ) ),
		),
	)
);
pct_assert( is_wp_error( $multiple_outputs ), 'Ambiguous multiple output_text values must be rejected.' );

$fact_map = array(
	'topic'            => '테스트',
	'reader_problem'   => '확인 필요',
	'primary_solution' => '첫 문단 확인',
	'facts'            => array(
		array(
			'id'            => 'F1',
			'claim'         => '첫 문단이 있다.',
			'evidence'      => array( array( 'source_id' => 'S002', 'quote' => '첫 문단입니다.' ) ),
			'must_preserve' => array( '첫 문단' ),
		),
	),
	'reader_stakes'     => array( array( 'text' => '무엇을 먼저 확인할지 판단해야 한다.', 'fact_ids' => array( 'F1' ) ) ),
	'common_mistakes'   => array(),
	'why_it_matters'    => array( array( 'text' => '첫 문단 확인이 핵심이다.', 'fact_ids' => array( 'F1' ) ) ),
	'unexpected_points' => array(),
	'actionable_payoffs' => array( array( 'text' => '첫 문단을 확인하는 행동이 가능하다.', 'fact_ids' => array( 'F1' ) ) ),
	'curiosity_gaps'    => array(),
	'weak_points_for_copy' => array( array( 'text' => '제목만 반복하지 않는다.', 'fact_ids' => array( 'F1' ) ) ),
	'hook_angles'      => array(
		array( 'id' => 'H1', 'type' => 'convenience', 'premise' => '첫째', 'fact_ids' => array( 'F1' ) ),
		array( 'id' => 'H2', 'type' => 'warning', 'premise' => '둘째', 'fact_ids' => array( 'F1' ) ),
		array( 'id' => 'H3', 'type' => 'other', 'premise' => '셋째', 'fact_ids' => array( 'F1' ) ),
	),
	'blockers'         => array(),
);
pct_assert( true === personal_cta_threads_validate_fact_map( $fact_map, $source['text'] ), 'Literal FACT evidence must validate against its source segment.' );
$fact_schema = personal_cta_threads_fact_schema();
foreach ( personal_cta_threads_content_value_keys() as $value_key ) {
	pct_assert( in_array( $value_key, $fact_schema['required'], true ) && isset( $fact_schema['properties'][ $value_key ] ), 'Every grounded content-value field must be required by the FACT schema.' );
}
pct_assert( 12 === $fact_schema['properties']['facts']['maxItems'] && 1 === $fact_schema['properties']['facts']['items']['properties']['evidence']['maxItems'] && 2 === $fact_schema['properties']['reader_stakes']['maxItems'], 'FACT schema must bound analysis output to the Threads use case.' );
$unknown_value_fact_map = $fact_map;
$unknown_value_fact_map['why_it_matters'][0]['fact_ids'] = array( 'F999' );
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $unknown_value_fact_map, $source['text'] ) ), 'Content-value hints may only cite known FACT IDs.' );
$missing_value_fact_map = $fact_map;
unset( $missing_value_fact_map['reader_stakes'] );
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $missing_value_fact_map, $source['text'] ) ), 'All content-value fields must be present even when empty.' );
$empty_value_fact_map = $fact_map;
foreach ( personal_cta_threads_content_value_keys() as $value_key ) {
	$empty_value_fact_map[ $value_key ] = array();
}
pct_assert( true === personal_cta_threads_validate_fact_map( $empty_value_fact_map, $source['text'] ), 'Empty content-value fields must remain valid when the source offers no safe editorial hint.' );
$too_many_values_fact_map = $fact_map;
$too_many_values_fact_map['reader_stakes'] = array_fill( 0, 3, $fact_map['reader_stakes'][0] );
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $too_many_values_fact_map, $source['text'] ) ), 'FACT value lists must stay compact.' );
$blocked_invalid_value_fact_map = $fact_map;
$blocked_invalid_value_fact_map['blockers'] = array( '원문이 모순됩니다.' );
unset( $blocked_invalid_value_fact_map['reader_stakes'] );
pct_assert( is_wp_error( personal_cta_threads_validate_fact_map( $blocked_invalid_value_fact_map, $source['text'] ) ), 'Blockers may not bypass content-value validation.' );

$copy = array(
	'text'          => "첫 문단입니다.\n확인해봐.",
	'hook_angle_id' => 'H1',
	'fact_ids'      => array( 'F1' ),
	'claims'        => array( array( 'text' => '첫 문단입니다.', 'fact_ids' => array( 'F1' ) ) ),
);
pct_assert( true === personal_cta_threads_validate_copy( $copy, $fact_map, 'H1' ), 'A grounded copy must pass semantic validation.' );
$copy_schema = personal_cta_threads_copy_schema();
pct_assert( 12 === $copy_schema['properties']['fact_ids']['maxItems'] && 8 === $copy_schema['properties']['claims']['maxItems'] && 4 === $copy_schema['properties']['claims']['items']['properties']['fact_ids']['maxItems'], 'Copy metadata must stay bounded so it cannot consume the editor output budget.' );
$too_many_claims = $copy;
$too_many_claims['claims'] = array_fill( 0, 9, $copy['claims'][0] );
pct_assert( is_wp_error( personal_cta_threads_validate_copy( $too_many_claims, $fact_map, 'H1' ) ), 'Copy validation must mirror the structured-output claim cap.' );
$missing_copy         = $copy;
$missing_copy['text'] = '확인해봐.';
$missing_error        = personal_cta_threads_validate_copy( $missing_copy, $fact_map, 'H1' );
pct_assert( is_wp_error( $missing_error ) && 'pct_missing_preserve' === $missing_error->get_error_code(), 'Missing literal preservation must return a dedicated error.' );
pct_assert( in_array( '첫 문단', (array) $missing_error->get_error_data()['missing_tokens'], true ), 'Missing preservation errors must identify the omitted source token.' );
$optional_fact_map                              = $fact_map;
$optional_fact_map['facts'][0]['must_preserve'] = array();
pct_assert( true === personal_cta_threads_validate_copy( $missing_copy, $optional_fact_map, 'H1' ), 'Facts without literal preservation requirements must remain valid.' );
$literal_repair = personal_cta_threads_queue_literal_repair( 7, $missing_copy, 'writer', 'H1', array( '첫 문단' ) );
pct_assert( is_array( $literal_repair ) && ! empty( $literal_repair['pending'] ), 'Missing literals must schedule one repair step.' );
pct_assert( 'writer' === personal_cta_threads_meta( 7, 'literal_repair' )['target'], 'Literal repair must remember the writer target.' );
$zero_literal_repair = personal_cta_threads_queue_literal_repair( 7, $missing_copy, 'writer', 'H1', array( '0' ) );
pct_assert( is_array( $zero_literal_repair ) && in_array( '0', personal_cta_threads_meta( 7, 'literal_repair' )['missing_tokens'], true ), 'A zero value must remain a required literal.' );
$literal_repair = personal_cta_threads_queue_literal_repair( 7, $missing_copy, 'writer', 'H1', array( '첫 문단' ) );
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array(
		'id'     => 'resp_literal_repair',
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => wp_json_encode( $copy ) ) ) ) ),
	) ),
);
$repaired_copy = personal_cta_threads_run_literal_repair( 7, $source, $fact_map );
pct_assert( is_array( $repaired_copy ) && ! empty( $repaired_copy['pending'] ), 'Literal repair must resume generation after one corrected model call.' );
pct_assert( $copy === personal_cta_threads_meta( 7, 'drafts' )['H1'], 'Literal repair must replace the affected writer draft.' );
$length_literal_repair = personal_cta_threads_queue_literal_repair( 7, $missing_copy, 'repair', 'H1', array( '첫 문단' ) );
$length_repaired_copy  = personal_cta_threads_run_literal_repair( 7, $source, $fact_map );
pct_assert( is_array( $length_literal_repair ) && is_array( $length_repaired_copy ) && $copy === personal_cta_threads_meta( 7, 'repair_result' ), 'Length-repair literals must stay in the length-repair result.' );

$units = personal_cta_threads_candidate_units( $copy['text'] );
$verification = array(
	'decision' => 'pass',
	'checks'   => array(
		array( 'unit_id' => 'T001', 'claim' => '첫 문단입니다.', 'verdict' => 'supported', 'fact_ids' => array( 'F1' ), 'evidence_ids' => array( 'S002' ), 'reason' => '직접 근거' ),
		array( 'unit_id' => 'T002', 'claim' => '확인해봐.', 'verdict' => 'non_factual', 'fact_ids' => array(), 'evidence_ids' => array(), 'reason' => '행동 안내' ),
	),
	'issues'   => array(),
);
pct_assert( true === personal_cta_threads_validate_verifier( $verification, $fact_map, $source['text'], $units, array( 'F1' ) ), 'Every candidate line must receive one valid verifier decision.' );
$missing_check = $verification;
array_pop( $missing_check['checks'] );
pct_assert( is_wp_error( personal_cta_threads_validate_verifier( $missing_check, $fact_map, $source['text'], $units, array( 'F1' ) ) ), 'A verifier may not omit a candidate line.' );

personal_cta_threads_set_meta( 7, 'drafts', array( 'H1' => $copy, 'H2' => $copy, 'H3' => $copy ) );
personal_cta_threads_set_meta( 7, 'editor_result', $copy );
personal_cta_threads_set_meta( 7, 'repair_result', $copy );
personal_cta_threads_set_meta( 7, 'final_text', $copy['text'] );
personal_cta_threads_set_state( 7, 'ready', 'ready' );
$diagnostics = personal_cta_threads_admin_diagnostics( 7 );
pct_assert( 3 === count( $diagnostics['drafts'] ) && 'H1' === $diagnostics['drafts'][0]['id'], 'Diagnostics must preserve the writer checkpoint order.' );
pct_assert( $copy['text'] === $diagnostics['editor']['text'] && $copy['text'] === $diagnostics['repair']['text'] && $copy['text'] === $diagnostics['final']['text'], 'Diagnostics must expose only the saved copy checkpoints.' );
pct_assert( false === strpos( wp_json_encode( $diagnostics ), 'fact_ids' ), 'Diagnostics must not expose FACT evidence or model metadata.' );
$ready_state = personal_cta_threads_admin_state( 7 );
pct_assert( '' !== $ready_state['copy_text'], 'A ready generation must expose its copy text.' );
personal_cta_threads_set_state( 7, 'failed', 'editor', 'Test failure.' );
$failed_state = personal_cta_threads_admin_state( 7 );
pct_assert( '' === $failed_state['text'] && '' === $failed_state['ai_original'] && '' === $failed_state['copy_text'] && 0 === $failed_state['length'], 'A failed generation must not expose an older copy as the current result.' );
personal_cta_threads_set_state( 7, 'ready', 'ready' );

$current_delivery_context = personal_cta_threads_outbound_url( 7 );
$current_run_key = hash( 'sha256', $source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . wp_json_encode( personal_cta_threads_prompt_versions() ) . '|' . hash( 'sha256', personal_cta_threads_style_examples_text() ) . '|' . $current_delivery_context );
personal_cta_threads_set_meta( 7, 'source_hash', $source['hash'] );
personal_cta_threads_set_meta( 7, 'generation_key', $current_run_key );
personal_cta_threads_set_state( 7, 'queued', 'queued' );
$reused_copy = personal_cta_threads_generate( 7, false );
pct_assert( is_array( $reused_copy ) && ! empty( $reused_copy['reused'] ) && 'ready' === personal_cta_threads_meta( 7, 'status' ), 'A matching completed generation must restore ready state without calling the model.' );
$link_toggle_settings                 = personal_cta_threads_settings();
$link_toggle_settings['include_link'] = false;
update_option( PERSONAL_CTA_THREADS_SETTINGS_OPTION, $link_toggle_settings );
personal_cta_threads_set_state( 7, 'queued', 'queued' );
$link_toggled_copy = personal_cta_threads_generate( 7, false );
pct_assert( ! is_array( $link_toggled_copy ) || empty( $link_toggled_copy['reused'] ), 'Changing the outbound-link context must not reuse a copy with a different CTA or body budget.' );
$link_toggle_settings['include_link'] = true;
update_option( PERSONAL_CTA_THREADS_SETTINGS_OPTION, $link_toggle_settings );
$test_permalink = 'https://example.test/' . str_repeat( 'a', 500 );
$url_request_count = count( $test_remote_requests );
$url_too_long = personal_cta_threads_generate( 11, false );
pct_assert( is_wp_error( $url_too_long ) && 'pct_outbound_url_too_long' === $url_too_long->get_error_code() && $url_request_count === count( $test_remote_requests ), 'An impossible outbound URL must fail before any model call.' );
$test_permalink = '';
personal_cta_threads_set_meta( 7, 'generation_key', 'outdated-generation-key' );
personal_cta_threads_set_state( 7, 'queued', 'queued' );
$outdated_copy = personal_cta_threads_generate( 7, false );
pct_assert( ! is_array( $outdated_copy ) || empty( $outdated_copy['reused'] ), 'A prompt-version change must not reuse an older completed generation.' );

$queued = personal_cta_threads_queue( 7, false );
pct_assert( true === $queued, 'A copy-generation request must queue successfully.' );
pct_assert( 'queued' === personal_cta_threads_meta( 7, 'status' ), 'A copy-generation request must never publish the post.' );
pct_assert( (int) personal_cta_threads_meta( 7, 'last_heartbeat', 0 ) > 0 && (int) personal_cta_threads_meta( 7, 'lease_until', 0 ) > time(), 'A queued job must be recoverable by the watchdog.' );
personal_cta_threads_set_state( 7, 'drafting', 'writer_h2_complete' );
delete_post_meta( 7, '_pct_threads_lease_until' );
pct_assert( true === personal_cta_threads_resume( 7 ), 'A stalled job must be requeued without discarding its checkpoints.' );
pct_assert( 'drafting' === personal_cta_threads_meta( 7, 'status' ) && 'writer_h2_complete' === personal_cta_threads_meta( 7, 'stage' ), 'Resuming must preserve the generation checkpoint state.' );

personal_cta_threads_set_meta( 7, 'generation_id', 'retry-fixture' );
personal_cta_threads_set_state( 7, 'editing', 'editor' );
$first_transport_retry = personal_cta_threads_retry_transient_error( 7, new WP_Error( 'pct_openai_network', 'test' ) );
$second_transport_retry = personal_cta_threads_retry_transient_error( 7, new WP_Error( 'pct_openai_network', 'test' ) );
pct_assert( true === $first_transport_retry && false === $second_transport_retry && 'retry_wait' === personal_cta_threads_meta( 7, 'stage' ), 'A generation must spend at most one automatic transport retry.' );
delete_post_meta( 7, '_pct_threads_transport_retry' );

$transport_post_id = 12;
$transport_request_count = count( $test_remote_requests );
$test_remote_response = new WP_Error( 'http_request_failed', 'fixture network failure' );
personal_cta_threads_set_state( $transport_post_id, 'queued', 'queued' );
personal_cta_threads_run_job( $transport_post_id );
pct_assert( 'analyzing' === personal_cta_threads_meta( $transport_post_id, 'status' ) && 'retry_wait' === personal_cta_threads_meta( $transport_post_id, 'stage' ), 'A transient model transport failure must retain its checkpoint and wait once.' );
delete_option( 'personal_cta_threads_' . $transport_post_id . '.lock' );
personal_cta_threads_run_job( $transport_post_id );
pct_assert( 'failed' === personal_cta_threads_meta( $transport_post_id, 'status' ) && 'fact' === personal_cta_threads_meta( $transport_post_id, 'stage' ) && $transport_request_count + 2 === count( $test_remote_requests ), 'The second transient failure must stop instead of creating a retry loop.' );

delete_post_meta( 7, '_pct_threads_final_text' );
delete_post_meta( 7, '_pct_threads_source_hash' );
delete_post_meta( 7, '_pct_threads_generation_key' );
delete_post_meta( 7, '_pct_threads_fact_map' );
delete_post_meta( 7, '_pct_threads_fact_cache_key' );
delete_post_meta( 7, '_pct_threads_lease_until' );
personal_cta_threads_set_state( 7, 'queued', 'queued' );
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array( 'status' => 'incomplete', 'incomplete_details' => array( 'reason' => 'max_output_tokens' ) ) ),
);
personal_cta_threads_run_job( 7 );
pct_assert( 'failed' === personal_cta_threads_meta( 7, 'status' ) && 'fact' === personal_cta_threads_meta( 7, 'stage' ), 'A failed model call must retain the concrete pipeline stage for diagnostics.' );

$quality_post_id = 8;
$quality_run_key = hash( 'sha256', $source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . wp_json_encode( personal_cta_threads_prompt_versions() ) . '|' . hash( 'sha256', personal_cta_threads_style_examples_text() ) . '|' . personal_cta_threads_outbound_url( $quality_post_id ) );
$quality_fact_key = hash( 'sha256', $source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . PERSONAL_CTA_THREADS_FACT_PROMPT_VERSION . '|' . PERSONAL_CTA_THREADS_SCHEMA_VERSION );
$quality_drafts = array();
foreach ( array( 'H1', 'H2', 'H3' ) as $quality_hook_id ) {
	$quality_draft                  = $copy;
	$quality_draft['hook_angle_id'] = $quality_hook_id;
	$quality_drafts[ $quality_hook_id ] = $quality_draft;
}
personal_cta_threads_set_meta( $quality_post_id, 'fact_map', $fact_map );
personal_cta_threads_set_meta( $quality_post_id, 'fact_cache_key', $quality_fact_key );
personal_cta_threads_set_meta( $quality_post_id, 'drafts', $quality_drafts );
personal_cta_threads_set_meta( $quality_post_id, 'editor_result', $copy );
personal_cta_threads_set_meta( $quality_post_id, 'generation_key', $quality_run_key );
personal_cta_threads_set_state( $quality_post_id, 'editing', 'editor_complete' );
$quality_request_count = count( $test_remote_requests );
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array(
		'id'     => 'resp_quality_rewrite',
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => wp_json_encode( array( 'decision' => 'rewrite', 'issues' => array( 'weak_cta' ) ) ) ) ) ) ),
	) ),
);
$quality_pending = personal_cta_threads_generate( $quality_post_id, false );
pct_assert( is_array( $quality_pending ) && ! empty( $quality_pending['pending'] ) && 'quality_complete' === personal_cta_threads_meta( $quality_post_id, 'stage' ) && $quality_request_count + 1 === count( $test_remote_requests ), 'A cached editor result must schedule exactly one quality review.' );

$conversion_copy = $copy;
$conversion_copy['text'] = "첫 문단입니다.\n\n무엇을 먼저 확인할지 링크에서 확인해봐 👇";
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array(
		'id'     => 'resp_conversion_repair',
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => wp_json_encode( $conversion_copy ) ) ) ) ),
	) ),
);
$conversion_pending = personal_cta_threads_generate( $quality_post_id, false );
pct_assert( is_array( $conversion_pending ) && ! empty( $conversion_pending['pending'] ) && 'conversion_repair_complete' === personal_cta_threads_meta( $quality_post_id, 'stage' ) && 1 === (int) personal_cta_threads_meta( $quality_post_id, 'conversion_rewrite_done' ) && $quality_request_count + 2 === count( $test_remote_requests ), 'A rewrite review must schedule exactly one conversion repair.' );

$ready_request_count = count( $test_remote_requests );
$quality_ready        = personal_cta_threads_generate( $quality_post_id, false );
pct_assert( is_array( $quality_ready ) && empty( $quality_ready['pending'] ) && 'ready' === personal_cta_threads_meta( $quality_post_id, 'status' ) && $conversion_copy['text'] === personal_cta_threads_meta( $quality_post_id, 'final_text' ) && $ready_request_count === count( $test_remote_requests ), 'A repaired conversion must finish without another quality request.' );

$editor_recovery_post_id = 9;
$editor_recovery_source  = personal_cta_threads_source( $editor_recovery_post_id );
$editor_recovery_run_key = hash( 'sha256', $editor_recovery_source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . wp_json_encode( personal_cta_threads_prompt_versions() ) . '|' . hash( 'sha256', personal_cta_threads_style_examples_text() ) . '|' . personal_cta_threads_outbound_url( $editor_recovery_post_id ) );
$editor_recovery_fact_key = hash( 'sha256', $editor_recovery_source['hash'] . '|' . personal_cta_threads_openai_model() . '|' . PERSONAL_CTA_THREADS_FACT_PROMPT_VERSION . '|' . PERSONAL_CTA_THREADS_SCHEMA_VERSION );
$editor_recovery_drafts   = array();
foreach ( array( 'H1', 'H2', 'H3' ) as $editor_recovery_hook_id ) {
	$editor_recovery_draft                  = $copy;
	$editor_recovery_draft['hook_angle_id'] = $editor_recovery_hook_id;
	$editor_recovery_drafts[ $editor_recovery_hook_id ] = $editor_recovery_draft;
}
personal_cta_threads_set_meta( $editor_recovery_post_id, 'fact_map', $fact_map );
personal_cta_threads_set_meta( $editor_recovery_post_id, 'fact_cache_key', $editor_recovery_fact_key );
personal_cta_threads_set_meta( $editor_recovery_post_id, 'drafts', $editor_recovery_drafts );
personal_cta_threads_set_meta( $editor_recovery_post_id, 'generation_key', $editor_recovery_run_key );
personal_cta_threads_set_state( $editor_recovery_post_id, 'editing', 'editor' );
$editor_request_count = count( $test_remote_requests );
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array( 'status' => 'incomplete', 'incomplete_details' => array( 'reason' => 'max_output_tokens' ) ) ),
);
$editor_retry_pending = personal_cta_threads_generate( $editor_recovery_post_id, false );
$editor_first_payload = json_decode( $test_remote_requests[ count( $test_remote_requests ) - 1 ]['args']['body'], true );
pct_assert( is_array( $editor_retry_pending ) && ! empty( $editor_retry_pending['pending'] ) && 'editor_retry' === personal_cta_threads_meta( $editor_recovery_post_id, 'stage' ) && 1 === (int) personal_cta_threads_meta( $editor_recovery_post_id, 'editor_output_retry' ) && $editor_request_count + 1 === count( $test_remote_requests ) && 6144 === $editor_first_payload['max_output_tokens'] && 'medium' === $editor_first_payload['reasoning']['effort'], 'A truncated editor must schedule exactly one medium-effort to low-effort recovery.' );
$test_remote_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array(
		'id'     => 'resp_editor_recovery',
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => wp_json_encode( $copy ) ) ) ) ),
	) ),
);
$editor_recovered     = personal_cta_threads_generate( $editor_recovery_post_id, false );
$editor_retry_payload = json_decode( $test_remote_requests[ count( $test_remote_requests ) - 1 ]['args']['body'], true );
$editor_retry_input   = $editor_retry_payload['input'][1]['content'][0]['text'];
pct_assert( is_array( $editor_recovered ) && ! empty( $editor_recovered['pending'] ) && 'editor_complete' === personal_cta_threads_meta( $editor_recovery_post_id, 'stage' ) && '' === personal_cta_threads_meta( $editor_recovery_post_id, 'editor_output_retry' ) && $editor_request_count + 2 === count( $test_remote_requests ) && 8192 === $editor_retry_payload['max_output_tokens'] && 'low' === $editor_retry_payload['reasoning']['effort'] && false === strpos( $editor_retry_input, '"source_document"' ), 'The single editor recovery must use the compact FACT MAP-only request and then clear its retry marker.' );

echo "Threads copy-generation safeguards are valid.\n";
