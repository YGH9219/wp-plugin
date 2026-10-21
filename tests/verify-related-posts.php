<?php
/** Fast guards and rendering checks; database selection is verified in WordPress. */
define( 'ABSPATH', __DIR__ );
define( 'PERSONAL_CTA_BLOCKS_URL', 'https://example.test/plugin/' );
define( 'PERSONAL_CTA_BLOCKS_VERSION', 'test' );
$context = array( 'admin' => false, 'feed' => false, 'singular' => true, 'loop' => true, 'main' => true, 'password' => false, 'id' => 1 );
$categories = array( 2, 3 );
$assets = $queries = $hooks = array();
$candidates = array(
	(object) array( 'ID' => 2, 'post_title' => '제주 <script>alert(1)</script> 여행 & 숙소', 'post_content' => '', 'image' => '<img src="featured.jpg" alt="" loading="lazy" decoding="async" />' ),
	(object) array( 'ID' => 3, 'post_title' => '강릉 여행', 'post_content' => '<img src="javascript:alert(1)"><img src="https://example.test/beach.jpg">', 'image' => '' ),
	(object) array( 'ID' => 4, 'post_title' => '', 'post_content' => '사진 없는 글', 'image' => '' ),
	(object) array( 'ID' => 5, 'post_title' => '부산 여행', 'post_content' => '<img src>', 'image' => '' ),
);
function add_action( $hook, $callback ) { global $hooks; $hooks[$hook] = $callback; }
function add_filter( $hook, $callback, $priority ) { global $hooks; $hooks[$hook] = array( $callback, $priority ); }
function is_admin() { global $context; return $context['admin']; }
function is_feed() { global $context; return $context['feed']; }
function is_singular( $type ) { global $context; return 'post' === $type && $context['singular']; }
function in_the_loop() { global $context; return $context['loop']; }
function is_main_query() { global $context; return $context['main']; }
function post_password_required() { global $context; return $context['password']; }
function get_the_ID() { global $context; return $context['id']; }
function get_queried_object_id() { return 1; }
function wp_get_post_categories( $id ) { global $categories; return $categories; }
function is_wp_error( $value ) { return $value instanceof Exception; }
function wp_unique_id( $prefix ) { static $n = 0; return $prefix . ++$n; }
function __( $s ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_html__( $s ) { return esc_html( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_url( $s ) { return preg_match( '~^https?://~', $s ) ? esc_attr( $s ) : ''; }
function wp_strip_all_tags( $s ) { return strip_tags( preg_replace( '~<script.*?</script>~s', '', $s ) ); }
function get_permalink( $post ) { return 'https://example.test/?p=' . $post->ID . '&view=full'; }
function get_the_title( $post ) { return $post->post_title; }
function get_the_post_thumbnail( $post, $size, $attrs ) {
	check_related( 'medium_large' === $size && '' === $attrs['alt'] && 'lazy' === $attrs['loading'], 'Responsive decorative thumbnail attributes' );
	return $post->image;
}
function personal_cta_blocks_button_color() { return '#0f766e'; }
function wp_enqueue_style( $handle, $url, $deps, $version ) { global $assets; $assets[] = $handle; }
class WP_Query {
	public $posts;
	public function __construct( $args ) { global $queries, $candidates; $queries[] = $args; $this->posts = $candidates; }
}
class WP_HTML_Tag_Processor {
	private $tags;
	private $tag = '';
	public function __construct( $html ) { preg_match_all( '~<img\b[^>]*>~i', $html, $matches ); $this->tags = $matches[0]; }
	public function next_tag( $name ) { $this->tag = array_shift( $this->tags ); return (bool) $this->tag; }
	public function get_attribute( $name ) { return preg_match( '~\bsrc="([^"]*)"~', $this->tag, $match ) ? $match[1] : true; }
}
require dirname( __DIR__ ) . '/includes/related-posts.php';
function check_related( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
check_related( array( 'personal_cta_related_posts_append', 20 ) === $hooks['the_content'], 'Content filter registration' );
$content = '<p>원래 본문</p>';
foreach ( array( 'admin', 'feed', 'singular', 'loop', 'main', 'password', 'id' ) as $guard ) {
	$original = $context[$guard];
	$context[$guard] = 'id' === $guard ? 99 : ! $original;
	check_related( $content === personal_cta_related_posts_append( $content ), 'Guard: ' . $guard );
	$context[$guard] = $original;
}
check_related( ! $queries, 'Excluded contexts must not query related posts' );
foreach ( array( array(), new RuntimeException( 'Taxonomy error' ) ) as $empty ) {
	$categories = $empty;
	check_related( $content === personal_cta_related_posts_append( $content ) && ! $queries, 'Missing or invalid categories' );
}
$categories = array( 2, 3 );
$html = personal_cta_related_posts_append( $content );
$args = $queries[0];
check_related( array( 2, 3 ) === $args['category__in'] && array( 1 ) === $args['post__not_in'], 'Only shared categories; exclude current post' );
check_related( 'publish' === $args['post_status'] && 'post' === $args['post_type'] && false === $args['has_password'], 'Only public unprotected posts' );
check_related( 4 === $args['posts_per_page'] && $args['ignore_sticky_posts'] && $args['no_found_rows'], 'Bounded query without pagination or sticky overrides' );
check_related( array( 'date' => 'DESC', 'ID' => 'DESC' ) === $args['orderby'], 'Stable latest-first order' );
check_related( 0 === strpos( $html, $content ) && 4 === substr_count( $html, '<li ' ), 'Preserve content and render four cards' );
check_related( false === strpos( $html, '<script' ) && false === strpos( $html, 'javascript:' ), 'Reject unsafe image URLs and strip title markup' );
check_related( false !== strpos( $html, 'beach.jpg' ) && false !== strpos( $html, 'featured.jpg' ) && false !== strpos( $html, '함께 읽어요' ), 'Featured, body image and no-image fallback' );
check_related( false !== strpos( $html, '(제목 없음)' ) && false !== strpos( $html, '&amp;view=full' ), 'Untitled fallback and escaped links' );
check_related( false !== strpos( $html, 'aria-labelledby="pct-related-heading-1"' ) && false !== strpos( personal_cta_related_posts_append( $content ), 'id="pct-related-heading-2"' ), 'Unique accessible headings' );
check_related( 1 === get_the_ID(), 'Rendering must preserve the main post' );
$candidates = array_slice( $candidates, 0, 3 );
check_related( 3 === substr_count( personal_cta_related_posts_append( $content ), '<li ' ), 'Three available posts' );
$candidates = array();
check_related( $content === personal_cta_related_posts_append( $content ), 'Hide empty results' );
$context['singular'] = false;
personal_cta_related_posts_style();
$context['singular'] = true;
$context['password'] = true;
personal_cta_related_posts_style();
check_related( ! $assets, 'No CSS on archives or password-protected posts' );
$context['password'] = false;
personal_cta_related_posts_style();
check_related( array( 'personal-cta-related-posts' ) === $assets, 'Scoped frontend CSS' );
echo "Related posts guards, category query, limits, escaping, image fallbacks, accessibility and asset scope passed.\n";
