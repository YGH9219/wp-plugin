<?php
/**
 * Dynamic renderer for the pulse CTA button.
 *
 * @var array $attributes Block attributes.
 */

$text = isset( $attributes['text'] ) ? trim( wp_strip_all_tags( $attributes['text'] ) ) : '';
$url  = isset( $attributes['url'] ) ? esc_url( trim( $attributes['url'] ) ) : '';
$open_in_new_tab = ! empty( $attributes['openInNewTab'] );
$rel = array_filter(
	array(
		$open_in_new_tab ? 'noopener' : '',
		! empty( $attributes['nofollow'] ) ? 'nofollow' : '',
		! empty( $attributes['sponsored'] ) ? 'sponsored' : '',
	)
);

if ( '' === $text ) {
	return;
}

$button_color = isset( $attributes['buttonColor'] ) && is_string( $attributes['buttonColor'] ) ? sanitize_hex_color( $attributes['buttonColor'] ) : '';
$text_color = isset( $attributes['textColor'] ) && is_string( $attributes['textColor'] ) ? sanitize_hex_color( $attributes['textColor'] ) : '';
$button_color = $button_color ? $button_color : personal_cta_blocks_button_color();
$text_color = $text_color ? $text_color : '#ffffff';
$button_rgb = implode( ', ', personal_cta_social_thumbnail_rgb( $button_color ) );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'personal-cta-blocks-pulse-button',
		'style' => '--pct-button-color:' . $button_color . ';--pct-button-text-color:' . $text_color . ';--pct-button-rgb:' . $button_rgb . ';',
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
	<?php if ( '' !== $url ) : ?>
		<a class="personal-cta-blocks-pulse-button__button" href="<?php echo esc_url( $url ); ?>"<?php echo $open_in_new_tab ? ' target="_blank"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php else : ?>
		<span class="personal-cta-blocks-pulse-button__button is-disabled" aria-disabled="true">
	<?php endif; ?>
		<span class="personal-cta-blocks-pulse-button__text"><?php echo esc_html( $text ); ?></span>
		<span class="personal-cta-blocks-pulse-button__arrow" aria-hidden="true"><?php echo $open_in_new_tab ? '↗' : '→'; ?></span>
		<?php if ( $open_in_new_tab ) : ?>
			<span class="personal-cta-blocks-pulse-button__screen-reader-text"> <?php esc_html_e( '(새 탭에서 열림)', 'personal-cta-blocks' ); ?></span>
		<?php endif; ?>
	<?php if ( '' !== $url ) : ?>
		</a>
	<?php else : ?>
		</span>
	<?php endif; ?>
</div>
