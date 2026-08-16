<?php
/** Minimal contract checks for social-thumbnail settings and Rank Math routing. */

define( 'ABSPATH', __DIR__ );
define( 'MB_IN_BYTES', 1048576 );

$test_options       = array();
$test_attachments   = array( 10 => true, 20 => true );
$test_theme_mods    = array( 'custom_logo' => 10 );
$test_post_meta     = array();
$test_thumbnail_ids = array( 7 => 31 );
$test_attachment_files = array();
$test_uploads_directory = '';
$test_queried_id    = 7;
$test_is_singular   = true;

function add_action() {}
function add_filter() {}
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_hex_color( $value ) { return is_string( $value ) && preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtolower( $value ) : null; }
function wp_attachment_is_image( $id ) {
	global $test_attachments;
	return ! empty( $test_attachments[ $id ] );
}
function get_theme_mod( $key ) {
	global $test_theme_mods;
	return isset( $test_theme_mods[ $key ] ) ? $test_theme_mods[ $key ] : false;
}
function get_post_meta( $post_id, $key, $single = false ) {
	global $test_post_meta;
	return isset( $test_post_meta[ $post_id ][ $key ] ) ? $test_post_meta[ $post_id ][ $key ] : '';
}
function get_post_thumbnail_id( $post_id ) {
	global $test_thumbnail_ids;
	return isset( $test_thumbnail_ids[ $post_id ] ) ? $test_thumbnail_ids[ $post_id ] : 0;
}
function get_post_type( $post_id ) { return 7 === (int) $post_id ? 'post' : ''; }
function get_attached_file( $attachment_id ) {
	global $test_attachment_files;
	return isset( $test_attachment_files[ $attachment_id ] ) ? $test_attachment_files[ $attachment_id ] : '';
}
function wp_getimagesize( $path ) { return getimagesize( $path ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_upload_dir() {
	global $test_uploads_directory;
	return array( 'basedir' => $test_uploads_directory, 'baseurl' => 'https://example.com/uploads', 'error' => false );
}
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function wp_mkdir_p( $directory ) { return is_dir( $directory ) || mkdir( $directory, 0777, true ); }
function wp_generate_uuid4() { return uniqid( 'test-', true ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function update_post_meta( $post_id, $key, $value ) {
	global $test_post_meta;
	$test_post_meta[ $post_id ][ $key ] = $value;
	return true;
}
function delete_post_meta( $post_id, $key ) {
	global $test_post_meta;
	unset( $test_post_meta[ $post_id ][ $key ] );
	return true;
}
function esc_url_raw( $url ) { return $url; }
function wp_delete_file( $path ) { if ( is_file( $path ) ) { unlink( $path ); } }
function is_singular( $type ) {
	global $test_is_singular;
	return 'post' === $type && $test_is_singular;
}
function get_queried_object_id() {
	global $test_queried_id;
	return $test_queried_id;
}

require dirname( __DIR__ ) . '/includes/social-thumbnail.php';

function pct_social_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

$defaults = personal_cta_social_thumbnail_settings();
pct_social_assert( true === $defaults['enabled'] && 0 === $defaults['logo_id'] && '#2563eb' === $defaults['border_color'], 'Defaults must enable generation and use the site logo.' );
pct_social_assert( 10 === personal_cta_social_thumbnail_logo_id( $defaults ), 'The theme Site Logo must be the default.' );

$custom = personal_cta_social_thumbnail_sanitize_settings(
	array(
		'enabled'       => '1',
		'logo_id'       => '20',
		'logo_position' => 'top-left',
		'border_color'  => '#ABCDEF',
	)
);
pct_social_assert( 20 === $custom['logo_id'] && 'top-left' === $custom['logo_position'] && '#abcdef' === $custom['border_color'], 'Valid custom branding must survive sanitization.' );

$invalid = personal_cta_social_thumbnail_sanitize_settings(
	array(
		'logo_id'       => '999',
		'logo_position' => 'bottom',
		'border_color'  => 'blue',
	)
);
pct_social_assert( false === $invalid['enabled'] && 0 === $invalid['logo_id'] && 'top-right' === $invalid['logo_position'] && '#2563eb' === $invalid['border_color'], 'Invalid settings must fall back safely.' );

pct_social_assert( array( 819, 546 ) === personal_cta_social_thumbnail_contain( 1200, 800, 1116, 546 ), 'A 3:2 image must fit without cropping.' );
pct_social_assert( array( 307, 546 ) === personal_cta_social_thumbnail_contain( 800, 1422, 1116, 546 ), 'A portrait image must fit without cropping.' );
pct_social_assert( array( 37, 99, 235 ) === personal_cta_social_thumbnail_rgb( '#2563eb' ), 'The border color must convert to RGB.' );
pct_social_assert(
	array( 'social' => array( 1200, 630 ), '16x9' => array( 1200, 675 ), '4x3' => array( 1200, 900 ), '1x1' => array( 1200, 1200 ) ) === personal_cta_social_thumbnail_variants(),
	'The generator must keep the four required output ratios.'
);

$temp_file = tempnam( sys_get_temp_dir(), 'pct-social-' );
file_put_contents( $temp_file, 'jpg' );
$test_post_meta[7][PERSONAL_CTA_SOCIAL_THUMBNAIL_META] = array(
	'url'       => 'https://example.com/social.jpg',
	'file'      => $temp_file,
	'source_id' => 31,
	'variants'  => array(
		'social' => array( 'url' => 'https://example.com/social.jpg', 'file' => $temp_file ),
		'16x9'   => array( 'url' => 'https://example.com/16x9.jpg', 'file' => $temp_file ),
		'4x3'    => array( 'url' => 'https://example.com/4x3.jpg', 'file' => $temp_file ),
		'1x1'    => array( 'url' => 'https://example.com/1x1.jpg', 'file' => $temp_file ),
	),
);
pct_social_assert( 'https://example.com/social.jpg' === personal_cta_social_thumbnail_rank_math_facebook( 'https://example.com/original.jpg' ), 'Generated images must replace Rank Math defaults.' );
$article = personal_cta_social_thumbnail_rank_math_article( array( '@type' => 'BlogPosting', 'image' => 'https://example.com/original.jpg' ) );
pct_social_assert( array( 'https://example.com/1x1.jpg', 'https://example.com/4x3.jpg', 'https://example.com/16x9.jpg' ) === $article['image'], 'Rank Math Article schema must receive all three recommended ratios.' );

$test_post_meta[7]['rank_math_facebook_image'] = 'https://example.com/manual.jpg';
pct_social_assert( 'https://example.com/manual.jpg' === personal_cta_social_thumbnail_rank_math_facebook( 'https://example.com/manual.jpg' ), 'A manually selected Rank Math image must win.' );
unset( $test_post_meta[7]['rank_math_facebook_image'] );

$test_options[PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION] = array( 'enabled' => false );
pct_social_assert( 'https://example.com/original.jpg' === personal_cta_social_thumbnail_rank_math_facebook( 'https://example.com/original.jpg' ), 'Disabling the feature must stop using an older generated image.' );

unlink( $temp_file );

if ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' ) ) {
	global $test_attachment_files, $test_uploads_directory, $test_options, $test_post_meta;
	$source_file = tempnam( sys_get_temp_dir(), 'pct-source-' );
	$logo_file   = tempnam( sys_get_temp_dir(), 'pct-logo-' );
	$test_uploads_directory = sys_get_temp_dir() . '/pct-uploads-' . uniqid();
	$source      = imagecreatetruecolor( 600, 400 );
	$logo        = imagecreatetruecolor( 360, 80 );
	imagefill( $source, 0, 0, imagecolorallocate( $source, 32, 80, 180 ) );
	imagealphablending( $logo, false );
	imagesavealpha( $logo, true );
	imagefill( $logo, 0, 0, imagecolorallocatealpha( $logo, 0, 0, 0, 127 ) );
	imagefilledrectangle( $logo, 0, 0, 40, 79, imagecolorallocatealpha( $logo, 37, 99, 235, 0 ) );
	imagepng( $source, $source_file );
	imagepng( $logo, $logo_file );
	imagedestroy( $source );
	imagedestroy( $logo );
	$test_attachment_files = array( 31 => $source_file, 10 => $logo_file );
	$test_options[PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION] = array( 'enabled' => true, 'logo_id' => 0, 'logo_position' => 'top-right', 'border_color' => '#2563eb' );
	unset( $test_post_meta[7][PERSONAL_CTA_SOCIAL_THUMBNAIL_META] );
	$generated_url  = personal_cta_social_thumbnail_generate( 7 );
	$generated_data = $test_post_meta[7][PERSONAL_CTA_SOCIAL_THUMBNAIL_META];
	pct_social_assert( 0 === strpos( $generated_url, 'https://example.com/uploads/personal-cta-social/' ) && personal_cta_social_thumbnail_variants_ready( $generated_data ), 'Generation must atomically save all four variants.' );
	foreach ( personal_cta_social_thumbnail_variants() as $name => $dimensions ) {
		$output_size = getimagesize( $generated_data['variants'][ $name ]['file'] );
		pct_social_assert( $dimensions[0] === $output_size[0] && $dimensions[1] === $output_size[1] && IMAGETYPE_JPEG === $output_size[2], 'GD must produce every exact output ratio.' );
	}
	$output_image = imagecreatefromjpeg( $generated_data['variants']['social']['file'] );
	$transparent_area = imagecolorsforindex( $output_image, imagecolorat( $output_image, 1135, 65 ) );
	pct_social_assert( abs( 248 - $transparent_area['red'] ) < 12 && abs( 250 - $transparent_area['green'] ) < 12 && abs( 252 - $transparent_area['blue'] ) < 12, 'Transparent logo pixels must not create a white background box.' );
	imagedestroy( $output_image );
	foreach ( array_merge( array( $source_file, $logo_file ), array_column( $generated_data['variants'], 'file' ) ) as $generated_file ) {
		unlink( $generated_file );
	}
	rmdir( $test_uploads_directory . '/personal-cta-social' );
	rmdir( $test_uploads_directory );
}

echo "Social thumbnail settings and Rank Math routing are valid.\n";
