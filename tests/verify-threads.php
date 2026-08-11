<?php
/**
 * Lightweight regression tests for the Threads helpers without WordPress.
 */

define( 'ABSPATH', __DIR__ );
define( 'PERSONAL_CTA_BLOCKS_FILE', dirname( __DIR__ ) . '/personal-cta-blocks.php' );
define( 'PERSONAL_CTA_THREADS_MASTER_KEY', 'test-only-master-key-that-is-never-shipped-as-a-secret' );
define( 'PERSONAL_CTA_THREADS_USER_ID', '123456789' );
define( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN', 'test-token' );
define( 'DAY_IN_SECONDS', 86400 );

$test_options = array();
$test_meta    = array();
$test_fail_meta_key = '';

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
	global $test_meta, $test_fail_meta_key;
	if ( '' !== $test_fail_meta_key && $key === $test_fail_meta_key ) {
		return false;
	}
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
function wp_json_encode( $value ) { return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
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

$test_http_requests = array();
$test_ambiguous_publish = false;
function wp_remote_request( $url, $args ) {
	global $test_http_requests, $test_ambiguous_publish;
	$test_http_requests[] = array( 'url' => $url, 'args' => $args );

	if ( false !== strpos( $url, '/threads_publish' ) ) {
		if ( $test_ambiguous_publish ) {
			return new WP_Error( 'http_request_failed', 'simulated timeout' );
		}
		$data = array( 'id' => 'media-1' );
	} elseif ( 'POST' === $args['method'] && preg_match( '#/\d+/threads$#', $url ) ) {
		$test_ambiguous_publish = isset( $args['body']['text'] ) && '모호한 게시' === $args['body']['text'];
		$data = array( 'id' => $test_ambiguous_publish ? 'container-ambiguous' : 'container-1' );
	} elseif ( false !== strpos( $url, '/container-ambiguous' ) ) {
		$data = array( 'id' => 'container-ambiguous', 'status' => 'FINISHED' );
	} elseif ( 'GET' === $args['method'] && false !== strpos( $url, '/123456789/threads' ) ) {
		$data = array( 'data' => array() );
	} elseif ( false !== strpos( $url, '/media-1' ) ) {
		$data = array( 'id' => 'media-1', 'permalink' => 'https://www.threads.com/@example/post/1' );
	} else {
		$data = array();
	}

	return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $data ) );
}
function wp_remote_retrieve_response_code( $response ) { return (int) $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return (string) $response['body']; }

require dirname( __DIR__ ) . '/includes/threads-core.php';
require dirname( __DIR__ ) . '/includes/threads-openai.php';
require dirname( __DIR__ ) . '/includes/threads-meta.php';

function pct_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

pct_assert( 3 === personal_cta_threads_character_length( '가나다' ), 'Unicode length fallback is invalid.' );
pct_assert( 5 === personal_cta_threads_length( '가😀' ), 'Meta emoji byte counting is invalid.' );
pct_assert( "첫째\n둘째" === personal_cta_threads_clean_text( '<p>첫째</p><p>둘째</p>' ), 'HTML normalization is invalid.' );

$source = personal_cta_threads_source( 7 );
pct_assert( is_array( $source ), 'A valid post must produce a source document.' );
pct_assert( false !== strpos( $source['text'], '[S001] 제목: 테스트 글' ), 'The source title is missing.' );
pct_assert( false !== strpos( $source['text'], '첫 문단입니다.' ), 'The source paragraph is missing.' );
pct_assert( false === strpos( $source['text'], '[secret]' ), 'Shortcodes must not enter the model source.' );

$payload = personal_cta_threads_payload_text( 7, '본문' );
pct_assert( is_array( $payload ), 'A short Threads payload must be valid.' );
pct_assert( '본문' === $payload['text'], 'Attachment mode must not append the URL to text.' );
pct_assert( false !== strpos( $payload['link_attachment'], 'utm_source=threads' ), 'The deterministic outbound URL is missing UTM data.' );

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

