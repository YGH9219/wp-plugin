<?php
/** Same-category reading suggestions, appended without changing saved post content. */
defined( 'ABSPATH' ) || exit;

function personal_cta_related_posts_style() {
	if ( ! is_singular( 'post' ) || post_password_required() ) {
		return;
	}
	wp_enqueue_style( 'personal-cta-related-posts', PERSONAL_CTA_BLOCKS_URL . 'assets/related-posts.css', array(), PERSONAL_CTA_BLOCKS_VERSION );
}
add_action( 'wp_enqueue_scripts', 'personal_cta_related_posts_style' );

function personal_cta_related_posts_thumbnail( $post ) {
	$image = get_the_post_thumbnail( $post, 'medium_large', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) );
	if ( $image ) {
		return $image;
	}
	$html = new WP_HTML_Tag_Processor( $post->post_content );
	while ( $html->next_tag( 'IMG' ) ) {
		$src = $html->get_attribute( 'src' );
		$src = is_string( $src ) ? esc_url( $src ) : '';
		if ( $src ) {
			return '<img src="' . $src . '" alt="" loading="lazy" decoding="async" />';
		}
	}
	return '<span class="pct-related__placeholder" aria-hidden="true">' . esc_html__( '함께 읽어요', 'personal-cta-blocks' ) . '</span>';
}

function personal_cta_related_posts_append( $content ) {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() || post_password_required() ) {
		return $content;
	}
	$post_id    = get_the_ID();
	$categories = wp_get_post_categories( $post_id );
	if ( is_wp_error( $categories ) || ! $categories ) {
		return $content;
	}
	$related = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'has_password'        => false,
			'category__in'        => $categories,
			'post__not_in'        => array( $post_id ),
			'posts_per_page'      => 4,
			'orderby'             => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'ignore_sticky_posts' => true,
			'no_found_rows'        => true,
		)
	);
	if ( ! $related->posts ) {
		return $content;
	}
	$heading_id = wp_unique_id( 'pct-related-heading-' );
	ob_start();
	?>
	<aside class="pct-related" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" style="--pct-related-accent:<?php echo esc_attr( personal_cta_blocks_button_color() ); ?>;--pct-related-columns:<?php echo count( $related->posts ); ?>">
		<p class="pct-related__heading" id="<?php echo esc_attr( $heading_id ); ?>"><?php esc_html_e( '함께 보면 좋은 글', 'personal-cta-blocks' ); ?></p>
		<ul class="pct-related__grid" role="list">
			<?php foreach ( $related->posts as $related_post ) : ?>
				<li class="pct-related__item">
					<a class="pct-related__link" href="<?php echo esc_url( get_permalink( $related_post ) ); ?>">
						<span class="pct-related__media"><?php echo personal_cta_related_posts_thumbnail( $related_post ); ?></span>
						<span class="pct-related__title"><?php echo esc_html( wp_strip_all_tags( get_the_title( $related_post ) ) ?: __( '(제목 없음)', 'personal-cta-blocks' ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>
	<?php
	return $content . ob_get_clean();
}
add_filter( 'the_content', 'personal_cta_related_posts_append', 20 );
