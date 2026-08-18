( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.components || ! wp.data || ! wp.blocks ) {
		return;
	}

	const registerPlugin = wp.plugins.registerPlugin;
	const PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	const createElement = wp.element.createElement;
	const Fragment = wp.element.Fragment;
	const useEffect = wp.element.useEffect;
	const useState = wp.element.useState;
	const useSelect = wp.data.useSelect;
	const Button = wp.components.Button;
	const CheckboxControl = wp.components.CheckboxControl;
	const Notice = wp.components.Notice;
	const Spinner = wp.components.Spinner;
	const createBlock = wp.blocks.createBlock;
	const getBlockContent = wp.blocks.getBlockContent;
	const marker = 'personal-cta-inline-ai';
	const maximum = Math.max( 1, Number( config.max ) || 5 );

	if ( ! registerPlugin || ! PluginDocumentSettingPanel || ! CheckboxControl || ! useSelect ) {
		return;
	}

	function plainText( value ) {
		const node = document.createElement( 'div' );
		node.innerHTML = String( value || '' );

		return ( node.textContent || node.innerText || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function blockText( block ) {
		try {
			return plainText( getBlockContent( block ) );
		} catch ( ignored ) {
			return '';
		}
	}

	function clipped( value, length ) {
		return Array.from( String( value || '' ) ).slice( 0, length ).join( '' );
	}

	function headingLevel( block ) {
		return block && block.name === 'core/heading' ? Number( block.attributes.level || 2 ) : 0;
	}

	function collectSections( blockEditor ) {
		const sections = [];

		function visit( rootClientId ) {
			const siblings = blockEditor.getBlocks( rootClientId || undefined ) || [];
			siblings.forEach( function ( block, index ) {
				const level = headingLevel( block );
				const heading = level === 2 || level === 3 ? blockText( block ) : '';
				if ( heading ) {
					const context = [];
					for ( let next = index + 1; next < siblings.length; next += 1 ) {
						const nextLevel = headingLevel( siblings[ next ] );
						if ( nextLevel === 2 || nextLevel === 3 ) {
							break;
						}
						const text = blockText( siblings[ next ] );
						if ( text ) {
							context.push( text );
						}
					}
					sections.push( {
						clientId: block.clientId,
						level: level,
						heading: clipped( heading, 180 ),
						context: clipped( context.join( ' ' ), 1600 ),
					} );
				}

				const controlled = blockEditor.areInnerBlocksControlled
					&& blockEditor.areInnerBlocksControlled( block.clientId );
				if ( ! controlled && blockEditor.getBlocks( block.clientId ).length ) {
					visit( block.clientId );
				}
			} );
		}

		visit( '' );
		return sections;
	}

	function endpoint( postId ) {
		return config.root.replace( /\/?$/, '/' ) + 'inline-images/' + encodeURIComponent( postId ) + '/generate';
	}

	async function requestImage( postId, payload ) {
		let response;
		try {
			response = await window.fetch( endpoint( postId ), {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				body: JSON.stringify( payload ),
			} );
		} catch ( ignored ) {
			throw new Error( '서버에 연결하지 못했습니다. 네트워크 연결을 확인하세요.' );
		}

		let data = null;
		try {
			data = await response.json();
		} catch ( ignored ) {
			throw new Error( '서버 응답을 읽지 못했습니다. HTTP ' + response.status + '.' );
		}
		if ( ! response.ok ) {
			throw new Error( data && data.message ? data.message : '이미지 생성에 실패했습니다. HTTP ' + response.status + '.' );
		}
		if ( ! data || ! data.id || ! data.url ) {
			throw new Error( '생성된 이미지 정보를 받지 못했습니다.' );
		}

		return data;
	}

	function hasMarker( block ) {
		const className = block && block.attributes ? String( block.attributes.className || '' ) : '';

		return block && block.name === 'core/image' && ( ' ' + className + ' ' ).indexOf( ' ' + marker + ' ' ) !== -1;
	}

	function upsertImage( headingClientId, result ) {
		const select = wp.data.select( 'core/block-editor' );
		const dispatch = wp.data.dispatch( 'core/block-editor' );
		if ( ! select.getBlock( headingClientId ) ) {
			throw new Error( '편집기에서 소제목을 찾지 못했습니다.' );
		}

		const rootClientId = select.getBlockRootClientId( headingClientId ) || '';
		const index = select.getBlockIndex( headingClientId, rootClientId || undefined );
		if ( index < 0 ) {
			throw new Error( '소제목의 본문 위치를 찾지 못했습니다.' );
		}
		const siblings = select.getBlocks( rootClientId || undefined );
		const next = index >= 0 ? siblings[ index + 1 ] : null;
		const attributes = {
			id: Number( result.id ),
			url: result.url,
			alt: result.alt || '',
			sizeSlug: 'full',
			linkDestination: 'none',
			className: marker,
		};
		if ( Number( result.width ) > 0 && Number( result.height ) > 0 ) {
			attributes.width = String( Number( result.width ) );
			attributes.height = String( Number( result.height ) );
		}
		if ( hasMarker( next ) ) {
			const updates = {
				id: attributes.id,
				url: attributes.url,
				alt: attributes.alt,
			};
			if ( attributes.width && attributes.height ) {
				updates.width = attributes.width;
				updates.height = attributes.height;
			}
			if ( next.attributes.linkDestination === 'media' ) {
				updates.href = result.url;
			}
			dispatch.updateBlockAttributes( next.clientId, updates );
		} else {
			const image = createBlock( 'core/image', attributes );
			dispatch.insertBlocks( [ image ], index + 1, rootClientId || undefined );
		}

		const updatedIndex = select.getBlockIndex( headingClientId, rootClientId || undefined );
		const updatedNext = select.getBlocks( rootClientId || undefined )[ updatedIndex + 1 ];
		if ( ! hasMarker( updatedNext ) || Number( updatedNext.attributes.id ) !== Number( result.id ) ) {
			throw new Error( '잠긴 블록 영역이라 이미지를 넣지 못했습니다.' );
		}
	}

	function InlineImagesPanel() {
		const editorState = useSelect( function ( select ) {
			const editor = select( 'core/editor' );
			const blockEditor = select( 'core/block-editor' );

			return {
				postId: editor && editor.getCurrentPostId ? Number( editor.getCurrentPostId() ) : 0,
				title: editor && editor.getEditedPostAttribute ? plainText( editor.getEditedPostAttribute( 'title' ) ) : '',
				sections: blockEditor ? collectSections( blockEditor ) : [],
			};
		}, [] );
		const [ selected, setSelected ] = useState( [] );
		const [ regenerate, setRegenerate ] = useState( false );
		const [ busy, setBusy ] = useState( false );
		const [ progress, setProgress ] = useState( '' );
		const [ message, setMessage ] = useState( '' );
		const [ errors, setErrors ] = useState( [] );
		const sectionIds = editorState.sections.map( function ( section ) { return section.clientId; } ).join( '|' );

		useEffect( function () {
			const available = editorState.sections.map( function ( section ) { return section.clientId; } );
			setSelected( function ( current ) {
				return current.filter( function ( clientId ) { return available.indexOf( clientId ) !== -1; } );
			} );
		}, [ sectionIds ] );

		function toggle( clientId, checked ) {
			setMessage( '' );
			setErrors( [] );
			setSelected( function ( current ) {
				if ( ! checked ) {
					return current.filter( function ( value ) { return value !== clientId; } );
				}
				return current.indexOf( clientId ) !== -1 || current.length >= maximum
					? current
					: current.concat( clientId );
			} );
		}

		async function generate() {
			const targets = selected.map( function ( clientId ) {
				return editorState.sections.find( function ( section ) { return section.clientId === clientId; } );
			} ).filter( Boolean );
			if ( ! editorState.postId || ! editorState.title || ! targets.length ) {
				setErrors( [ ! editorState.title ? '글 제목을 먼저 입력하세요.' : '이미지를 만들 소제목을 선택하세요.' ] );
				return;
			}

			setBusy( true );
			setErrors( [] );
			setMessage( '' );
			const failures = [];
			let completed = 0;
			let reused = 0;
			for ( let index = 0; index < targets.length; index += 1 ) {
				const section = targets[ index ];
				setProgress( ( index + 1 ) + '/' + targets.length + ' · ' + section.heading );
				try {
					const result = await requestImage( editorState.postId, {
						title: editorState.title,
						heading: section.heading,
						context: section.context,
						regenerate: regenerate,
					} );
					upsertImage( section.clientId, result );
					completed += 1;
					reused += result.reused ? 1 : 0;
				} catch ( error ) {
					failures.push( section.heading + ': ' + ( error && error.message ? error.message : '알 수 없는 오류' ) );
				}
			}

			setBusy( false );
			setProgress( '' );
			setErrors( failures );
			if ( completed ) {
				setMessage( completed + '개를 본문에 넣었습니다' + ( reused ? ' (기존 이미지 ' + reused + '개 재사용)' : '' ) + '. 확인 후 글을 업데이트하세요.' );
			}
		}

		return createElement(
			PluginDocumentSettingPanel,
			{ name: 'personal-cta-inline-images', title: '본문 AI 이미지', className: 'personal-cta-inline-images' },
			createElement( 'p', null, 'H2/H3 아래에 넣을 정보성 이미지를 선택하세요. 한 번에 최대 ' + maximum + '개를 순서대로 만듭니다.' ),
			editorState.sections.length
				? editorState.sections.map( function ( section ) {
					const checked = selected.indexOf( section.clientId ) !== -1;
					return createElement( CheckboxControl, {
						key: section.clientId,
						label: 'H' + section.level + ' · ' + section.heading,
						checked: checked,
						disabled: busy || ( ! checked && selected.length >= maximum ),
						onChange: function ( value ) { toggle( section.clientId, value ); },
					} );
				} )
				: createElement( Notice, { status: 'info', isDismissible: false }, '본문에 H2 또는 H3 소제목을 먼저 추가하세요.' ),
			createElement( CheckboxControl, {
				label: '같은 내용도 새 이미지로 다시 만들기',
				help: '끄면 동일한 제목·소제목·문맥의 기존 미디어를 재사용해 API 비용을 줄입니다.',
				checked: regenerate,
				disabled: busy,
				onChange: setRegenerate,
			} ),
			createElement(
				Button,
				{
					variant: 'primary',
					disabled: busy || ! selected.length || ! editorState.title,
					onClick: generate,
				},
				busy ? createElement( Fragment, null, createElement( Spinner ), ' 생성 중' ) : '선택 ' + selected.length + '개 생성'
			),
			createElement( 'p', { style: { marginTop: '8px', marginBottom: 0 } }, '선택 ' + selected.length + '개 · 최대 API ' + selected.length + '회' ),
			progress ? createElement( 'p', { 'aria-live': 'polite' }, progress ) : null,
			message ? createElement( Notice, { status: 'success', isDismissible: false }, message ) : null,
			errors.length ? createElement( Notice, { status: 'error', isDismissible: false }, errors.map( function ( error, index ) { return createElement( 'div', { key: index + '-' + error }, error ); } ) ) : null
		);
	}

	registerPlugin( 'personal-cta-inline-images', {
		render: InlineImagesPanel,
		icon: 'format-image',
	} );
} )( window.wp, window.personalCtaInlineImages );
