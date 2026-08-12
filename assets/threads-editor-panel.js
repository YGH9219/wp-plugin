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
	const stages = {
		queued: '대기열에 등록됨',
		waiting_lock: '다른 작업이 끝나길 기다리는 중',
		fact: '1/5 원문의 숫자·조건 분석',
		writer_h1: '2/5 초안 1/3 작성',
		writer_h1_complete: '2/5 초안 1/3 완료',
		writer_h2: '3/5 초안 2/3 작성',
		writer_h2_complete: '3/5 초안 2/3 완료',
		writer_h3: '4/5 초안 3/3 작성',
		writer_h3_complete: '4/5 초안 3/3 완료',
		editor: '5/5 최종 문구 편집',
		editor_complete: '5/5 최종 문구 점검',
		literal_repair: '필수 숫자·조건 보정',
		repair: '500자 제한에 맞춰 정리',
		repair_complete: '최종 길이 점검',
		ready: '완료',
	};

	function elapsedLabel( timestamp ) {
		const seconds = Math.max( 0, Math.floor( Date.now() / 1000 ) - Number( timestamp || 0 ) );

		return seconds < 60 ? seconds + '초 전' : Math.floor( seconds / 60 ) + '분 전';
	}

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
		const [ diagnosticOpen, setDiagnosticOpen ] = useState( false );
		const [ diagnostic, setDiagnostic ] = useState( null );
		const [ diagnosticBusy, setDiagnosticBusy ] = useState( false );
		const [ diagnosticError, setDiagnosticError ] = useState( '' );

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

		function loadDiagnostics() {
			if ( ! postId ) {
				return Promise.resolve();
			}

			setDiagnosticBusy( true );
			setDiagnosticError( '' );
			return request( postId, '/diagnostics', 'GET' ).then( function ( nextDiagnostic ) {
				setDiagnostic( nextDiagnostic );
			} ).catch( function ( requestError ) {
				setDiagnosticError( requestError.message );
			} ).finally( function () {
				setDiagnosticBusy( false );
			} );
		}

		useEffect( function () {
			setError( '' );
			setMessage( '' );
			setDiagnostic( null );
			setDiagnosticError( '' );
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

		useEffect( function () {
			if ( ! postId || ! diagnosticOpen ) {
				return undefined;
			}

			loadDiagnostics();
			if ( ! state.poll ) {
				return undefined;
			}

			const timer = window.setInterval( loadDiagnostics, Number( config.pollMs ) || 2500 );

			return function () {
				window.clearInterval( timer );
			};
		}, [ postId, diagnosticOpen, state.poll ] );

		function generate( regenerate ) {
			setError( '' );
			setDiagnostic( null );
			setDiagnosticError( '' );
			setMessage( regenerate ? '새 문구를 요청하는 중…' : 'AI 문구를 요청하는 중…' );
			setBusy( true );

			request( postId, '/generate', 'POST', { regenerate: Boolean( regenerate ) } ).then( function ( nextState ) {
				setState( nextState );
			} ).catch( function ( requestError ) {
				setError( requestError.message );
			} ).finally( function () {
				setBusy( false );
				setMessage( '' );
			} );
		}

		function resume() {
			setError( '' );
			setMessage( '멈춘 작업을 다시 예약하는 중…' );
			setBusy( true );

			request( postId, '/resume', 'POST' ).then( function ( nextState ) {
				setState( nextState );
			} ).catch( function ( requestError ) {
				setError( requestError.message );
			} ).finally( function () {
				setBusy( false );
				setMessage( '' );
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
		const activityAt = Number( state.last_heartbeat || state.updated_at || 0 );
		const stale = working && activityAt && Math.floor( Date.now() / 1000 ) - activityAt >= 420;
		const status = working
			? ( busy && message ? message : ( labels[ state.status ] || ( '상태: ' + state.status ) ) )
			: ( message || labels[ state.status ] || ( '상태: ' + state.status ) );
		const displayedError = error || ( 'failed' === state.status ? state.last_error : '' );
		const disabled = ! postId || ! published || busy || working;
		const diagnosticDrafts = diagnostic && Array.isArray( diagnostic.drafts ) ? diagnostic.drafts : [];
		const diagnosticEntries = diagnosticDrafts.map( function ( draft ) {
			return {
				label: 'Writer 초안 ' + draft.id + ( draft.hook_angle_id ? ' (' + draft.hook_angle_id + ')' : '' ),
				copy: draft,
			};
		} );
		if ( diagnostic && diagnostic.editor ) {
			diagnosticEntries.push( { label: 'Chief Editor 결과' + ( diagnostic.editor.hook_angle_id ? ' (' + diagnostic.editor.hook_angle_id + ')' : '' ), copy: diagnostic.editor } );
		}
		if ( diagnostic && diagnostic.repair ) {
			diagnosticEntries.push( { label: '최종 보정 결과', copy: diagnostic.repair } );
		}
		if ( diagnostic && diagnostic.final ) {
			diagnosticEntries.push( { label: '최종 생성 문구 (보정 후)', copy: diagnostic.final } );
		}

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
				working && createElement( 'p', { className: 'pct-threads-editor-progress' }, '진행 단계: ' + ( stages[ state.stage ] || state.stage || '작업 준비' ) ),
				working && activityAt && createElement( 'p', { className: 'pct-threads-editor-progress' }, '마지막 서버 활동: ' + elapsedLabel( activityAt ) ),
				stale && createElement( Notice, { status: 'warning', isDismissible: false },
					'7분 이상 새 단계가 확인되지 않았습니다. 예약 작업 또는 AI 응답이 지연된 상태일 수 있습니다.',
					createElement( Button, { variant: 'secondary', onClick: resume, disabled: busy }, '작업 다시 예약' )
				),
				'failed' === state.status && state.stage && createElement( 'p', { className: 'pct-threads-editor-progress' }, '실패 단계: ' + ( stages[ state.stage ] || state.stage ) ),
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
			),
			createElement(
				PanelBody,
				{
					title: '생성 단계 진단 (관리자 전용)',
					initialOpen: false,
					onToggle: function ( nextOpen ) {
						setDiagnosticOpen( nextOpen );
					},
				},
				createElement( 'p', { className: 'pct-threads-editor-progress' }, 'Writer 초안과 편집장 결과를 비교합니다. 원문과 API 정보는 표시하지 않습니다.' ),
				diagnosticOpen && createElement( Button, { variant: 'secondary', onClick: loadDiagnostics, disabled: diagnosticBusy }, '진단 새로고침' ),
				diagnosticBusy && createElement( Spinner, null ),
				diagnosticError && createElement( Notice, { status: 'error', isDismissible: false }, diagnosticError ),
				diagnosticOpen && diagnostic && ! diagnosticEntries.length && createElement( Notice, { status: 'info', isDismissible: false }, '아직 저장된 생성 단계가 없습니다.' ),
				diagnosticOpen && diagnosticEntries.map( function ( entry ) {
					return createElement( TextareaControl, {
						key: entry.label,
						label: entry.label,
						value: entry.copy.text || '',
						rows: 8,
						readOnly: true,
					} );
				} )
			)
		);
	}

	registerPlugin( 'personal-cta-threads-copy', {
		render: ThreadsCopyPanel,
	} );
}( window.wp, window.personalCtaThreadsEditor ) );
