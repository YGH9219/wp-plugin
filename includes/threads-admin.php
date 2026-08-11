<?php
/**
 * Administrator settings, REST endpoints, and the front-end export dialog.
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
 * Sanitizes the non-secret plugin settings while preserving style examples.
 *
 * @param mixed $input Submitted settings.
 * @return array<string, mixed>
 */
function personal_cta_threads_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$current  = personal_cta_threads_settings();
	$link_mode = isset( $input['link_mode'] ) ? sanitize_key( $input['link_mode'] ) : 'attachment';

	if ( ! in_array( $link_mode, array( 'attachment', 'raw' ), true ) ) {
		$link_mode = 'attachment';
	}

	$examples = isset( $current['style_examples'] ) && is_array( $current['style_examples'] )
		? array_slice( array_values( array_filter( array_map( 'strval', $current['style_examples'] ) ) ), -5 )
		: array();

	return array(
		'enabled'        => ! empty( $input['enabled'] ),
		'auto_publish'   => ! empty( $input['auto_publish'] ),
		'one_click'      => ! empty( $input['one_click'] ),
		'include_link'   => ! empty( $input['include_link'] ),
		'link_mode'      => $link_mode,
		'add_utm'        => ! empty( $input['add_utm'] ),
		'model'          => 'gpt-5.6-sol',
		'style_examples' => $examples,
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
		'Threads 내보내기',
		'Threads 내보내기',
		'manage_options',
		'personal-cta-threads',
		'personal_cta_threads_render_settings_page'
	);
}
add_action( 'admin_menu', 'personal_cta_threads_add_settings_page' );

/**
 * Returns the exact OAuth callback used for both authorization steps.
 *
 * @return string
 */
function personal_cta_threads_admin_oauth_redirect_uri() {
	return admin_url( 'admin-post.php?action=personal_cta_threads_oauth_callback' );
}

/**
 * Stores a one-time settings notice without putting error details in a URL.
 *
 * @param string $type success or error.
 * @param string $message Safe administrator message.
 * @return void
 */
function personal_cta_threads_admin_set_notice( $type, $message ) {
	set_transient(
		'personal_cta_threads_notice_' . get_current_user_id(),
		array(
			'type'    => 'success' === $type ? 'success' : 'error',
			'message' => sanitize_text_field( $message ),
		),
		MINUTE_IN_SECONDS
	);
}

/**
 * Redirects back to the plugin settings page.
 *
 * @return void
 */
function personal_cta_threads_admin_settings_redirect() {
	wp_safe_redirect( admin_url( 'options-general.php?page=personal-cta-threads' ) );
	exit;
}

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
 * Renders the administrator settings and account connection controls.
 *
 * @return void
 */
