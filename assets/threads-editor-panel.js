( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! config || ! wp.plugins || ! wp.element || ! wp.components || ! wp.data ) {
		return;
	}

	const editor = wp.editor || wp.editPost;
	const registerPlugin = wp.plugins.registerPlugin;
	const PluginSidebar = editor && editor.PluginSidebar;
	const createElement = wp.element.createElement;
	const useEffect = wp.element.useEffect;
	const useState = wp.element.useState;
	const useSelect = wp.data.useSelect;
	const Button = wp.components.Button;
	const Notice = wp.components.Notice;
	const PanelBody = wp.components.PanelBody;
	const Spinner = wp.components.Spinner;
	const TextareaControl = wp.components.TextareaControl;

	if ( ! registerPlugin || ! PluginSidebar || ! PanelBody || ! useSelect ) {
		return;
	}

	const labels = {
		idle: '아직 문구가 없습니다.',
		queued: 'AI 문구 생성 작업을 기다리는 중…',
		analyzing: '원문의 사실과 소재를 분석하는 중…',
		drafting: '서로 다른 문구 초안을 만드는 중…',
		editing: '최종 문구를 다듬는 중…',
		ready: '문구가 준비되었습니다. 복사해 Threads에 직접 올리세요.',
		failed: '문구 생성에 실패했습니다.',
	};

	function endpoint( postId, suffix ) {
		return config.root.replace( /\/?$/, '/' ) + 'threads/' + encodeURIComponent( postId ) + ( suffix || '' );
	}

	async function request( postId, suffix, method, body ) {
		const options = {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce },
		};
		if ( body !== undefined ) {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify( body );
		}

		const response = await window.fetch( endpoint( postId, suffix ), options );
		let data = {};
		try {
			data = await response.json();
		} catch ( ignored ) {
			data = {};
		}
		if ( ! response.ok ) {
			throw new Error( data.message || '요청을 처리하지 못했습니다.' );
		}

		return data;
	}

	function fallbackCopy( value ) {
		const temporary = document.createElement( 'textarea' );
		temporary.value = value;
		temporary.setAttribute( 'readonly', 'readonly' );
		temporary.style.position = 'fixed';
		temporary.style.opacity = '0';
		document.body.appendChild( temporary );
		temporary.select();
		const copied = document.execCommand( 'copy' );
		temporary.remove();
		if ( ! copied ) {
			throw new Error( 'copy failed' );
		}
	}

	function ThreadsCopyPanel() {
		const postId = useSelect( function ( select ) {
			const editor = select( 'core/editor' );

			return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
		}, [] );
		const postStatus = useSelect( function ( select ) {
			const editor = select( 'core/editor' );

			return editor && editor.getEditedPostAttribute ? editor.getEditedPostAttribute( 'status' ) : '';
		}, [] );
		const [ state, setState ] = useState( { status: 'idle', text: '', copy_text: '', poll: false } );
		const [ busy, setBusy ] = useState( false );
		const [ error, setError ] = useState( '' );
		const [ message, setMessage ] = useState( '' );

		function loadState() {
			if ( ! postId ) {
				return Promise.resolve();
			}

			return request( postId, '', 'GET' ).then( function ( nextState ) {
				setState( nextState );
				return nextState;
			} ).catch( function ( requestError ) {
				setError( requestError.message );
			} );
		}

		useEffect( function () {
			setError( '' );
			setMessage( '' );
			loadState();
		}, [ postId ] );

		useEffect( function () {
			if ( ! postId || ! state.poll ) {
				return undefined;
			}

			const timer = window.setInterval( loadState, Number( config.pollMs ) || 2500 );

			return function () {
				window.clearInterval( timer );
			};
		}, [ postId, state.poll ] );

		function generate( regenerate ) {
			setError( '' );
			setMessage( regenerate ? '새 문구를 요청하는 중…' : 'AI 문구를 요청하는 중…' );
			setBusy( true );

			request( postId, '/generate', 'POST', { regenerate: Boolean( regenerate ) } ).then( function ( nextState ) {
				setState( nextState );
			} ).catch( function ( requestError ) {
				setError( requestError.message );
			} ).finally( function () {
				setBusy( false );
			} );
		}

		function copy() {
			const value = ( state.copy_text || '' ).trim();
			if ( ! value ) {
				return;
			}

			setError( '' );
			const copyPromise = navigator.clipboard && navigator.clipboard.writeText
				? navigator.clipboard.writeText( value )
				: Promise.reject( new Error( 'clipboard unavailable' ) );
			copyPromise.catch( function () {
				fallbackCopy( value );
			} ).then( function () {
				setMessage( '문구를 복사했습니다. Threads에 직접 붙여넣으세요.' );
			} ).catch( function () {
				setError( '브라우저가 복사를 허용하지 않았습니다. 문구를 직접 선택해 복사하세요.' );
			} );
		}

		const working = Boolean( state.poll );
		const published = 'publish' === postStatus;
		const copyText = state.copy_text || '';
		const status = message || labels[ state.status ] || ( '상태: ' + state.status );
		const displayedError = error || ( 'failed' === state.status ? state.last_error : '' );
		const disabled = ! postId || ! published || busy || working;

		return createElement(
			PluginSidebar,
			{
				name: 'threads-copy',
				title: 'Threads 문구',
				icon: 'admin-post',
				isPinnable: true,
				className: 'pct-threads-editor-panel',
			},
			createElement(
				PanelBody,
				{ initialOpen: true },
				! published && createElement( Notice, { status: 'warning', isDismissible: false }, '글을 먼저 발행하면 문구를 만들 수 있습니다.' ),
				createElement( 'p', { className: 'pct-threads-editor-status', role: 'status', 'aria-live': 'polite' },
					working && createElement( Spinner, null ),
					status
				),
				displayedError && createElement( Notice, { status: 'error', isDismissible: false }, displayedError ),
				createElement( TextareaControl, {
					label: '복사할 문구',
					value: copyText,
					rows: 12,
					readOnly: true,
					help: copyText ? ( String( state.length || 0 ) + ' / 500자' ) : '생성된 문구가 여기에 표시됩니다.',
				} ),
				createElement(
					'div',
					{ className: 'pct-threads-editor-actions' },
					createElement( Button, { variant: 'primary', onClick: function () { generate( false ); }, disabled: disabled, isBusy: busy }, '문구 만들기' ),
					createElement( Button, { variant: 'secondary', onClick: function () { generate( true ); }, disabled: disabled }, '다시 생성' ),
					createElement( Button, { variant: 'secondary', onClick: copy, disabled: busy || ! copyText }, '복사' )
				)
			)
		);
	}

	registerPlugin( 'personal-cta-threads-copy', {
		render: ThreadsCopyPanel,
	} );
}( window.wp, window.personalCtaThreadsEditor ) );
