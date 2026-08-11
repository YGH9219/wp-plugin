( function () {
	'use strict';

	const config = window.personalCtaThreads;
	if ( ! config ) {
		return;
	}

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback, { once: true } );
		} else {
			callback();
		}
	}

	ready( function () {
		const dialog = document.getElementById( 'pct-threads-dialog' );
		const trigger = document.querySelector( '#wp-admin-bar-personal-cta-threads-export a' );
		if ( ! dialog || ! trigger ) {
			return;
		}

		const text = document.getElementById( 'pct-threads-text' );
		const closeButton = dialog.querySelector( '.pct-threads-close' );
		const nativeDialog = typeof dialog.showModal === 'function';
		const status = document.getElementById( 'pct-threads-status' );
		const count = document.getElementById( 'pct-threads-count' );
		const linkNote = document.getElementById( 'pct-threads-link-note' );
		const error = document.getElementById( 'pct-threads-error' );
		const remote = document.getElementById( 'pct-threads-remote' );
		const buttons = {
			generate: document.getElementById( 'pct-threads-generate' ),
			regenerate: document.getElementById( 'pct-threads-regenerate' ),
			save: document.getElementById( 'pct-threads-save' ),
			copy: document.getElementById( 'pct-threads-copy' ),
			style: document.getElementById( 'pct-threads-style' ),
			reconcile: document.getElementById( 'pct-threads-reconcile' ),
			publish: document.getElementById( 'pct-threads-publish' ),
		};
		const labels = {
			idle: '아직 초안이 없습니다.',
			queued: 'AI 생성 작업을 기다리는 중…',
			analyzing: '원문의 사실과 후킹 소재를 분석하는 중…',
			drafting: '서로 다른 초안 3개를 만드는 중…',
			editing: '편집장이 최종 초안을 다듬는 중…',
			ready: '초안이 준비되었습니다.',
			verifying: '원문과 사실이 일치하는지 검증하는 중…',
			publishing: 'Threads 게시 결과를 확인하는 중…',
			published: 'Threads 게시가 완료되었습니다.',
			failed: '작업에 실패했습니다.',
			blocked: '사실 검증을 통과하지 못해 게시를 중단했습니다.',
			uncertain: 'Meta 응답이 불확실합니다. 재게시하지 말고 상태를 확인하세요.',
		};
		let current = { status: 'idle', text: '', link_mode: 'none', outbound_url: config.outboundUrl || '' };
		let busy = false;
		let dirty = false;
		let timer = 0;
		let polling = false;

		function isOpen() {
			return dialog.hasAttribute( 'open' );
		}

		function endpoint( suffix ) {
			return config.root.replace( /\/?$/, '/' ) + 'threads/' + encodeURIComponent( config.postId ) + ( suffix || '' );
		}

		async function api( suffix, method, body ) {
			const options = {
				method: method || 'GET',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce },
			};
			if ( body !== undefined ) {
				options.headers[ 'Content-Type' ] = 'application/json';
				options.body = JSON.stringify( body );
			}
			const response = await window.fetch( endpoint( suffix ), options );
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

		function clusters( value ) {
			if ( window.Intl && Intl.Segmenter ) {
				return Array.from( new Intl.Segmenter( 'ko', { granularity: 'grapheme' } ).segment( value ), function ( part ) {
					return part.segment;
				} );
			}
			return Array.from( value );
		}

		function threadsLength( value ) {
			const encoder = window.TextEncoder ? new TextEncoder() : null;
			const emoji = /[\u{1F000}-\u{1FAFF}\u{1F1E6}-\u{1F1FF}\u{2600}-\u{27BF}\u{20E3}]/u;
			return clusters( value ).reduce( function ( total, cluster ) {
				if ( emoji.test( cluster ) ) {
					return total + ( encoder ? encoder.encode( cluster ).length : unescape( encodeURIComponent( cluster ) ).length );
				}
				return total + Array.from( cluster ).length;
			}, 0 );
		}

		function bodyForLength() {
			let value = text.value.trim();
			if ( current.link_mode === 'raw' && current.outbound_url ) {
				value += '\n\n' + current.outbound_url;
			}
			return value;
		}

		function updateCount() {
			const length = threadsLength( bodyForLength() );
			count.textContent = length + ' / 500';
			count.classList.toggle( 'is-over', length > 500 );
			return length;
		}

		function showError( message ) {
			error.textContent = message || '';
			error.hidden = ! message;
		}

		function setBusy( value ) {
			busy = value;
			dialog.setAttribute( 'aria-busy', value ? 'true' : 'false' );
			render();
		}

		function render( state, acceptText ) {
			if ( state ) {
				current = Object.assign( {}, current, state );
				if ( ! current.last_error || [ 'failed', 'blocked', 'uncertain' ].indexOf( current.status ) === -1 ) {
					showError( '' );
				}
			}
			if ( acceptText && typeof current.text === 'string' ) {
				text.value = current.text;
				dirty = false;
			}

			const working = Boolean( current.poll );
			const published = current.status === 'published' || Boolean( current.remote_id );
			const uncertain = current.status === 'uncertain';
			const empty = ! text.value.trim();
			const tooLong = updateCount() > 500;
			text.readOnly = busy || working || published || uncertain;
			status.textContent = labels[ current.status ] || '상태: ' + current.status;
			if ( current.last_error && [ 'failed', 'blocked', 'uncertain' ].indexOf( current.status ) !== -1 ) {
				showError( current.last_error );
			}

			buttons.generate.disabled = busy || working || published || uncertain;
			buttons.regenerate.disabled = busy || working || published || uncertain;
			buttons.save.disabled = busy || working || published || uncertain || empty || tooLong;
			buttons.copy.disabled = busy || empty;
			buttons.style.disabled = busy || working || uncertain || empty || tooLong;
			buttons.publish.disabled = busy || working || published || uncertain || empty || tooLong;
			buttons.reconcile.hidden = current.status !== 'uncertain';
			buttons.reconcile.disabled = busy;
			buttons.style.textContent = current.style_pinned ? '스타일 고정 해제' : '스타일 예시로 고정';

			if ( current.link_mode === 'attachment' && current.outbound_url ) {
				linkNote.textContent = '원문 링크 카드가 게시 시 첨부됩니다.';
			} else if ( current.link_mode === 'raw' && current.outbound_url ) {
				linkNote.textContent = '원문 URL 길이가 500자 계산에 포함됩니다.';
			} else {
				linkNote.textContent = '원문 링크를 포함하지 않습니다.';
			}

			if ( current.remote_url ) {
				remote.href = current.remote_url;
				remote.hidden = false;
			} else {
				remote.hidden = true;
				remote.removeAttribute( 'href' );
			}

			if ( working && isOpen() ) {
				startPolling();
			} else {
				stopPolling();
			}
		}

		async function loadState() {
			const state = await api( '', 'GET' );
			render( state, ! dirty );
			return state;
		}

		function startPolling() {
			if ( timer || ! isOpen() ) {
				return;
			}
			timer = window.setInterval( async function () {
				if ( ! isOpen() ) {
					stopPolling();
					return;
				}
				if ( polling || busy ) {
					return;
				}
				polling = true;
				try {
					await loadState();
				} catch ( requestError ) {
					showError( requestError.message );
				} finally {
					polling = false;
				}
			}, Number( config.pollMs ) || 2500 );
		}

		function stopPolling() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = 0;
			}
		}

		async function generate( regenerate, publishAfter ) {
			showError( '' );
			setBusy( true );
			status.textContent = regenerate ? '새 초안을 요청하는 중…' : 'AI 초안을 요청하는 중…';
			try {
				const state = await api( '/generate', 'POST', { regenerate: Boolean( regenerate ), publish: Boolean( publishAfter ) } );
				dirty = false;
				render( state, true );
			} catch ( requestError ) {
				showError( requestError.message );
			} finally {
				setBusy( false );
			}
		}

		async function save() {
			showError( '' );
			setBusy( true );
			try {
				const state = await api( '/save', 'POST', { text: text.value } );
				dirty = false;
				render( state, true );
				status.textContent = '초안을 저장했습니다.';
			} catch ( requestError ) {
				showError( requestError.message );
			} finally {
				setBusy( false );
			}
		}

		async function publish() {
			showError( '' );
			setBusy( true );
			status.textContent = '원문 검증 후 Threads에 게시하는 중…';
			try {
				const state = await api( '/publish', 'POST', { text: text.value } );
				dirty = false;
				render( state, true );
			} catch ( requestError ) {
				showError( requestError.message );
				try {
					await loadState();
				} catch ( ignored ) {
					// Keep the original publishing error visible.
				}
			} finally {
				setBusy( false );
			}
		}

		async function reconcile() {
			showError( '' );
			setBusy( true );
			try {
				const state = await api( '/reconcile', 'POST', {} );
				render( state, true );
			} catch ( requestError ) {
				showError( requestError.message );
			} finally {
				setBusy( false );
			}
		}

		async function toggleStyle() {
			showError( '' );
			setBusy( true );
			try {
				const result = await api( '/style', 'POST', { text: text.value, pinned: ! current.style_pinned } );
				render( result.state, false );
				status.textContent = result.pinned ? '스타일 예시로 고정했습니다.' : '스타일 고정을 해제했습니다.';
			} catch ( requestError ) {
				showError( requestError.message );
			} finally {
				setBusy( false );
			}
		}

		async function copy() {
			showError( '' );
			let value = text.value.trim();
			if ( current.outbound_url ) {
				value += '\n\n' + current.outbound_url;
			}
			try {
				if ( ! navigator.clipboard ) {
					throw new Error( 'clipboard unavailable' );
				}
				await navigator.clipboard.writeText( value );
				status.textContent = current.outbound_url ? '본문과 링크를 복사했습니다.' : '본문을 복사했습니다.';
			} catch ( clipboardError ) {
				const fallback = document.createElement( 'textarea' );
				fallback.value = value;
				fallback.setAttribute( 'readonly', 'readonly' );
				fallback.style.position = 'fixed';
				fallback.style.opacity = '0';
				document.body.appendChild( fallback );
				fallback.select();
				const copied = document.execCommand( 'copy' );
				fallback.remove();
				if ( copied ) {
					status.textContent = current.outbound_url ? '본문과 링크를 복사했습니다.' : '본문을 복사했습니다.';
				} else {
					showError( '브라우저가 복사를 허용하지 않았습니다. 본문을 직접 선택해 복사하세요.' );
				}
			}
		}

		async function openDialog( event ) {
			event.preventDefault();
			showError( '' );
			if ( nativeDialog ) {
				dialog.showModal();
			} else {
				dialog.setAttribute( 'open', 'open' );
				dialog.setAttribute( 'role', 'dialog' );
				dialog.setAttribute( 'aria-modal', 'true' );
			}
			setBusy( true );
			try {
				const state = await loadState();
				if ( state.status === 'idle' ) {
					await generate( false, Boolean( config.oneClick ) );
				} else if ( config.oneClick && state.status === 'ready' && state.text ) {
					await publish();
				}
			} catch ( requestError ) {
				showError( requestError.message );
			} finally {
				setBusy( false );
				if ( isOpen() ) {
					text.focus();
				}
			}
		}

		function closeFallback( event ) {
			if ( nativeDialog ) {
				return;
			}
			if ( event ) {
				event.preventDefault();
			}
			dialog.removeAttribute( 'open' );
			stopPolling();
			trigger.focus();
		}

		trigger.addEventListener( 'click', openDialog );
		text.addEventListener( 'input', function () {
			dirty = true;
			current.style_pinned = false;
			showError( '' );
			render();
		} );
		buttons.generate.addEventListener( 'click', function () { generate( false, false ); } );
		buttons.regenerate.addEventListener( 'click', function () { generate( true, false ); } );
		buttons.save.addEventListener( 'click', save );
		buttons.publish.addEventListener( 'click', publish );
		buttons.reconcile.addEventListener( 'click', reconcile );
		buttons.style.addEventListener( 'click', toggleStyle );
		buttons.copy.addEventListener( 'click', copy );
		closeButton.addEventListener( 'click', closeFallback );
		dialog.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				if ( nativeDialog ) {
					event.preventDefault();
					dialog.close();
				} else {
					closeFallback( event );
				}
			} else if ( ! nativeDialog && event.key === 'Tab' ) {
				const focusable = Array.from( dialog.querySelectorAll( 'a[href], button:not([disabled]), textarea:not([disabled])' ) ).filter( function ( element ) {
					return ! element.hidden;
				} );
				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
		} );
		dialog.addEventListener( 'close', function () {
			stopPolling();
			trigger.focus();
		} );
		render();
	} );
}() );