function personal_cta_threads_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = personal_cta_threads_settings();
	$account  = function_exists( 'personal_cta_threads_account' ) ? personal_cta_threads_account() : array();
	$account  = is_array( $account ) ? $account : array();
	$openai   = '' !== personal_cta_threads_config_secret( 'PERSONAL_CTA_OPENAI_API_KEY', 'OPENAI_API_KEY' );
	$app      = ! empty( $account['app_configured'] );
	$encrypted = ! empty( $account['encryption_configured'] );
	$connected = ! empty( $account['connected'] );
	$style_count = isset( $settings['style_examples'] ) && is_array( $settings['style_examples'] ) ? count( $settings['style_examples'] ) : 0;
	$notice_key = 'personal_cta_threads_notice_' . get_current_user_id();
	$notice     = get_transient( $notice_key );

	if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
		delete_transient( $notice_key );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error',
			esc_html( $notice['message'] )
		);
	}
	?>
	<div class="wrap">
		<h1>Threads 내보내기</h1>
		<p>워드프레스 글 보기 화면의 관리자바에서 AI 초안을 만들고 검토한 뒤 Threads에 게시합니다.</p>

		<h2>기능 설정</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'personal_cta_threads' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">기능 사용</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> 글 보기 화면에 내보내기 버튼 표시</label></td>
				</tr>
				<tr>
					<th scope="row">관리자바 한 번 게시</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[one_click]" value="1" <?php checked( ! empty( $settings['one_click'] ) ); ?>> 내보내기 버튼을 누르면 생성·검증 후 바로 게시</label><p class="description">끄면 초안을 먼저 확인하고 직접 게시합니다.</p></td>
				</tr>
				<tr>
					<th scope="row">새 글 자동 게시</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[auto_publish]" value="1" <?php checked( ! empty( $settings['auto_publish'] ) ); ?>> 글이 처음 발행될 때 자동 생성·검증·게시</label><p class="description">기존 글 수정에는 동작하지 않습니다.</p></td>
				</tr>
				<tr>
					<th scope="row">원문 링크</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[include_link]" value="1" <?php checked( ! empty( $settings['include_link'] ) ); ?>> 링크 포함</label><br>
						<label class="screen-reader-text" for="pct-threads-link-mode">링크 표시 방식</label><select id="pct-threads-link-mode" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[link_mode]">
							<option value="attachment" <?php selected( 'attachment', $settings['link_mode'] ); ?>>링크 카드로 첨부</option>
							<option value="raw" <?php selected( 'raw', $settings['link_mode'] ); ?>>본문 끝에 URL 표시</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">유입 추적</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[add_utm]" value="1" <?php checked( ! empty( $settings['add_utm'] ) ); ?>> 링크에 Threads UTM 추가</label></td>
				</tr>
				<tr>
					<th scope="row">AI 모델</th>
					<td><code>gpt-5.6-sol</code></td>
				</tr>
				<tr>
					<th scope="row">고정된 스타일 예시</th>
					<td><?php echo esc_html( $style_count ); ?> / 5개</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2>API 및 Threads 계정</h2>
		<table class="widefat striped" style="max-width:780px">
			<tbody>
				<tr><th scope="row">OpenAI API 키</th><td><?php personal_cta_threads_admin_status_badge( $openai ); ?></td><td><code>PERSONAL_CTA_OPENAI_API_KEY</code> 또는 <code>OPENAI_API_KEY</code></td></tr>
				<tr><th scope="row">Meta 앱</th><td><?php personal_cta_threads_admin_status_badge( $app ); ?></td><td><code>PERSONAL_CTA_THREADS_APP_ID</code> / <code>PERSONAL_CTA_THREADS_APP_SECRET</code></td></tr>
				<tr><th scope="row">토큰 암호화 키</th><td><?php personal_cta_threads_admin_status_badge( $encrypted ); ?></td><td><code>PERSONAL_CTA_THREADS_MASTER_KEY</code></td></tr>
				<tr><th scope="row">Threads 계정</th><td><?php personal_cta_threads_admin_status_badge( $connected ); ?></td><td>
					<?php if ( $connected ) : ?>
						<?php echo esc_html( ! empty( $account['username'] ) ? '@' . ltrim( $account['username'], '@' ) : (string) ( $account['user_id'] ?? '' ) ); ?>
						<?php if ( ! empty( $account['source'] ) ) : ?>(<?php echo esc_html( $account['source'] ); ?>)<?php endif; ?>
						<?php if ( ! empty( $account['expires_at'] ) ) : ?><br><small>만료: <?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $account['expires_at'] ) ); ?></small><?php endif; ?>
					<?php else : ?>연결되지 않음<?php endif; ?>
				</td></tr>
			</tbody>
		</table>
		<p class="description">비밀 값은 이 화면에 다시 표시되지 않습니다. 환경 변수 또는 <code>wp-config.php</code> 상수 사용을 권장합니다.</p>
		<p>Meta 앱의 OAuth 리디렉션 URI: <code><?php echo esc_html( personal_cta_threads_admin_oauth_redirect_uri() ); ?></code></p>

		<?php if ( $connected ) : ?>
			<?php if ( 'config' === ( $account['source'] ?? '' ) ) : ?>
				<p>이 계정은 환경 변수 또는 <code>wp-config.php</code>에서 연결되었습니다. 연결을 해제하려면 해당 사용자 ID와 액세스 토큰 설정을 제거하세요.</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
					<input type="hidden" name="action" value="personal_cta_threads_disconnect">
					<?php wp_nonce_field( 'personal_cta_threads_disconnect' ); ?>
					<?php submit_button( 'Threads 계정 연결 해제', 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		<?php else : ?>
			<h3>방법 1: Meta OAuth</h3>
			<?php if ( $app && $encrypted && function_exists( 'personal_cta_threads_oauth_url' ) ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=personal_cta_threads_connect' ), 'personal_cta_threads_connect' ) ); ?>">Meta에서 Threads 계정 연결</a></p>
			<?php else : ?>
				<p>Meta 앱 ID·앱 시크릿·마스터 키를 먼저 설정하세요.</p>
			<?php endif; ?>

			<h3>방법 2: 장기 액세스 토큰 직접 연결</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:620px">
				<input type="hidden" name="action" value="personal_cta_threads_connect_token">
				<?php wp_nonce_field( 'personal_cta_threads_connect_token' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="pct-threads-user-id">Threads 사용자 ID</label></th><td><input id="pct-threads-user-id" name="user_id" type="text" class="regular-text" inputmode="numeric" required <?php disabled( ! $encrypted ); ?>></td></tr>
					<tr><th scope="row"><label for="pct-threads-token">장기 액세스 토큰</label></th><td><input id="pct-threads-token" name="access_token" type="password" class="regular-text" autocomplete="new-password" required <?php disabled( ! $encrypted ); ?>></td></tr>
					<tr><th scope="row"><label for="pct-threads-username">사용자명 (선택)</label></th><td><input id="pct-threads-username" name="username" type="text" class="regular-text" <?php disabled( ! $encrypted ); ?>></td></tr>
				</table>
				<?php submit_button( '토큰으로 연결', 'secondary', 'submit', false, $encrypted ? array() : array( 'disabled' => 'disabled' ) ); ?>
				<?php if ( ! $encrypted ) : ?><p class="description">토큰을 데이터베이스에 암호화하려면 마스터 키를 먼저 설정하세요.</p><?php endif; ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Starts Meta OAuth after validating the administrator request.
 *
 * @return void
 */
function personal_cta_threads_admin_connect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( '권한이 없습니다.' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'personal_cta_threads_connect' );

	if ( ! function_exists( 'personal_cta_threads_oauth_url' ) ) {
		personal_cta_threads_admin_set_notice( 'error', 'OAuth 기능을 불러오지 못했습니다.' );
		personal_cta_threads_admin_settings_redirect();
	}

	$state = wp_generate_password( 48, false, false );
	set_transient(
		'personal_cta_threads_oauth_state_' . get_current_user_id(),
		hash( 'sha256', $state ),
		10 * MINUTE_IN_SECONDS
	);
	$url   = personal_cta_threads_oauth_url( personal_cta_threads_admin_oauth_redirect_uri(), $state );
	if ( is_wp_error( $url ) ) {
		delete_transient( 'personal_cta_threads_oauth_state_' . get_current_user_id() );
		personal_cta_threads_admin_set_notice( 'error', $url->get_error_message() );
		personal_cta_threads_admin_settings_redirect();
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( 'https' !== $scheme || ! in_array( $host, array( 'threads.com', 'www.threads.com' ), true ) ) {
		delete_transient( 'personal_cta_threads_oauth_state_' . get_current_user_id() );
		personal_cta_threads_admin_set_notice( 'error', '올바르지 않은 OAuth 주소입니다.' );
		personal_cta_threads_admin_settings_redirect();
	}

	wp_redirect( esc_url_raw( $url ), 302, 'Personal CTA Blocks' );
	exit;
}
add_action( 'admin_post_personal_cta_threads_connect', 'personal_cta_threads_admin_connect' );

/**
 * Completes Meta OAuth.
 *
 * @return void
 */
function personal_cta_threads_admin_oauth_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( '권한이 없습니다.' ), '', array( 'response' => 403 ) );
	}

	$state     = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$code      = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	$state_key = 'personal_cta_threads_oauth_state_' . get_current_user_id();
	$expected  = get_transient( $state_key );
	delete_transient( $state_key );
	if ( ! $state || ! is_string( $expected ) || ! hash_equals( $expected, hash( 'sha256', $state ) ) ) {
		personal_cta_threads_admin_set_notice( 'error', 'OAuth 요청 확인에 실패했습니다. 다시 연결하세요.' );
		personal_cta_threads_admin_settings_redirect();
	}
	if ( isset( $_GET['error'] ) || '' === $code || ! function_exists( 'personal_cta_threads_oauth_callback' ) ) {
		personal_cta_threads_admin_set_notice( 'error', 'Meta 계정 연결이 취소되었거나 실패했습니다.' );
		personal_cta_threads_admin_settings_redirect();
	}

	$result = personal_cta_threads_oauth_callback( $code, personal_cta_threads_admin_oauth_redirect_uri() );
	if ( is_wp_error( $result ) ) {
		personal_cta_threads_admin_set_notice( 'error', $result->get_error_message() );
	} else {
		personal_cta_threads_admin_set_notice( 'success', 'Threads 계정이 연결되었습니다.' );
	}
	personal_cta_threads_admin_settings_redirect();
}
add_action( 'admin_post_personal_cta_threads_oauth_callback', 'personal_cta_threads_admin_oauth_callback' );

