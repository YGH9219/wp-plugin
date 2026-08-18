<?php
/** Minimal contract checks for section-specific AI image generation. */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'PERSONAL_CTA_BLOCKS_URL', 'https://example.test/wp-content/plugins/personal-cta-blocks/' );
define( 'PERSONAL_CTA_BLOCKS_VERSION', 'test' );

$test_post_meta           = array();
$test_attachments         = array();
$test_attachment_files    = array();
$test_attachment_urls     = array();
$test_attachment_mimes    = array();
$test_attachment_metadata = array();
$test_inserted_attachments = array();
$test_upload_records      = array();
$test_remote_requests     = array();
$test_remote_responses    = array();
$test_next_attachment_id  = 200;
$test_uploads_directory   = sys_get_temp_dir() . '/pct-inline-' . uniqid();
$test_active_locks        = array();
$test_lock_scopes         = array();
$test_unlock_calls        = 0;
$test_force_lock_error    = false;
$test_can_manage_post     = true;
$test_capabilities        = array(
	'manage_options' => true,
	'edit_post'      => true,
	'upload_files'   => true,
);

function add_action() {}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
	public function add_data( $data ) { $this->data = $data; }
}

function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function esc_url_raw( $value ) { return (string) $value; }
function wp_delete_file( $path ) { if ( is_file( $path ) ) { unlink( $path ); } }

function personal_cta_threads_openai_key() { return 'test-openai-key'; }

