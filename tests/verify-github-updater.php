<?php
define( 'ABSPATH', __DIR__ );

$test_release = array(
	'tag_name' => 'v0.1.3',
	'html_url' => 'https://github.com/YGH9219/wp-plugin/releases/tag/v0.1.3',
	'body'     => 'Stronger CTA pulse.',
	'assets'   => array(
		array(
			'name'                 => 'source.zip',
			'browser_download_url' => 'https://github.com/YGH9219/wp-plugin/archive/v0.1.3.zip',
		),
		array(
			'name'                 => 'personal-cta-blocks-0.1.3.zip',
			'browser_download_url' => 'https://github.com/YGH9219/wp-plugin/releases/download/v0.1.3/personal-cta-blocks-0.1.3.zip',
		),
	),
);

function add_action( $hook, $callback ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function register_deactivation_hook( $file, $callback ) {}
function register_block_type( $path ) {}
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/personal-cta-blocks/'; }
function plugin_basename( $file ) { return 'personal-cta-blocks/personal-cta-blocks.php'; }
function wp_remote_get( $url, $args ) {
	global $test_release;
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => json_encode( $test_release ),
	);
}
function is_wp_error( $response ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function esc_url_raw( $url ) { return $url; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }

require dirname( __DIR__ ) . '/personal-cta-blocks.php';

$update = personal_cta_blocks_github_update(
	false,
	array( 'Version' => '0.1.2' ),
	'personal-cta-blocks/personal-cta-blocks.php'
);

if ( ! is_array( $update ) || '0.1.3' !== $update['version'] || false === strpos( $update['package'], 'personal-cta-blocks-0.1.3.zip' ) ) {
	throw new RuntimeException( 'The GitHub release update payload is invalid.' );
}

if ( false !== personal_cta_blocks_github_update( false, array( 'Version' => '0.1.2' ), 'other-plugin/other-plugin.php' ) ) {
	throw new RuntimeException( 'The updater must ignore other plugins.' );
}

$information = personal_cta_blocks_github_plugin_information( false, 'plugin_information', (object) array( 'slug' => 'personal-cta-blocks' ) );

if ( ! is_object( $information ) || '0.1.3' !== $information->version || $update['package'] !== $information->download_link ) {
	throw new RuntimeException( 'The plugin information payload is invalid.' );
}

echo "GitHub updater payload is valid.\n";
