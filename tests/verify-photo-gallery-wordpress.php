<?php
/** Run with wp eval-file in a disposable WordPress installation. */
$images = '';
for ( $i = 1; $i <= 3; $i++ ) {
	$images .= '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="http://localhost:8097/wp-content/plugins/personal-cta-blocks/tests/fixtures/ai-background.jpg" alt="테스트 사진 ' . $i . '"/><figcaption class="wp-element-caption">사진 ' . $i . '</figcaption></figure><!-- /wp:image -->';
}
$content = '<!-- wp:gallery {"linkTo":"none","className":"is-style-pct-swipe"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped is-style-pct-swipe">' . $images . '</figure><!-- /wp:gallery -->';
$rendered = do_blocks( $content );
echo "Gallery rendered\n";
if ( 1 !== substr_count( $rendered, 'class="pct-gallery"' ) || false === strpos( $rendered, 'tabindex="0"' ) || 2 !== substr_count( $rendered, 'aria-controls=' ) ) {
	throw new RuntimeException( 'Real WordPress gallery rendering failed.' );
}
$post = wp_insert_post( array( 'post_title' => 'Swipe gallery QA', 'post_status' => 'publish', 'post_content' => $content ), true );
if ( is_wp_error( $post ) ) { throw new RuntimeException( $post->get_error_message() ); }
echo 'QA post: ' . get_permalink( $post ) . "\n";