/**
 * Saves a manually supplied token through the Meta module's encrypted store.
 *
 * @return void
 */
function personal_cta_threads_admin_connect_token() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( '권한이 없습니다.' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'personal_cta_threads_connect_token' );

	$user_id  = isset( $_POST['user_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_id'] ) ) ) : '';
	$token    = isset( $_POST['access_token'] ) ? trim( (string) wp_unslash( $_POST['access_token'] ) ) : '';
	$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
	if ( ! preg_match( '/^\d{1,32}$/', $user_id ) || '' === $token || strlen( $token ) > 4096 || preg_match( '/[\x00-\x1F\x7F]/', $token ) ) {
		personal_cta_threads_admin_set_notice( 'error', 'Threads 사용자 ID와 액세스 토큰을 확인하세요.' );
		personal_cta_threads_admin_settings_redirect();
	}
	if ( ! function_exists( 'personal_cta_threads_connect_token' ) ) {
		personal_cta_threads_admin_set_notice( 'error', '토큰 연결 기능을 불러오지 못했습니다.' );
		personal_cta_threads_admin_settings_redirect();
	}

	$result = personal_cta_threads_connect_token( $user_id, $token, $username, 0 );
	if ( is_wp_error( $result ) ) {
		personal_cta_threads_admin_set_notice( 'error', $result->get_error_message() );
	} else {
		personal_cta_threads_admin_set_notice( 'success', 'Threads 토큰이 암호화되어 저장되었습니다.' );
	}
	personal_cta_threads_admin_settings_redirect();
}
add_action( 'admin_post_personal_cta_threads_connect_token', 'personal_cta_threads_admin_connect_token' );

