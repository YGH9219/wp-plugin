( function( blocks, blockEditor, components, element, i18n ) {
	const { registerBlockType } = blocks;
	const { URLInput, useBlockProps } = blockEditor;
	const { TextControl, ToggleControl } = components;
	const { createElement: el } = element;
	const { __ } = i18n;

	registerBlockType( 'personal-cta-blocks/pulse-button', {
		edit: function( { attributes, setAttributes } ) {
			const text = attributes.text || '';
			const url = attributes.url || '';
			const openInNewTab = !! attributes.openInNewTab;
			const nofollow = !! attributes.nofollow;
			const sponsored = !! attributes.sponsored;
			const rel = [
				openInNewTab && 'noopener',
				nofollow && 'nofollow',
				sponsored && 'sponsored',
			].filter( Boolean ).join( ' ' );
			const blockProps = useBlockProps( {
				className: 'personal-cta-blocks-pulse-button',
			} );

			return el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'personal-cta-blocks-pulse-button__fields' },
					el(
						'div',
						{ className: 'personal-cta-blocks-pulse-button__primary-fields' },
						el( TextControl, {
							label: __( '버튼 문구', 'personal-cta-blocks' ),
							value: text,
							onChange: function( value ) {
								setAttributes( { text: value } );
							},
						} ),
						el(
							'div',
							{ className: 'personal-cta-blocks-pulse-button__url-field' },
							el(
								'p',
								{ className: 'personal-cta-blocks-pulse-button__field-label' },
								__( '이동할 페이지', 'personal-cta-blocks' )
							),
							el( URLInput, {
								value: url,
								placeholder: __( '페이지 검색 또는 URL 입력', 'personal-cta-blocks' ),
								onChange: function( value ) {
									setAttributes( { url: value || '' } );
								},
							} )
						)
					),
					el(
						'div',
						{ className: 'personal-cta-blocks-pulse-button__link-options' },
						el(
							'p',
							{ className: 'personal-cta-blocks-pulse-button__field-label' },
							__( '링크 설정', 'personal-cta-blocks' )
						),
						el( 'p', { className: 'personal-cta-blocks-pulse-button__link-help' }, __( '일반 링크는 모두 끔', 'personal-cta-blocks' ) ),
						el( ToggleControl, {
							label: __( '새 탭에서 열기', 'personal-cta-blocks' ),
							checked: openInNewTab,
							onChange: function( value ) {
								setAttributes( { openInNewTab: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'nofollow (신뢰하지 않는 링크)', 'personal-cta-blocks' ),
							checked: nofollow,
							onChange: function( value ) {
								setAttributes( { nofollow: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'sponsored (광고·제휴 링크)', 'personal-cta-blocks' ),
							checked: sponsored,
							onChange: function( value ) {
								setAttributes( { sponsored: value } );
							},
						} )
					)
				),
				el(
					'div',
					{ className: 'personal-cta-blocks-pulse-button__preview' },
					el(
						'a',
						{
							className: 'personal-cta-blocks-pulse-button__button',
							href: url || '#',
							target: openInNewTab ? '_blank' : undefined,
							rel: rel || undefined,
							onClick: function( event ) {
								event.preventDefault();
							},
						},
						el(
							'span',
							{ className: 'personal-cta-blocks-pulse-button__text' },
							text || __( '버튼 문구를 입력하세요', 'personal-cta-blocks' )
						),
						el(
							'span',
							{
								className: 'personal-cta-blocks-pulse-button__arrow',
								'aria-hidden': true,
							},
							openInNewTab ? '↗' : '→'
						)
					)
				)
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
