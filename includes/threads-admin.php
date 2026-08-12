<?php
/**
 * Administrator settings, REST endpoints, and the editor copy panel.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request may expose the Threads controls for a post.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function personal_cta_threads_can_manage_post( $post_id ) {
	$post = get_post( $post_id );

	return $post
		&& 'post' === $post->post_type
		&& current_user_can( 'manage_options' )
		&& current_user_can( 'edit_post', $post_id );
}

/**
 * Sanitizes the non-secret plugin settings.
 *
 * @param mixed $input Submitted settings.
 * @return array<string, mixed>
 */
function personal_cta_threads_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$api_key = isset( $input['openai_api_key'] ) && is_string( $input['openai_api_key'] ) ? trim( wp_unslash( $input['openai_api_key'] ) ) : '';

	if ( '' !== $api_key ) {
		$result = is_ssl()
			? personal_cta_threads_save_openai_key( $api_key )
			: new WP_Error( 'pct_openai_https_required', 'API 키는 HTTPS 관리자 화면에서만 저장할 수 있습니다.' );
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'personal_cta_threads', 'openai_api_key', $result->get_error_message(), 'error' );
		}
	} elseif ( ! empty( $input['delete_openai_api_key'] ) ) {
		personal_cta_threads_delete_openai_key();
	}

	return array(
		'enabled'      => ! empty( $input['enabled'] ),
		'include_link' => ! empty( $input['include_link'] ),
		'add_utm'      => ! empty( $input['add_utm'] ),
		'model'        => 'gpt-5.6-sol',
	);
}

/**
 * Registers the settings page and its option.
 *
 * @return void
 */
