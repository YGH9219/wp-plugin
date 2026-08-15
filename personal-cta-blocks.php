<?php
/**
 * Plugin Name: Personal CTA Blocks
 * Plugin URI: https://github.com/YGH9219/wp-plugin
 * Description: 펄스 CTA 버튼과 WordPress 글의 AI Threads 문구 생성을 제공합니다.
 * Version: 0.6.2
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Update URI: https://github.com/YGH9219/wp-plugin
 * Text Domain: personal-cta-blocks
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_BLOCKS_VERSION', '0.6.2' );
define( 'PERSONAL_CTA_BLOCKS_FILE', __FILE__ );
define( 'PERSONAL_CTA_BLOCKS_DIR', __DIR__ );
define( 'PERSONAL_CTA_BLOCKS_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/threads-core.php';
require_once __DIR__ . '/includes/threads-openai.php';
require_once __DIR__ . '/includes/threads-admin.php';

function personal_cta_blocks_register_blocks() {
	register_block_type( __DIR__ . '/blocks/pulse-button' );
}
add_action( 'init', 'personal_cta_blocks_register_blocks' );

/**
 * Gets the latest public GitHub release for this plugin.
 *
 * @return array<string, mixed>
 */
function personal_cta_blocks_get_latest_release() {
	$response = wp_remote_get(
		'https://api.github.com/repos/YGH9219/wp-plugin/releases/latest',
		array(
			'timeout' => 5,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Personal CTA Blocks',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return array();
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $release ) ? $release : array();
}

/**
 * Returns a usable version from a GitHub release tag.
 *
 * @param array<string, mixed> $release GitHub release data.
 * @return string
 */
function personal_cta_blocks_release_version( $release ) {
	if ( empty( $release['tag_name'] ) || ! is_string( $release['tag_name'] ) ) {
		return '';
	}

	$version = preg_replace( '/^v/i', '', $release['tag_name'] );

	return preg_match( '/^\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ? $version : '';
}

/**
 * Finds the release ZIP that preserves this plugin's directory name.
 *
 * @param array<string, mixed> $release GitHub release data.
 * @param string               $version Release version.
 * @return string
 */
function personal_cta_blocks_release_package( $release, $version ) {
	if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
		return '';
	}

	$asset_name = 'personal-cta-blocks-' . $version . '.zip';

	foreach ( $release['assets'] as $asset ) {
		if ( ! is_array( $asset ) || $asset_name !== ( $asset['name'] ?? '' ) ) {
			continue;
		}

		$url = $asset['browser_download_url'] ?? '';

		if ( ! is_string( $url ) || 0 !== strpos( $url, 'https://' ) ) {
			continue;
		}

		return esc_url_raw( $url );
	}

	return '';
}

/**
 * Supplies GitHub Release data to WordPress's native plugin updater.
 *
 * @param array|false         $update      Existing update data.
 * @param array<string, mixed> $plugin_data Plugin header data.
 * @param string              $plugin_file Plugin basename.
 * @return array|false
 */
function personal_cta_blocks_github_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
		return $update;
	}

	$release = personal_cta_blocks_get_latest_release();
	$version = personal_cta_blocks_release_version( $release );

	if ( '' === $version ) {
		return $update;
	}

	$package = personal_cta_blocks_release_package( $release, $version );

	if ( version_compare( $version, $plugin_data['Version'], '>' ) && '' === $package ) {
		return $update;
	}

	return array(
		'slug'         => 'personal-cta-blocks',
		'version'      => $version,
		'url'          => ! empty( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : 'https://github.com/YGH9219/wp-plugin',
		'package'      => $package,
		'requires'     => '6.3',
		'requires_php' => '7.4',
	);
}
add_filter( 'update_plugins_github.com', 'personal_cta_blocks_github_update', 10, 4 );

/**
 * Adds GitHub release notes to WordPress's plugin details modal.
 *
 * @param false|object $result Existing plugin information.
 * @param string       $action Requested API action.
 * @param object       $args   Request arguments.
 * @return false|object
 */
function personal_cta_blocks_github_plugin_information( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || 'personal-cta-blocks' !== ( $args->slug ?? '' ) ) {
		return $result;
	}

	$release = personal_cta_blocks_get_latest_release();
	$version = personal_cta_blocks_release_version( $release );
	$package = personal_cta_blocks_release_package( $release, $version );

	if ( '' === $version || '' === $package ) {
		return $result;
	}

	$notes = isset( $release['body'] ) && is_string( $release['body'] ) ? $release['body'] : '';

	return (object) array(
		'name'          => 'Personal CTA Blocks',
		'slug'          => 'personal-cta-blocks',
		'version'       => $version,
		'homepage'      => 'https://github.com/YGH9219/wp-plugin',
		'download_link' => $package,
		'requires'      => '6.3',
		'requires_php'  => '7.4',
		'sections'      => array(
			'description' => '<p>반응형 펄스 CTA 블록과 WordPress 글의 AI Threads 문구 생성을 제공합니다.</p>',
			'changelog'   => '' === $notes ? '<p>변경 사항이 없습니다.</p>' : nl2br( esc_html( $notes ) ),
		),
	);
}
add_filter( 'plugins_api', 'personal_cta_blocks_github_plugin_information', 20, 3 );
