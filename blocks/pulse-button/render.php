<?php
/**
 * Dynamic renderer for the pulse CTA button.
 *
 * @var array $attributes Block attributes.
 */

$text = isset( $attributes['text'] ) ? trim( wp_strip_all_tags( $attributes['text'] ) ) : '';
$url  = isset( $attributes['url'] ) ? esc_url( trim( $attributes['url'] ) ) : '';

if ( '' === $text ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'personal-cta-blocks-pulse-button',
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
	<?php if ( '' !== $url ) : ?>
		<a class="personal-cta-blocks-pulse-button__button" href="<?php echo esc_url( $url ); ?>">
	<?php else : ?>
		<span class="personal-cta-blocks-pulse-button__button is-disabled" aria-disabled="true">
	<?php endif; ?>
		<span class="personal-cta-blocks-pulse-button__text"><?php echo esc_html( $text ); ?></span>
		<span class="personal-cta-blocks-pulse-button__arrow" aria-hidden="true">→</span>
	<?php if ( '' !== $url ) : ?>
		</a>
	<?php else : ?>
		</span>
	<?php endif; ?>
</div>