/**
 * Disconnects the stored Threads account.
 *
 * @return void
 */
function personal_cta_threads_admin_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( '권한이 없습니다.' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'personal_cta_threads_disconnect' );

	if ( function_exists( 'personal_cta_threads_disconnect' ) ) {
		personal_cta_threads_disconnect();
	}
	personal_cta_threads_admin_set_notice( 'success', '저장된 Threads 계정 연결을 해제했습니다.' );
	personal_cta_threads_admin_settings_redirect();
}
add_action( 'admin_post_personal_cta_threads_disconnect', 'personal_cta_threads_admin_disconnect' );

/**
 * REST route permission callback.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function personal_cta_threads_rest_permission( $request ) {
	$post_id = absint( $request['id'] );
	if ( ! personal_cta_threads_can_manage_post( $post_id ) ) {
		return new WP_Error( 'pct_forbidden', '이 글을 Threads로 내보낼 권한이 없습니다.', array( 'status' => 403 ) );
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
			'publish'    => array( 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ),
			'regenerate' => array( 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ),
		) ),
	) );
	register_rest_route( 'personal-cta/v1', $base . '/save', array(
		'methods'             => 'POST',
		'callback'            => 'personal_cta_threads_rest_save',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => array_merge( $id, array( 'text' => array( 'required' => true, 'type' => 'string' ) ) ),
	) );
	register_rest_route( 'personal-cta/v1', $base . '/publish', array(
		'methods'             => 'POST',
		'callback'            => 'personal_cta_threads_rest_publish',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => array_merge( $id, array( 'text' => array( 'required' => false, 'type' => 'string' ) ) ),
	) );
	register_rest_route( 'personal-cta/v1', $base . '/reconcile', array(
		'methods'             => 'POST',
		'callback'            => 'personal_cta_threads_rest_reconcile',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => $id,
	) );
	register_rest_route( 'personal-cta/v1', $base . '/style', array(
		'methods'             => 'POST',
		'callback'            => 'personal_cta_threads_rest_style',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => array_merge( $id, array(
			'text'   => array( 'required' => false, 'type' => 'string' ),
			'pinned' => array( 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ),
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
		'pct_forbidden'         => 403,
		'pct_invalid_post'      => 400,
		'pct_empty_source'      => 422,
		'pct_source_too_long'   => 422,
		'pct_empty_text'        => 422,
		'pct_text_too_long'     => 422,
		'pct_locked'            => 409,
		'pct_already_published' => 409,
		'pct_uncertain'         => 409,
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
	$state    = personal_cta_threads_state( $post_id );
	$settings = personal_cta_threads_settings();
	$text     = (string) $state['text'];
	$payload  = personal_cta_threads_payload_text( $post_id, $text );
	$examples = isset( $settings['style_examples'] ) && is_array( $settings['style_examples'] ) ? $settings['style_examples'] : array();

	$state['include_link'] = ! empty( $settings['include_link'] );
	$state['link_mode']    = $state['include_link'] ? (string) $settings['link_mode'] : 'none';
	$state['outbound_url'] = $state['include_link'] ? personal_cta_threads_outbound_url( $post_id ) : '';
	$state['style_pinned'] = '' !== $text && in_array( $text, $examples, true );
	$state['poll']         = in_array( $state['status'], array( 'queued', 'analyzing', 'drafting', 'editing', 'verifying', 'publishing' ), true );
	if ( ! is_wp_error( $payload ) ) {
		$state['length'] = (int) $payload['length'];
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
		return new WP_Error( 'pct_disabled', '설정에서 Threads 내보내기를 먼저 켜세요.', array( 'status' => 409 ) );
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return new WP_Error( 'pct_not_public', '발행된 글만 Threads로 내보낼 수 있습니다.', array( 'status' => 409 ) );
	}

	return true;
}

/**
 * Locks a post mutation and rejects changes while a background step is active.
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
	if ( in_array( $status, array( 'queued', 'analyzing', 'drafting', 'editing', 'verifying', 'publishing', 'uncertain' ), true ) ) {
		personal_cta_threads_unlock( $lock );
		$message = 'uncertain' === $status
			? '게시 여부가 불확실합니다. 먼저 게시 상태를 확인하세요.'
			: '이 글의 Threads 작업이 진행 중입니다. 완료 후 다시 시도하세요.';

		return new WP_Error( 'pct_busy', $message, array( 'status' => 409 ) );
	}

	return $lock;
}

/**
 * Queues generation and optional one-click publishing.
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
		$result = personal_cta_threads_queue(
			$post_id,
			(bool) $request->get_param( 'publish' ),
			(bool) $request->get_param( 'regenerate' )
		);
		if ( is_wp_error( $result ) ) {
			return personal_cta_threads_rest_error( $result );
		}
	} finally {
		personal_cta_threads_unlock( $lock );
	}

	return new WP_REST_Response( personal_cta_threads_admin_state( $post_id ), 202 );
}

/**
 * Saves an administrator-edited draft and invalidates its old verification.
 *
 * @param int    $post_id Post ID.
 * @param string $text Draft body, without the permalink.
 * @return array<string, mixed>|WP_Error
 */