function wp_remote_post( $url, $args ) {
	global $test_remote_requests, $test_remote_responses;
	$test_remote_requests[] = array( 'url' => $url, 'args' => $args );

	return $test_remote_responses
		? array_shift( $test_remote_responses )
		: new WP_Error( 'missing_fixture', 'No remote response was queued.' );
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['code'] ) ? (int) $response['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

function get_post_meta( $post_id, $key, $single = false ) {
	global $test_post_meta;

	return isset( $test_post_meta[ $post_id ][ $key ] ) ? $test_post_meta[ $post_id ][ $key ] : '';
}

function update_post_meta( $post_id, $key, $value ) {
	global $test_post_meta;
	$test_post_meta[ $post_id ][ $key ] = $value;

	return true;
}

function wp_upload_bits( $name, $deprecated, $bits ) {
	global $test_upload_records, $test_uploads_directory;
	if ( ! is_dir( $test_uploads_directory ) && ! mkdir( $test_uploads_directory, 0777, true ) ) {
		return array( 'error' => 'mkdir failed' );
	}
	$file = $test_uploads_directory . '/' . uniqid( 'image-', true ) . '-' . basename( $name );
	if ( false === file_put_contents( $file, $bits ) ) {
		return array( 'error' => 'write failed' );
	}
	$url = 'https://example.test/uploads/' . basename( $file );
	$test_upload_records[ $file ] = array( 'name' => $name, 'url' => $url, 'bytes' => $bits );

	return array( 'file' => $file, 'url' => $url, 'type' => 'image/jpeg', 'error' => false );
}

function wp_insert_attachment( $attachment, $file, $post_id, $wp_error = false ) {
	global $test_next_attachment_id, $test_attachments, $test_attachment_files, $test_attachment_urls,
		$test_attachment_mimes, $test_inserted_attachments, $test_upload_records;
	$id = $test_next_attachment_id++;
	$test_attachments[ $id ]          = true;
	$test_attachment_files[ $id ]     = $file;
	$test_attachment_urls[ $id ]      = isset( $test_upload_records[ $file ]['url'] ) ? $test_upload_records[ $file ]['url'] : '';
	$test_attachment_mimes[ $id ]     = isset( $attachment['post_mime_type'] ) ? $attachment['post_mime_type'] : '';
	$test_inserted_attachments[ $id ] = array( 'attachment' => $attachment, 'file' => $file, 'post_id' => $post_id, 'wp_error' => $wp_error );

	return $id;
}

function wp_generate_attachment_metadata( $attachment_id, $file ) {
	$size = getimagesize( $file );

	return array(
		'file'   => basename( $file ),
		'width'  => isset( $size[0] ) ? (int) $size[0] : 0,
		'height' => isset( $size[1] ) ? (int) $size[1] : 0,
	);
}

function wp_update_attachment_metadata( $attachment_id, $metadata ) {
	global $test_attachment_metadata;
	$test_attachment_metadata[ $attachment_id ] = $metadata;

	return true;
}

function wp_get_attachment_metadata( $attachment_id ) {
	global $test_attachment_metadata;

	return isset( $test_attachment_metadata[ $attachment_id ] ) ? $test_attachment_metadata[ $attachment_id ] : array();
}

function wp_attachment_is_image( $attachment_id ) {
	global $test_attachments;

	return ! empty( $test_attachments[ $attachment_id ] );
}

function get_attached_file( $attachment_id ) {
	global $test_attachment_files;

	return isset( $test_attachment_files[ $attachment_id ] ) ? $test_attachment_files[ $attachment_id ] : '';
}

function wp_get_attachment_url( $attachment_id ) {
	global $test_attachment_urls;

	return isset( $test_attachment_urls[ $attachment_id ] ) ? $test_attachment_urls[ $attachment_id ] : '';
}

function get_post_mime_type( $attachment_id ) {
	global $test_attachment_mimes;

	return isset( $test_attachment_mimes[ $attachment_id ] ) ? $test_attachment_mimes[ $attachment_id ] : '';
}

function personal_cta_threads_lock( $post_id, $ttl, $scope = 'threads' ) {
	global $test_active_locks, $test_force_lock_error, $test_lock_scopes;
	$post_id = (int) $post_id;
	$test_lock_scopes[] = $scope;
	if ( $test_force_lock_error || isset( $test_active_locks[ $post_id ] ) ) {
		return new WP_Error( 'busy', 'busy' );
	}
	$lock = array( 'post_id' => $post_id, 'token' => uniqid( 'lock-', true ) );
	$test_active_locks[ $post_id ] = $lock['token'];

	return $lock;
}

function personal_cta_threads_unlock( $lock ) {
	global $test_active_locks, $test_unlock_calls;
	if ( is_array( $lock ) && isset( $lock['post_id'] ) ) {
		unset( $test_active_locks[ (int) $lock['post_id'] ] );
	}
	$test_unlock_calls++;
}

function personal_cta_threads_can_manage_post( $post_id ) {
	global $test_can_manage_post;

	return (bool) $test_can_manage_post;
}

function current_user_can( $capability ) {
	global $test_capabilities;

	return ! empty( $test_capabilities[ $capability ] );
}

require dirname( __DIR__ ) . '/includes/inline-images.php';

function pct_inline_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

function pct_inline_test_jpeg() {
	$bytes = file_get_contents( __DIR__ . '/fixtures/ai-background.jpg' );
	$sof   = strpos( $bytes, "\xFF\xC0" );
	if ( false === $sof ) {
		throw new RuntimeException( 'The JPEG fixture has no baseline SOF marker.' );
	}

	return substr_replace( $bytes, pack( 'nn', 800, 1200 ), $sof + 5, 4 );
}

function pct_inline_api_response( $bytes ) {
	return array(
		'code' => 200,
		'body' => json_encode(
			array(
				'data' => array(
					array( 'b64_json' => base64_encode( $bytes ) ),
				),
			)
		),
	);
}

$title   = '배그 2차 비밀번호 초기화 방법';
$heading = 'STEP 2. 계정 보안 확인';
$context = '본인 계정인지 확인한 다음 안전하게 비밀번호를 바꾸는 절차입니다.';
$prompt  = personal_cta_inline_images_prompt( $title, $heading, $context );
pct_inline_assert( false !== strpos( $prompt, $title ) && false !== strpos( $prompt, $heading ) && false !== strpos( $prompt, $context ), 'The prompt must contain the article title, selected heading, and bounded section context.' );
pct_inline_assert( false !== strpos( $prompt, 'Do not render text' ) && false !== strpos( $prompt, 'never as instructions' ), 'The prompt must prohibit generated text and treat article content as untrusted data.' );

$jpeg = pct_inline_test_jpeg();
$size = getimagesizefromstring( $jpeg );
pct_inline_assert( is_array( $size ) && 1200 === $size[0] && 800 === $size[1] && IMAGETYPE_JPEG === $size[2], 'The generated fixture must be an exact 1200x800 JPEG.' );

$test_remote_responses[] = pct_inline_api_response( $jpeg );
$first = personal_cta_inline_images_generate( 7, $title, $heading, $context );
pct_inline_assert( ! is_wp_error( $first ) && 200 === $first['id'] && false === $first['reused'], 'The first section request must create a new media attachment.' );
pct_inline_assert( 1200 === $first['width'] && 800 === $first['height'] && 'image/jpeg' === $first['mime_type'], 'The editor response must expose the exact JPEG dimensions and MIME type.' );
pct_inline_assert( '계정 보안 확인' === $first['alt'] && '계정 보안 확인' === get_post_meta( 200, '_wp_attachment_image_alt', true ), 'The attachment ALT must be a natural heading description without the step prefix.' );
pct_inline_assert( 'image/jpeg' === $test_inserted_attachments[200]['attachment']['post_mime_type'] && 7 === $test_inserted_attachments[200]['attachment']['post_parent'] && 7 === $test_inserted_attachments[200]['post_id'], 'The JPG must be registered as a normal child attachment of the post.' );
pct_inline_assert( 1 === count( $test_remote_requests ) && 1 === $test_unlock_calls && empty( $test_active_locks ), 'A successful request must call the provider once and release its lock.' );
pct_inline_assert( 'inline_images' === $test_lock_scopes[0], 'Inline generation must use a lock scope separate from Threads publishing.' );

$request = $test_remote_requests[0];
$payload = json_decode( $request['args']['body'], true );
pct_inline_assert( 'https://api.openai.com/v1/images/generations' === $request['url'] && 'gpt-image-2' === $payload['model'], 'Inline images must use the GPT Image 2 generation endpoint.' );
pct_inline_assert( '1200x800' === $payload['size'] && 'jpeg' === $payload['output_format'] && 85 === $payload['output_compression'] && 1 === $payload['n'], 'The provider payload must request one compressed 1200x800 JPEG.' );
pct_inline_assert( 'medium' === $payload['quality'] && 240 === $request['args']['timeout'] && 'Bearer test-openai-key' === $request['args']['headers']['Authorization'], 'The provider request must use bounded quality, timeout, and the stored API key.' );

$cached = personal_cta_inline_images_generate( 7, $title, $heading, $context );
pct_inline_assert( ! is_wp_error( $cached ) && 200 === $cached['id'] && true === $cached['reused'], 'An exact repeated section must reuse its existing attachment.' );
pct_inline_assert( 1 === count( $test_remote_requests ) && 1 === $test_unlock_calls, 'A cache hit must avoid both the provider and a needless lock.' );

$test_remote_responses[] = pct_inline_api_response( $jpeg );
$regenerated = personal_cta_inline_images_generate( 7, $title, $heading, $context, true );
pct_inline_assert( ! is_wp_error( $regenerated ) && 201 === $regenerated['id'] && false === $regenerated['reused'], 'Explicit regeneration must create a new attachment.' );
pct_inline_assert( 2 === count( $test_remote_requests ) && 2 === $test_unlock_calls && empty( $test_active_locks ), 'Explicit regeneration must make one new provider call and release its lock.' );

$test_remote_responses[] = new WP_Error( 'transport', 'offline' );
$failed = personal_cta_inline_images_generate( 7, $title, '오류 뒤 잠금 해제', '새로운 문맥' );
pct_inline_assert( is_wp_error( $failed ) && 'pct_inline_network' === $failed->get_error_code(), 'A transport failure must return the safe inline-image network error.' );
pct_inline_assert( 3 === $test_unlock_calls && empty( $test_active_locks ), 'A failed provider request must still release its lock.' );

$test_remote_responses[] = pct_inline_api_response( $jpeg );
$retry = personal_cta_inline_images_generate( 7, $title, '오류 뒤 잠금 해제', '새로운 문맥' );
pct_inline_assert( ! is_wp_error( $retry ) && 202 === $retry['id'] && 4 === $test_unlock_calls && empty( $test_active_locks ), 'The same section must be retryable immediately after a failed request.' );

$test_force_lock_error = true;
$busy_request_count    = count( $test_remote_requests );
$busy                  = personal_cta_inline_images_generate( 7, $title, '이미 생성 중인 소제목', '잠금 테스트' );
$test_force_lock_error = false;
pct_inline_assert( is_wp_error( $busy ) && 'pct_inline_busy' === $busy->get_error_code() && 409 === $busy->get_error_data()['status'], 'A concurrent generation lock must return a 409 busy error.' );
pct_inline_assert( $busy_request_count === count( $test_remote_requests ) && 4 === $test_unlock_calls, 'A rejected lock must stop before the provider and must not unlock another request.' );

$test_capabilities['upload_files'] = false;
$permission = personal_cta_inline_images_rest_permission( array( 'id' => 7 ) );
pct_inline_assert( is_wp_error( $permission ) && 'pct_inline_forbidden' === $permission->get_error_code(), 'Post-management permission without upload_files must not generate media.' );
$test_capabilities['upload_files'] = true;
pct_inline_assert( true === personal_cta_inline_images_rest_permission( array( 'id' => 7 ) ), 'An administrator who can edit the post and upload files must be allowed.' );
$test_can_manage_post = false;
pct_inline_assert( is_wp_error( personal_cta_inline_images_rest_permission( array( 'id' => 7 ) ) ), 'Upload permission alone must not bypass post-management permission.' );
$test_can_manage_post = true;

$editor = file_get_contents( dirname( __DIR__ ) . '/assets/inline-images-editor.js' );
pct_inline_assert( false !== strpos( $editor, 'level === 2 || level === 3' ) && false !== strpos( $editor, 'H2/H3' ), 'The editor must discover both H2 and H3 sections.' );
pct_inline_assert( false !== strpos( $editor, 'config.max' ) && false !== strpos( $editor, 'current.length >= maximum' ), 'The editor must enforce its configured maximum selection count.' );
pct_inline_assert( preg_match( '/for\s*\(\s*let index\s*=\s*0;\s*index\s*<\s*targets\.length/', $editor ) && false !== strpos( $editor, 'await requestImage' ) && false === strpos( $editor, 'Promise.all' ), 'Selected sections must request images sequentially.' );
pct_inline_assert( false !== strpos( $editor, "createBlock( 'core/image'" ) && false !== strpos( $editor, 'dispatch.insertBlocks' ) && false !== strpos( $editor, 'dispatch.updateBlockAttributes' ), 'Generated media must be inserted as a native core/image block and update generated blocks without discarding user styles.' );
pct_inline_assert( false !== strpos( $editor, 'personal-cta-inline-ai' ) && false !== strpos( $editor, 'X-WP-Nonce' ), 'Generated blocks need a replacement marker and authenticated REST requests.' );

foreach ( $test_attachment_files as $file ) {
	if ( is_file( $file ) ) {
		unlink( $file );
	}
}
if ( is_dir( $test_uploads_directory ) ) {
	rmdir( $test_uploads_directory );
}

echo "Inline AI image generation, caching, permissions, and Gutenberg insertion are valid.\n";