function personal_cta_threads_register_settings() {
	register_setting(
		'personal_cta_threads',
		PERSONAL_CTA_THREADS_SETTINGS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'personal_cta_threads_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'personal_cta_threads_register_settings' );

/**
 * Adds the page beneath Settings.
 *
 * @return void
 */
function personal_cta_threads_add_settings_page() {
	add_options_page(
		'Threads 문구',
		'Threads 문구',
		'manage_options',
		'personal-cta-threads',
		'personal_cta_threads_render_settings_page'
	);
}
add_action( 'admin_menu', 'personal_cta_threads_add_settings_page' );

/**
 * Renders a compact configured/not-configured indicator.
 *
 * @param bool $configured Status.
 * @return void
 */
function personal_cta_threads_admin_status_badge( $configured ) {
	printf(
		'<strong style="color:%1$s">%2$s</strong>',
		$configured ? '#008a20' : '#b32d2e',
		esc_html( $configured ? '설정됨' : '설정 필요' )
	);
}

/**
 * Renders the administrator settings.
 *
 * @return void
 */
function personal_cta_threads_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings      = personal_cta_threads_settings();
	$config_key    = personal_cta_threads_config_secret( 'PERSONAL_CTA_OPENAI_API_KEY', 'OPENAI_API_KEY' );
	$stored_key    = personal_cta_threads_has_saved_openai_key();
	$openai        = '' !== $config_key || $stored_key;
	?>
	<div class="wrap">
		<?php settings_errors( 'personal_cta_threads' ); ?>
		<h1>Threads 문구</h1>
		<p>발행한 글의 편집 화면 오른쪽 패널에서 AI 문구를 만들고 복사합니다. Threads 업로드는 직접 합니다.</p>

		<h2>기능 설정</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'personal_cta_threads' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">기능 사용</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> 글 편집 화면에 문구 패널 표시</label></td>
				</tr>
				<tr>
					<th scope="row">원문 링크</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[include_link]" value="1" <?php checked( ! empty( $settings['include_link'] ) ); ?>> 문구와 함께 원문 링크 복사</label><p class="description">링크는 문구 끝에 붙고 500자 제한에 함께 계산됩니다.</p></td>
				</tr>
				<tr>
					<th scope="row">유입 추적</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[add_utm]" value="1" <?php checked( ! empty( $settings['add_utm'] ) ); ?>> 링크에 Threads UTM 추가</label></td>
				</tr>
				<tr>
					<th scope="row">AI 모델</th>
					<td><code>gpt-5.6-sol</code></td>
				</tr>
			</table>
			<hr>
			<h2>OpenAI API</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">OpenAI API 키</th>
					<td>
						<?php personal_cta_threads_admin_status_badge( $openai ); ?>
						<p><label for="pct-openai-api-key">새 API 키</label><br><input id="pct-openai-api-key" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[openai_api_key]" type="password" class="regular-text" autocomplete="new-password" spellcheck="false" placeholder="<?php echo esc_attr( $stored_key ? '•••••••• (새 키 입력 시 교체)' : 'sk-...' ); ?>"></p>
						<p class="description">입력한 키는 다시 표시하지 않으며 WordPress 보안 키로 암호화해 저장합니다. 빈칸으로 저장하면 기존 키를 유지합니다.</p>
						<?php if ( $stored_key ) : ?>
							<p><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[delete_openai_api_key]" value="1"> 저장된 관리자 키 삭제</label></p>
						<?php endif; ?>
						<?php if ( '' !== $config_key ) : ?>
							<p class="description"><code>wp-config.php</code> 또는 서버 환경변수의 키가 현재 우선 적용됩니다.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( '변경 사항 저장' ); ?>
		</form>
	</div>
	<?php
}

/**
 * REST route permission callback.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function personal_cta_threads_rest_permission( $request ) {
	$post_id = absint( $request['id'] );
	if ( ! personal_cta_threads_can_manage_post( $post_id ) ) {
		return new WP_Error( 'pct_forbidden', '이 글의 Threads 문구를 관리할 권한이 없습니다.', array( 'status' => 403 ) );
	}

	return true;
}

/**
 * Registers the authenticated administrator REST surface.
 *
 * @return void
 */
function personal_cta_threads_register_rest_routes() {
	$base = '/threads/(?P<id>\d+)';
	$id   = array(
		'id' => array(
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ) {
				return absint( $value ) > 0;
			},
		),
	);

	register_rest_route( 'personal-cta/v1', $base, array(
		'methods'             => 'GET',
		'callback'            => 'personal_cta_threads_rest_state',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => $id,
	) );
	register_rest_route( 'personal-cta/v1', $base . '/generate', array(
		'methods'             => 'POST',
		'callback'            => 'personal_cta_threads_rest_generate',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => array_merge( $id, array(
			'regenerate' => array( 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ),
		) ),
	) );
}
add_action( 'rest_api_init', 'personal_cta_threads_register_rest_routes' );

/**
 * Wraps internal errors with an appropriate REST status.
 *
 * @param WP_Error $error Error.
 * @param int      $fallback Fallback status.
 * @return WP_Error
 */
function personal_cta_threads_rest_error( $error, $fallback = 500 ) {
	$map = array(
		'pct_forbidden'       => 403,
		'pct_invalid_post'    => 400,
		'pct_empty_source'    => 422,
		'pct_source_too_long' => 422,
		'pct_empty_text'      => 422,
		'pct_text_too_long'   => 422,
		'pct_locked'          => 409,
		'pct_busy'            => 409,
	);
	$code = sanitize_key( $error->get_error_code() );

	return new WP_Error(
		$code ? $code : 'pct_request_failed',
		sanitize_text_field( $error->get_error_message() ),
		array( 'status' => isset( $map[ $code ] ) ? $map[ $code ] : $fallback )
	);
}

