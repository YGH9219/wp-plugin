<?php
/** Swipe enhancement for the native Gallery block; saved images remain editable without this plugin. */
defined( 'ABSPATH' ) || exit;

function personal_cta_gallery_register() {
	$css_version = PERSONAL_CTA_BLOCKS_VERSION . '-' . filemtime( PERSONAL_CTA_BLOCKS_DIR . '/assets/photo-gallery.css' );
	wp_register_style( 'personal-cta-gallery', PERSONAL_CTA_BLOCKS_URL . 'assets/photo-gallery.css', array(), $css_version );
	wp_register_script( 'personal-cta-gallery', PERSONAL_CTA_BLOCKS_URL . 'assets/photo-gallery.js', array(), PERSONAL_CTA_BLOCKS_VERSION, true );
	register_block_style( 'core/gallery', array( 'name' => 'pct-swipe', 'label' => __( '옆으로 넘기는 사진', 'personal-cta-blocks' ) ) );
}
add_action( 'init', 'personal_cta_gallery_register' );

function personal_cta_gallery_editor_style() {
	if ( is_admin() ) {
		wp_enqueue_style( 'personal-cta-gallery' );
	}
}
add_action( 'enqueue_block_assets', 'personal_cta_gallery_editor_style' );

function personal_cta_gallery_frontend_style() {
	$post = get_post();
	if ( is_singular() && $post && has_block( 'core/gallery', $post ) && false !== strpos( $post->post_content, 'is-style-pct-swipe' ) ) {
		wp_enqueue_style( 'personal-cta-gallery' );
	}
}
add_action( 'wp_enqueue_scripts', 'personal_cta_gallery_frontend_style' );

function personal_cta_gallery_render( $content, $block ) {
	$class = $block['attrs']['className'] ?? '';
	if ( ! is_string( $class ) || ! preg_match( '/(?:^|\s)is-style-pct-swipe(?:\s|$)/', $class ) ) {
		return $content;
	}
	$images = array_filter( $block['innerBlocks'] ?? array(), function ( $child ) { return 'core/image' === ( $child['blockName'] ?? '' ); } );
	if ( count( $images ) < 2 ) {
		return $content;
	}
	$html = new WP_HTML_Tag_Processor( $content );
	if ( ! $html->next_tag( array( 'tag_name' => 'FIGURE', 'class_name' => 'wp-block-gallery' ) ) ) {
		return $content;
	}
	$id = wp_unique_id( 'pct-gallery-' );
	$html->set_attribute( 'id', $id );
	$html->set_attribute( 'tabindex', '0' );
	$html->set_attribute( 'aria-label', __( '사진 모음. 좌우 방향키로 넘길 수 있습니다.', 'personal-cta-blocks' ) );
	wp_enqueue_style( 'personal-cta-gallery' );
	wp_enqueue_script( 'personal-cta-gallery' );
	return '<div class="pct-gallery">' . $html->get_updated_html()
		. '<div class="pct-gallery__controls" hidden><span class="pct-gallery__hint">' . esc_html__( '옆으로 넘겨보기', 'personal-cta-blocks' ) . '</span>'
		. '<button type="button" class="pct-gallery__prev" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( '이전 사진', 'personal-cta-blocks' ) . '"><span aria-hidden="true">‹</span></button>'
		. '<span class="pct-gallery__count" aria-live="polite" aria-atomic="true"></span>'
		. '<button type="button" class="pct-gallery__next" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( '다음 사진', 'personal-cta-blocks' ) . '"><span aria-hidden="true">›</span></button></div></div>';
}
add_filter( 'render_block_core/gallery', 'personal_cta_gallery_render', 20, 2 );
