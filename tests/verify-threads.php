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
function get_permalink( $post_id ) { return 'https://example.test/sample-post/'; }
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
function wp_remote_post( $url, $args = array() ) { global $test_remote_response; return $test_remote_response; }
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
function wp_remote_retrieve_response_code( $response ) { return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0; }

require dirname( __DIR__ ) . '/includes/threads-core.php';
require dirname( __DIR__ ) . '/includes/threads-openai.php';

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

$refusal = personal_cta_threads_openai_parse_response(
	array(
		'status' => 'completed',
		'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'refusal', 'refusal' => 'no' ) ) ) ),
	)
);
pct_assert( is_wp_error( $refusal ) && 'pct_openai_refusal' === $refusal->get_error_code(), 'A model refusal must never be treated as copy.' );

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
	'hook_angles'      => array(
		array( 'id' => 'H1', 'type' => 'convenience', 'premise' => '첫째', 'fact_ids' => array( 'F1' ) ),
		array( 'id' => 'H2', 'type' => 'warning', 'premise' => '둘째', 'fact_ids' => array( 'F1' ) ),
		array( 'id' => 'H3', 'type' => 'other', 'premise' => '셋째', 'fact_ids' => array( 'F1' ) ),
	),
	'blockers'         => array(),
);
pct_assert( true === personal_cta_threads_validate_fact_map( $fact_map, $source['text'] ), 'Literal FACT evidence must validate against its source segment.' );

$copy = array(
	'text'          => "첫 문단입니다.\n확인해봐.",
	'hook_angle_id' => 'H1',
	'fact_ids'      => array( 'F1' ),
	'claims'        => array( array( 'text' => '첫 문단입니다.', 'fact_ids' => array( 'F1' ) ) ),
);
pct_assert( true === personal_cta_threads_validate_copy( $copy, $fact_map, 'H1' ), 'A grounded copy must pass semantic validation.' );
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

$queued = personal_cta_threads_queue( 7, false );
pct_assert( true === $queued, 'A copy-generation request must queue successfully.' );
pct_assert( 'queued' === personal_cta_threads_meta( 7, 'status' ), 'A copy-generation request must never publish the post.' );
pct_assert( (int) personal_cta_threads_meta( 7, 'last_heartbeat', 0 ) > 0 && (int) personal_cta_threads_meta( 7, 'lease_until', 0 ) > time(), 'A queued job must be recoverable by the watchdog.' );
delete_post_meta( 7, '_pct_threads_lease_until' );
pct_assert( true === personal_cta_threads_resume( 7 ), 'A stalled job must be requeued without discarding its checkpoints.' );

echo "Threads copy-generation safeguards are valid.\n";
