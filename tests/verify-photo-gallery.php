<?php
/** Guard and asset-scoping checks; real HTML API integration is checked in WordPress. */
define( 'ABSPATH', __DIR__ );
define( 'PERSONAL_CTA_BLOCKS_URL', 'https://example.test/plugin/' );
define( 'PERSONAL_CTA_BLOCKS_VERSION', 'test' );
$assets = array();
function add_action() {}
function add_filter() {}
function wp_enqueue_style( $handle ) { global $assets; $assets[] = $handle; }
function wp_enqueue_script( $handle ) { global $assets; $assets[] = $handle; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function wp_unique_id( $prefix ) { static $i = 0; return $prefix . ++$i; }
class WP_HTML_Tag_Processor {
	private $html;
	public function __construct( $html ) { $this->html = $html; }
	public function next_tag() { return false !== strpos( $this->html, '<figure class="wp-block-gallery' ); }
	public function set_attribute( $name, $value ) { $this->html = preg_replace( '/^<figure /', '<figure ' . $name . '="' . esc_attr( $value ) . '" ', $this->html ); }
	public function get_updated_html() { return $this->html; }
}
require dirname( __DIR__ ) . '/includes/photo-gallery.php';
function check_gallery( $condition ) { if ( ! $condition ) { throw new RuntimeException( 'Gallery check failed.' ); } }
$content = '<figure class="wp-block-gallery"><figure class="wp-block-image"><img alt="客室" src="x"></figure></figure>';
$children = array_fill( 0, 3, array( 'blockName' => 'core/image' ) );
foreach ( array( '', 'is-style-pct-swipe-other', array( 'is-style-pct-swipe' ) ) as $class ) {
	check_gallery( $content === personal_cta_gallery_render( $content, array( 'attrs' => array( 'className' => $class ), 'innerBlocks' => $children ) ) );
}
check_gallery( empty( $assets ) );
$block = array( 'attrs' => array( 'className' => 'custom is-style-pct-swipe extra' ), 'innerBlocks' => $children );
$one = $block;
$one['innerBlocks'] = array_slice( $children, 0, 1 );
check_gallery( $content === personal_cta_gallery_render( $content, $one ) && empty( $assets ) );
check_gallery( '' === personal_cta_gallery_render( '', $block ) && empty( $assets ) );
$html = personal_cta_gallery_render( $content, $block );
check_gallery( 2 === count( $assets ) );
check_gallery( false !== strpos( $html, 'tabindex="0"' ) && false !== strpos( $html, 'aria-controls="pct-gallery-1"' ) );
check_gallery( false !== strpos( $html, ' hidden>' ) && false !== strpos( $html, 'alt="客室"' ) );
check_gallery( false !== strpos( personal_cta_gallery_render( $content, $block ), 'aria-controls="pct-gallery-2"' ) );
echo "Gallery class matching, malformed input, image preservation, unique controls and conditional assets passed.\n";
