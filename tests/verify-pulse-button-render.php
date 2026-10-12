<?php
/** Isolated rendering and editor configuration checks; no external requests. */
define( 'ABSPATH', __DIR__ );
$test_options = array();
$test_inline = array();
$test_registration = true;
function add_action() {}
function add_filter() {}
function register_deactivation_hook() {}
function plugin_dir_url() { return 'https://example.test/plugins/personal-cta-blocks/'; }
function get_option( $key, $default = false ) { global $test_options; return $test_options[$key] ?? $default; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function sanitize_hex_color( $value ) { return is_string( $value ) && preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? $value : null; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_html_e( $value ) { echo esc_html( $value ); }
function esc_url( $value ) { return preg_match( '#^https?://#', $value ) ? esc_attr( $value ) : ''; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function register_block_type( $path ) {
	global $test_registration;
	return $test_registration ? (object) array( 'editor_script_handles' => array( 'test-editor' ) ) : false;
}
function wp_add_inline_script( $handle, $data, $position ) { global $test_inline; $test_inline = array( $handle, $data, $position ); }
function get_block_wrapper_attributes( $extra ) {
	return 'class="wp-block-personal-cta-blocks-pulse-button ' . esc_attr( $extra['class'] ) . '" style="' . esc_attr( $extra['style'] ) . '"';
}
require dirname( __DIR__ ) . '/personal-cta-blocks.php';
function pct_button_assert( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function pct_button_render( $attributes ) {
	ob_start();
	include dirname( __DIR__ ) . '/blocks/pulse-button/render.php';
	return ob_get_clean();
}

$legacy = array( 'text' => '공식 안내', 'url' => 'https://example.test/info', 'openInNewTab' => true, 'nofollow' => true, 'sponsored' => true );
$html = pct_button_render( $legacy );
pct_button_assert( false !== strpos( $html, '--pct-button-color:#0f766e;' ) && false !== strpos( $html, '--pct-button-text-color:#ffffff;' ), 'Old blocks must inherit teal and white without a migration.' );
pct_button_assert( false !== strpos( $html, '--pct-button-rgb:15, 118, 110;' ), 'Pulse shadows must use the selected color.' );
pct_button_assert( false !== strpos( $html, 'href="https://example.test/info" target="_blank" rel="noopener nofollow sponsored"' ), 'Existing link options must survive.' );

$test_options[PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION] = array( 'border_color' => '#2563eb', 'enabled' => false );
$html = pct_button_render( $legacy );
pct_button_assert( false !== strpos( $html, '--pct-button-color:#2563eb;' ), 'Saved site branding must survive, even when thumbnails are disabled.' );
personal_cta_blocks_register_blocks();
pct_button_assert( 'test-editor' === $test_inline[0] && 'before' === $test_inline[2] && false !== strpos( $test_inline[1], '#2563eb' ), 'The editor must receive the same saved site default before loading.' );
$test_registration = false;
personal_cta_blocks_register_blocks();

$custom = $legacy + array( 'buttonColor' => '#abc', 'textColor' => '#112233' );
$html = pct_button_render( $custom );
pct_button_assert( false !== strpos( $html, '--pct-button-color:#abc;' ) && false !== strpos( $html, '--pct-button-text-color:#112233;' ) && false !== strpos( $html, '--pct-button-rgb:170, 187, 204;' ), 'Per-button colors must override site branding, including short hex.' );

$html = pct_button_render( $legacy + array( 'buttonColor' => '#fff;background:url(https://evil.test)', 'textColor' => array( '#fff' ) ) );
pct_button_assert( false === strpos( $html, 'evil.test' ) && false !== strpos( $html, '--pct-button-color:#2563eb;' ) && false !== strpos( $html, '--pct-button-text-color:#ffffff;' ), 'Invalid colors must fall back without CSS injection or type errors.' );
$test_options[PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION] = array( 'border_color' => 'invalid' );
pct_button_assert( '#0f766e' === personal_cta_blocks_button_color(), 'Invalid stored defaults must fall back to teal.' );
$html = pct_button_render( array( 'text' => '<b>준비 & 확인</b>', 'url' => 'javascript:alert(1)' ) );
pct_button_assert( false === strpos( $html, '<a ' ) && false !== strpos( $html, 'aria-disabled="true"' ) && false !== strpos( $html, '준비 &amp; 확인' ), 'Unsafe URLs must stay disabled and text must remain escaped.' );
pct_button_assert( '' === pct_button_render( array( 'text' => ' ' ) ), 'Empty buttons must not render.' );
echo "Pulse CTA colors, defaults and existing links are valid.\n";