$encrypted = personal_cta_threads_encrypt_token( 'sensitive-test-token' );
pct_assert( is_array( $encrypted ), 'The token must be encrypted before storage.' );
pct_assert( 'sensitive-test-token' === personal_cta_threads_decrypt_token( $encrypted ), 'Encrypted token roundtrip failed.' );
$tampered               = $encrypted;
$tampered['ciphertext'] = base64_encode( 'tampered' );
pct_assert( is_wp_error( personal_cta_threads_decrypt_token( $tampered ) ), 'Tampered ciphertext must be rejected.' );

$published = personal_cta_threads_publish( 7, '게시할 본문' );
pct_assert( is_array( $published ) && 'media-1' === $published['id'], 'A confirmed Meta publish must persist its media ID.' );
$request_count = count( $test_http_requests );
$again         = personal_cta_threads_publish( 7, '게시할 본문' );
pct_assert( is_array( $again ) && 'media-1' === $again['id'], 'An already published post must return its saved result.' );
pct_assert( $request_count === count( $test_http_requests ), 'A duplicate publish must make zero HTTP requests.' );

$uncertain = personal_cta_threads_publish( 8, '모호한 게시' );
pct_assert( is_wp_error( $uncertain ) && 'pct_uncertain' === $uncertain->get_error_code(), 'An ambiguous publish must stop in the uncertain state.' );
$post_count = count(
	array_filter(
		$test_http_requests,
		function ( $request ) {
			return 'POST' === $request['args']['method'];
		}
	)
);
personal_cta_threads_publish( 8, '모호한 게시' );
$post_count_after_reconcile = count(
	array_filter(
		$test_http_requests,
		function ( $request ) {
			return 'POST' === $request['args']['method'];
		}
	)
);
pct_assert( $post_count === $post_count_after_reconcile, 'Ambiguous recovery must never resend a Meta POST.' );

$test_fail_meta_key = '_pct_threads_publish_started_at';
$before_requests     = count( $test_http_requests );
$checkpoint_failure = personal_cta_threads_publish( 9, '체크포인트 실패 테스트' );
$new_requests        = array_slice( $test_http_requests, $before_requests );
$publish_posts       = array_filter(
	$new_requests,
	function ( $request ) {
		return 'POST' === $request['args']['method'] && false !== strpos( $request['url'], '/threads_publish' );
	}
);
pct_assert( is_wp_error( $checkpoint_failure ) && 'pct_meta_checkpoint_failed' === $checkpoint_failure->get_error_code(), 'A failed durable marker must abort publishing.' );
pct_assert( 0 === count( $publish_posts ), 'The non-idempotent publish POST must not run after a checkpoint failure.' );
$test_fail_meta_key = '';

personal_cta_threads_set_meta( 10, 'creation_id', 'orphan-container' );
personal_cta_threads_set_state( 10, 'publishing', 'publishing' );
$partial_container = personal_cta_threads_reconcile( 10 );
pct_assert( is_wp_error( $partial_container ) && 'pct_meta_publish_not_started' === $partial_container->get_error_code(), 'A container-only crash must become explicitly retryable.' );
pct_assert( '' === personal_cta_threads_meta( 10, 'creation_id' ), 'A safe container-only checkpoint must be cleared.' );

personal_cta_threads_set_meta( 11, 'remote_id', 'media-partial' );
personal_cta_threads_set_state( 11, 'publishing', 'publishing' );
$partial_remote = personal_cta_threads_reconcile( 11 );
pct_assert( is_array( $partial_remote ) && 'media-partial' === $partial_remote['id'], 'A saved remote ID must repair partial local state.' );
pct_assert( 'published' === personal_cta_threads_meta( 11, 'status' ) && 0 < personal_cta_threads_meta( 11, 'published_at', 0 ), 'Partial remote state was not finalized.' );

echo "Threads core, OpenAI, and Meta safeguards are valid.\n";