function personal_cta_threads_save_draft( $post_id, $text ) {
	if ( personal_cta_threads_meta( $post_id, 'remote_id' ) ) {
		return new WP_Error( 'pct_already_published', '이미 Threads에 게시된 글입니다.' );
	}

	$text = trim( sanitize_textarea_field( $text ) );
	if ( '' === $text ) {
		return new WP_Error( 'pct_empty_text', 'Threads 본문을 입력하세요.' );
	}
	$payload = personal_cta_threads_payload_text( $post_id, $text );
	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	$text = (string) $payload['body'];
	if ( $text !== (string) personal_cta_threads_meta( $post_id, 'final_text' ) ) {
		personal_cta_threads_set_meta( $post_id, 'final_text', $text );
		personal_cta_threads_set_meta( $post_id, 'user_edited', $text !== (string) personal_cta_threads_meta( $post_id, 'ai_original' ) ? 1 : 0 );
		personal_cta_threads_set_meta( $post_id, 'verifier_state', 'not_run' );
		delete_post_meta( $post_id, '_pct_threads_verifier_hash' );
		delete_post_meta( $post_id, '_pct_threads_verifier_result' );
		delete_post_meta( $post_id, '_pct_threads_verifier_response_id' );
		delete_post_meta( $post_id, '_pct_threads_verifier_cache_key' );
	}
	personal_cta_threads_set_state( $post_id, 'ready', 'manual' );

	return personal_cta_threads_admin_state( $post_id );
}