/**
 * Adds UI-safe derived fields to the stored post state.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function personal_cta_threads_admin_state( $post_id ) {
	$state   = personal_cta_threads_state( $post_id );
	$text    = (string) $state['text'];
	$payload = '' === $text ? null : personal_cta_threads_payload_text( $post_id, $text );

	$state['copy_text'] = '';
	$state['poll']      = in_array( $state['status'], array( 'queued', 'analyzing', 'drafting', 'editing' ), true );
	if ( is_array( $payload ) ) {
		$state['copy_text'] = (string) $payload['text'];
		$state['length']    = (int) $payload['length'];
	}

	return $state;
}

/**
 * Returns the current post state.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function personal_cta_threads_rest_state( $request ) {
	return new WP_REST_Response( personal_cta_threads_admin_state( absint( $request['id'] ) ), 200 );
}

/**
 * Ensures the manual workflow is enabled and the source post is public.
 *
 * @param int $post_id Post ID.
 * @return true|WP_Error
 */
function personal_cta_threads_rest_ready( $post_id ) {
	$settings = personal_cta_threads_settings();
	if ( empty( $settings['enabled'] ) ) {
		return new WP_Error( 'pct_disabled', '설정에서 Threads 문구 기능을 먼저 켜세요.', array( 'status' => 409 ) );
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return new WP_Error( 'pct_not_public', '발행된 글에서만 Threads 문구를 만들 수 있습니다.', array( 'status' => 409 ) );
	}

	return true;
}

/**
 * Locks a post mutation and rejects changes while generation is active.
 *
 * @param int $post_id Post ID.
 * @return array<string, string|int>|WP_Error
 */
function personal_cta_threads_rest_mutation_lock( $post_id ) {
	$lock = personal_cta_threads_lock( $post_id, 120 );
	if ( is_wp_error( $lock ) ) {
		return personal_cta_threads_rest_error( $lock, 409 );
	}

	$status = (string) personal_cta_threads_meta( $post_id, 'status', 'idle' );
	if ( in_array( $status, array( 'queued', 'analyzing', 'drafting', 'editing' ), true ) ) {
		personal_cta_threads_unlock( $lock );

		return new WP_Error( 'pct_busy', '이 글의 문구 생성 작업이 진행 중입니다. 완료 후 다시 시도하세요.', array( 'status' => 409 ) );
	}

	return $lock;
}

/**
 * Queues copy generation.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function personal_cta_threads_rest_generate( $request ) {
	$post_id = absint( $request['id'] );
	$ready   = personal_cta_threads_rest_ready( $post_id );
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}

	$lock = personal_cta_threads_rest_mutation_lock( $post_id );
	if ( is_wp_error( $lock ) ) {
		return $lock;
	}
	try {
		$result = personal_cta_threads_queue( $post_id, (bool) $request->get_param( 'regenerate' ) );
		if ( is_wp_error( $result ) ) {
			return personal_cta_threads_rest_error( $result );
		}
	} finally {
		personal_cta_threads_unlock( $lock );
	}

	return new WP_REST_Response( personal_cta_threads_admin_state( $post_id ), 202 );
}

/**
 * Loads the Gutenberg document sidebar for post editors only.
 *
 * @return void
 */
function personal_cta_threads_enqueue_editor_panel() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style(
		'personal-cta-threads-editor-panel',
		PERSONAL_CTA_BLOCKS_URL . 'assets/threads-editor-panel.css',
		array(),
		PERSONAL_CTA_BLOCKS_VERSION
	);
	wp_enqueue_script(
		'personal-cta-threads-editor-panel',
		PERSONAL_CTA_BLOCKS_URL . 'assets/threads-editor-panel.js',
		array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
		PERSONAL_CTA_BLOCKS_VERSION,
		true
	);
	wp_localize_script( 'personal-cta-threads-editor-panel', 'personalCtaThreadsEditor', array(
		'root'   => esc_url_raw( rest_url( 'personal-cta/v1/' ) ),
		'nonce'  => wp_create_nonce( 'wp_rest' ),
		'pollMs' => 2500,
	) );
}
add_action( 'enqueue_block_editor_assets', 'personal_cta_threads_enqueue_editor_panel' );
