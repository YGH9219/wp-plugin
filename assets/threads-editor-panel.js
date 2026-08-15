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
		drafting: '원문을 Threads 문구로 바꾸는 중…',
		editing: '500자 제한에 맞춰 문구를 정리하는 중…',
		ready: '문구가 준비되었습니다. 복사해 Threads에 직접 올리세요.',
		failed: '문구 생성에 실패했습니다.',
	};
	const stages = {
		retry_wait: '일시 오류 후 현재 단계를 한 번 다시 시도하는 중',
		editor_retry: '6/8 최종 문구 편집을 압축해 한 번 복구',
		queued: '대기열에 등록됨',
		waiting_lock: '다른 작업이 끝나길 기다리는 중',
		composer: 'AI Composer 문구 생성',
		composer_repair: '500자 제한에 맞춰 한 번 정리',
		fact: '1/8 원자 사실 추출',
		fact_retry: '1/8 원자 사실의 보존 항목을 한 번 다시 추출',
		strategy: '2/8 콘텐츠 전략·Hook 설계',
		writer_a: '3/8 Writer A 초안 작성',
		writer_a_complete: '3/8 Writer A 초안 완료',
		writer_b: '4/8 Writer B 초안 작성',
		writer_b_complete: '4/8 Writer B 초안 완료',
		writer_c: '5/8 Writer C 초안 작성',
		writer_c_complete: '5/8 Writer C 초안 완료',
		editor: '6/8 Chief Editor 최종 문구 편집',
		editor_complete: '6/8 Chief Editor 편집 완료',
		quality: '7/8 최종 문체·전환력 심사',
		quality_complete: '7/8 최종 품질 심사 완료',
		literal_repair: '필수 숫자·조건 보정',
		repair: '500자 제한에 맞춰 정리',
		repair_complete: '최종 길이 점검',
		source_changed: '저장된 원문 변경 감지',
		verifier: '8/8 원문 기반 사실 검증',
		verified: '8/8 사실 검증 완료',
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

		let response;
		try {
			response = await window.fetch( endpoint( postId, suffix ), options );
		} catch ( ignored ) {
			throw new Error( '서버에 연결하지 못했습니다. 네트워크 연결을 확인한 뒤 다시 시도하세요.' );
		}

		let data = null;
		let parsed = true;
		try {
			data = await response.json();
		} catch ( ignored ) {
			parsed = false;
		}
		if ( ! response.ok ) {
			if ( data && typeof data.message === 'string' && data.message ) {
				throw new Error( data.message + ' (HTTP ' + response.status + ')' );
			}
			throw new Error( parsed
				? '서버 요청에 실패했습니다. HTTP ' + response.status + '.'
				: '서버가 읽을 수 없는 응답을 보냈습니다. HTTP ' + response.status + '.'
			);
		}
		if ( ! parsed || ! data || typeof data !== 'object' || Array.isArray( data ) ) {
			throw new Error( '서버 응답을 해석하지 못했습니다. HTTP ' + response.status + '.' );
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

	function diagnosticJson( value ) {
		try {
			return JSON.stringify( value, null, 2 );
		} catch ( ignored ) {
			return '';
		}
	}

	function diagnosticDecision( value, includeCopy ) {
		if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
			return '';
		}
		const lines = [
			'판정: ' + ( value.decision || '기록 없음' ),
			'이슈: ' + ( Array.isArray( value.issues ) && value.issues.length ? value.issues.join( ', ' ) : '없음' ),
		];
		if ( value.state ) {
			lines.unshift( '상태: ' + value.state );
		}
		if ( includeCopy && value.copy && value.copy.text ) {
			lines.push( '', value.copy.text );
		}
		if ( Array.isArray( value.checks ) && value.checks.length ) {
			lines.push( '', '문장별 검증:' );
			value.checks.forEach( function ( check ) {
				lines.push(
					( check.unit_id || '-' ) + ' [' + ( check.verdict || '기록 없음' ) + '] ' + ( check.claim || '' ),
					'이유: ' + ( check.reason || '기록 없음' ),
					'FACT: ' + ( Array.isArray( check.fact_ids ) && check.fact_ids.length ? check.fact_ids.join( ', ' ) : '없음' ) + ' / 원문: ' + ( Array.isArray( check.source_ids ) && check.source_ids.length ? check.source_ids.join( ', ' ) : '없음' )
				);
			} );
		}

		return lines.join( '\n' );
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
		const hasUnsavedChanges = useSelect( function ( select ) {
			const editor = select( 'core/editor' );

			return Boolean( editor && editor.isEditedPostDirty && editor.isEditedPostDirty() );
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
				setError( '' );
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
			if ( ! hasUnsavedChanges ) {
				loadState();
			}
		}, [ postId, hasUnsavedChanges ] );

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
			if ( hasUnsavedChanges ) {
				setError( '글을 먼저 저장한 뒤 저장된 원문으로 Threads 문구를 생성하세요.' );
				return;
			}
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
		const copyText = hasUnsavedChanges ? '' : ( state.copy_text || '' );
		const activityAt = Number( state.last_heartbeat || state.updated_at || 0 );
		const stale = working && activityAt && Math.floor( Date.now() / 1000 ) - activityAt >= 420;
		const status = working
			? ( busy && message ? message : ( labels[ state.status ] || ( '상태: ' + state.status ) ) )
			: ( message || labels[ state.status ] || ( '상태: ' + state.status ) );
		const displayedError = error || ( 'failed' === state.status ? state.last_error : '' );
		const disabled = ! postId || ! published || hasUnsavedChanges || busy || working;
		const diagnosticDrafts = diagnostic && Array.isArray( diagnostic.drafts ) ? diagnostic.drafts : [];
		const diagnosticEntries = [];
		if ( diagnostic && diagnostic.composer ) {
			diagnosticEntries.push( { label: 'AI Composer 원본', value: diagnostic.composer.text || '', rows: 10 } );
		}
		if ( diagnostic && diagnostic.composer_repair ) {
			diagnosticEntries.push( { label: '500자 보정 결과', value: diagnostic.composer_repair.text || '', rows: 10 } );
		}
		if ( diagnostic && diagnostic.fact_map ) {
			diagnosticEntries.push( { label: '1. FACT MAP', value: diagnosticJson( diagnostic.fact_map ), rows: 12 } );
		}
		if ( diagnostic && diagnostic.strategy ) {
			const strategySummary = {
				core_tension: diagnostic.strategy.core_tension || '',
				reader_assumption: diagnostic.strategy.reader_assumption || '',
				contrast: diagnostic.strategy.contrast || '',
				best_reveal: diagnostic.strategy.best_reveal || '',
				secondary_value: diagnostic.strategy.secondary_value || '',
				boring_fact_ids: Array.isArray( diagnostic.strategy.boring_fact_ids ) ? diagnostic.strategy.boring_fact_ids : [],
			};
			diagnosticEntries.push( { label: '2. CONTENT STRATEGY', value: diagnosticJson( strategySummary ), rows: 10 } );
			if ( Array.isArray( diagnostic.strategy.structures ) && diagnostic.strategy.structures.length ) {
				diagnosticEntries.push( { label: '3. Writer 구조', value: diagnosticJson( diagnostic.strategy.structures ), rows: 9 } );
			}
			if ( Array.isArray( diagnostic.strategy.hooks ) && diagnostic.strategy.hooks.length ) {
				diagnosticEntries.push( {
					label: '4. Hook 후보 / 선택: ' + ( Array.isArray( diagnostic.strategy.selected_hook_ids ) && diagnostic.strategy.selected_hook_ids.length ? diagnostic.strategy.selected_hook_ids.join( ', ' ) : '없음' ),
					value: diagnosticJson( diagnostic.strategy.hooks ),
					rows: 12,
				} );
			}
		}
		diagnosticDrafts.forEach( function ( draft, index ) {
			const structureId = draft.structure_id || '';
			const hookId = draft.hook_id || draft.hook_angle_id || draft.id || '';
			const details = [ structureId ? '구조 ' + structureId : '', hookId ? 'Hook ' + hookId : '' ].filter( Boolean ).join( ' / ' );
			diagnosticEntries.push( {
				label: '5. Writer 초안 ' + ( index + 1 ) + ( details ? ' (' + details + ')' : '' ),
				value: draft.text || '',
				rows: 8,
			} );
		} );
		const editorRaw = diagnostic && ( diagnostic.editor_raw || diagnostic.editor );
		if ( editorRaw ) {
			diagnosticEntries.push( { label: '6. Chief Editor 원본', value: editorRaw.text || '', rows: 8 } );
		}
		if ( diagnostic && diagnostic.final_quality ) {
			diagnosticEntries.push( { label: '7. Final Quality', value: diagnosticDecision( diagnostic.final_quality, true ), rows: 9 } );
		}
		if ( diagnostic && diagnostic.repair ) {
			diagnosticEntries.push( { label: '8. 최종 보정 결과', value: diagnostic.repair.text || '', rows: 8 } );
		}
		if ( diagnostic && diagnostic.verifier ) {
			diagnosticEntries.push( { label: '9. Fact Verifier', value: diagnosticDecision( diagnostic.verifier, false ), rows: 5 } );
		}
		if ( diagnostic && diagnostic.final ) {
			diagnosticEntries.push( { label: '10. 최종 생성 문구', value: diagnostic.final.text || '', rows: 8 } );
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
				hasUnsavedChanges && createElement( Notice, { status: 'warning', isDismissible: false }, '현재 편집 내용을 먼저 저장하세요. 문구는 저장된 글을 기준으로 만듭니다.' ),
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
				createElement( 'p', { className: 'pct-threads-editor-progress' }, 'AI Composer 원본과 500자 보정·최종 문구를 비교합니다. 원문과 API 정보는 표시하지 않습니다.' ),
				diagnosticOpen && createElement( Button, { variant: 'secondary', onClick: loadDiagnostics, disabled: diagnosticBusy }, '진단 새로고침' ),
				diagnosticBusy && createElement( Spinner, null ),
				diagnosticError && createElement( Notice, { status: 'error', isDismissible: false }, diagnosticError ),
				diagnosticOpen && diagnostic && ! diagnosticEntries.length && createElement( Notice, { status: 'info', isDismissible: false }, '아직 저장된 생성 단계가 없습니다.' ),
				diagnosticOpen && diagnosticEntries.map( function ( entry ) {
					return createElement( TextareaControl, {
						key: entry.label + entry.value.slice( 0, 24 ),
						label: entry.label,
						value: entry.value,
						rows: entry.rows || 8,
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