/**
 * REST draft save.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function personal_cta_threads_rest_save( $request ) {
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
		$result = personal_cta_threads_save_draft( $post_id, (string) $request->get_param( 'text' ) );
	} finally {
		personal_cta_threads_unlock( $lock );
	}

	return is_wp_error( $result )
		? personal_cta_threads_rest_error( $result, 422 )
		: new WP_REST_Response( $result, 200 );
}

/**
 * Queues verification and publishing of the current saved draft.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function personal_cta_threads_rest_publish( $request ) {
	$post_id = absint( $request['id'] );
	$ready   = personal_cta_threads_rest_ready( $post_id );
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	if ( ! function_exists( 'personal_cta_threads_continue_job' ) ) {
		return new WP_Error( 'pct_unavailable', '백그라운드 게시 기능을 불러오지 못했습니다.', array( 'status' => 503 ) );
	}
	$lock = personal_cta_threads_rest_mutation_lock( $post_id );
	if ( is_wp_error( $lock ) ) {
		return $lock;
	}

	try {
		$source   = personal_cta_threads_source( $post_id );
		$fact_map = personal_cta_threads_meta( $post_id, 'fact_map', array() );
		if ( is_wp_error( $source ) ) {
			return personal_cta_threads_rest_error( $source, 422 );
		}
		$saved_hash = (string) personal_cta_threads_meta( $post_id, 'source_hash' );
		if ( '' === $saved_hash || ! hash_equals( $source['hash'], $saved_hash ) || ! is_array( $fact_map ) || empty( $fact_map ) ) {
			return new WP_Error( 'pct_generation_required', '원문에 맞는 AI 초안을 먼저 만들거나 다시 생성하세요.', array( 'status' => 409 ) );
		}

		$text = null !== $request->get_param( 'text' )
			? (string) $request->get_param( 'text' )
			: (string) personal_cta_threads_meta( $post_id, 'final_text' );
		$saved = personal_cta_threads_save_draft( $post_id, $text );
		if ( is_wp_error( $saved ) ) {
			return personal_cta_threads_rest_error( $saved, 422 );
		}

		personal_cta_threads_set_meta( $post_id, 'publish_after_generate', 1 );
		personal_cta_threads_set_meta( $post_id, 'regenerate', 0 );
		personal_cta_threads_set_state( $post_id, 'queued', 'publishing_requested' );
		$queued = personal_cta_threads_continue_job( $post_id );
		if ( is_wp_error( $queued ) ) {
			personal_cta_threads_set_state( $post_id, 'failed', 'queue', $queued->get_error_message() );
			return personal_cta_threads_rest_error( $queued, 503 );
		}
	} finally {
		personal_cta_threads_unlock( $lock );
	}

	return new WP_REST_Response( personal_cta_threads_admin_state( $post_id ), 202 );
}

/**
 * Reconciles an ambiguous Meta publish without resending it.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function personal_cta_threads_rest_reconcile( $request ) {
	$post_id = absint( $request['id'] );
	if ( ! function_exists( 'personal_cta_threads_reconcile' ) ) {
		return new WP_Error( 'pct_unavailable', 'Threads 게시 확인 기능을 불러오지 못했습니다.', array( 'status' => 503 ) );
	}
	$lock = personal_cta_threads_lock( $post_id, 120 );
	if ( is_wp_error( $lock ) ) {
		return personal_cta_threads_rest_error( $lock, 409 );
	}
	try {
		$result = personal_cta_threads_reconcile( $post_id );
	} finally {
		personal_cta_threads_unlock( $lock );
	}

	return is_wp_error( $result )
		? personal_cta_threads_rest_error( $result, 409 )
		: new WP_REST_Response( personal_cta_threads_admin_state( $post_id ), 200 );
}

/**
 * Pins or unpins the current edited draft as one of five style examples.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function personal_cta_threads_rest_style( $request ) {
	$post_id = absint( $request['id'] );
	$lock    = personal_cta_threads_rest_mutation_lock( $post_id );
	if ( is_wp_error( $lock ) ) {
		return $lock;
	}
	try {
		$text = null !== $request->get_param( 'text' )
			? trim( sanitize_textarea_field( (string) $request->get_param( 'text' ) ) )
			: trim( (string) personal_cta_threads_meta( $post_id, 'final_text' ) );
		if ( '' === $text || personal_cta_threads_length( $text ) > 500 ) {
			return new WP_Error( 'pct_invalid_style', '500자 이내의 Threads 본문만 스타일 예시로 저장할 수 있습니다.', array( 'status' => 422 ) );
		}
		$payload = personal_cta_threads_payload_text( $post_id, $text );
		if ( is_wp_error( $payload ) ) {
			return personal_cta_threads_rest_error( $payload, 422 );
		}
		$text = (string) $payload['body'];

		$settings = personal_cta_threads_settings();
		$examples = isset( $settings['style_examples'] ) && is_array( $settings['style_examples'] ) ? $settings['style_examples'] : array();
		$examples = array_values( array_filter( $examples, static function ( $example ) use ( $text ) {
			return is_string( $example ) && $example !== $text;
		} ) );
		$pinned = (bool) $request->get_param( 'pinned' );
		if ( $pinned ) {
			$examples[] = $text;
			$examples   = array_slice( $examples, -5 );
		}
		$settings['style_examples'] = $examples;
		update_option( PERSONAL_CTA_THREADS_SETTINGS_OPTION, $settings, false );
	} finally {
		personal_cta_threads_unlock( $lock );
	}

	return new WP_REST_Response( array(
		'pinned' => $pinned,
		'count'  => count( $examples ),
		'state'  => personal_cta_threads_admin_state( $post_id ),
	), 200 );
}

/**
 * Whether the current front-end request should receive the export interface.
 *
 * @return bool
 */
function personal_cta_threads_frontend_context() {
	if ( is_admin() || ! is_user_logged_in() || ! is_singular( 'post' ) ) {
		return false;
	}
	$settings = personal_cta_threads_settings();

	$post_id = (int) get_queried_object_id();

	return ! empty( $settings['enabled'] )
		&& 'publish' === get_post_status( $post_id )
		&& personal_cta_threads_can_manage_post( $post_id );
}

/**
 * Adds the administrator-bar export action only for an authorized post editor.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar.
 * @return void
 */
function personal_cta_threads_admin_bar( $admin_bar ) {
	if ( ! personal_cta_threads_frontend_context() ) {
		return;
	}
	$admin_bar->add_node( array(
		'id'    => 'personal-cta-threads-export',
		'title' => 'Threads로 내보내기',
		'href'  => '#pct-threads-dialog',
		'meta'  => array( 'title' => '이 글을 Threads로 내보내기' ),
	) );
}
add_action( 'admin_bar_menu', 'personal_cta_threads_admin_bar', 100 );

/**
 * Loads the dialog assets only for the authorized administrator.
 *
 * @return void
 */
function personal_cta_threads_enqueue_assets() {
	if ( ! personal_cta_threads_frontend_context() ) {
		return;
	}

	$post_id  = (int) get_queried_object_id();
	$settings = personal_cta_threads_settings();
	wp_enqueue_style( 'personal-cta-threads-admin', PERSONAL_CTA_BLOCKS_URL . 'assets/threads-admin.css', array(), PERSONAL_CTA_BLOCKS_VERSION );
	wp_enqueue_script( 'personal-cta-threads-admin', PERSONAL_CTA_BLOCKS_URL . 'assets/threads-admin.js', array(), PERSONAL_CTA_BLOCKS_VERSION, true );
	wp_localize_script( 'personal-cta-threads-admin', 'personalCtaThreads', array(
		'root'       => esc_url_raw( rest_url( 'personal-cta/v1/' ) ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'postId'     => $post_id,
		'oneClick'   => ! empty( $settings['one_click'] ),
		'pollMs'     => 2500,
		'outboundUrl'=> ! empty( $settings['include_link'] ) ? personal_cta_threads_outbound_url( $post_id ) : '',
	) );
}
add_action( 'wp_enqueue_scripts', 'personal_cta_threads_enqueue_assets' );

/**
 * Prints the native accessible dialog for authorized administrators only.
 *
 * @return void
 */
function personal_cta_threads_render_dialog() {
	if ( ! personal_cta_threads_frontend_context() ) {
		return;
	}
	?>
	<dialog id="pct-threads-dialog" class="pct-threads-dialog" aria-labelledby="pct-threads-title" aria-describedby="pct-threads-help">
		<form method="dialog" class="pct-threads-close-form"><button type="submit" class="pct-threads-close" aria-label="닫기">×</button></form>
		<h2 id="pct-threads-title">Threads로 내보내기</h2>
		<p id="pct-threads-help" class="pct-threads-help">AI 초안을 확인하고 필요하면 수정하세요. 원문 링크는 게시할 때 플러그인이 추가합니다.</p>
		<div id="pct-threads-status" class="pct-threads-status" role="status" aria-live="polite">상태를 불러오는 중…</div>
		<label for="pct-threads-text">Threads 본문</label>
		<textarea id="pct-threads-text" rows="12" spellcheck="true" aria-describedby="pct-threads-help pct-threads-count pct-threads-link-note pct-threads-error"></textarea>
		<div class="pct-threads-meta"><span id="pct-threads-count" aria-live="polite" aria-atomic="true">0 / 500</span><span id="pct-threads-link-note"></span></div>
		<p id="pct-threads-error" class="pct-threads-error" role="alert" hidden></p>
		<p><a id="pct-threads-remote" href="#" target="_blank" rel="noopener noreferrer" hidden>게시된 Threads 열기</a></p>
		<div class="pct-threads-actions">
			<button type="button" id="pct-threads-generate" class="pct-primary">초안 만들기</button>
			<button type="button" id="pct-threads-regenerate">다시 생성</button>
			<button type="button" id="pct-threads-save">초안 저장</button>
			<button type="button" id="pct-threads-copy">복사</button>
			<button type="button" id="pct-threads-style">스타일 예시로 고정</button>
			<button type="button" id="pct-threads-reconcile" hidden>게시 상태 확인</button>
			<button type="button" id="pct-threads-publish" class="pct-primary">Threads 게시</button>
		</div>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'personal_cta_threads_render_dialog' );
