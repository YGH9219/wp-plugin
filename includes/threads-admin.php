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
	$input   = is_array( $input ) ? $input : array();
	$current = personal_cta_threads_settings();
	$api_key  = isset( $input['openai_api_key'] ) && is_string( $input['openai_api_key'] ) ? trim( wp_unslash( $input['openai_api_key'] ) ) : '';

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

	$style_examples = isset( $current['style_examples'] ) && is_array( $current['style_examples'] ) ? $current['style_examples'] : array();
	if ( isset( $input['style_examples'] ) && is_array( $input['style_examples'] ) ) {
		$style_examples = array();
		foreach ( array_slice( $input['style_examples'], 0, 5 ) as $example ) {
			if ( ! is_scalar( $example ) ) {
				continue;
			}
			$example = trim( sanitize_textarea_field( wp_unslash( (string) $example ) ) );
			if ( '' === $example ) {
				continue;
			}
			if ( personal_cta_threads_length( $example ) > 500 ) {
				add_settings_error( 'personal_cta_threads', 'style_examples', '스타일 예시는 각각 500자 이하여야 합니다.', 'error' );
				continue;
			}
			if ( ! in_array( $example, $style_examples, true ) ) {
				$style_examples[] = $example;
			}
		}
	}

	return array(
		'enabled'        => ! empty( $input['enabled'] ),
		'include_link'   => ! empty( $input['include_link'] ),
		'add_utm'        => ! empty( $input['add_utm'] ),
		'model'          => 'gpt-5.6-sol',
		'style_examples' => $style_examples,
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
				<tr>
					<th scope="row">스타일 예시</th>
					<td>
						<?php $style_examples = isset( $settings['style_examples'] ) && is_array( $settings['style_examples'] ) ? array_values( $settings['style_examples'] ) : array(); ?>
						<?php for ( $index = 0; $index < 5; $index++ ) : ?>
							<p><textarea name="<?php echo esc_attr( PERSONAL_CTA_THREADS_SETTINGS_OPTION ); ?>[style_examples][]" rows="4" class="large-text" maxlength="500" placeholder="잘 나온 Threads 본문 예시 <?php echo esc_attr( $index + 1 ); ?> (URL 제외)"><?php echo esc_textarea( isset( $style_examples[ $index ] ) ? $style_examples[ $index ] : '' ); ?></textarea></p>
						<?php endfor; ?>
						<p class="description">원하는 말투와 구성의 합격 문구 3~5개를 넣으세요. 서로 다른 주제의 본문만 넣고 URL은 빼는 것이 좋습니다. 비어 있는 칸은 무시됩니다.</p>
					</td>
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
	register_rest_route( 'personal-cta/v1', $base . '/diagnostics', array(
		'methods'             => 'GET',
		'callback'            => 'personal_cta_threads_rest_diagnostics',
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
	register_rest_route( 'personal-cta/v1', $base . '/resume', array(
		'methods'             => 'POST',
		'callback'            => 'personal_cta_threads_rest_resume',
		'permission_callback' => 'personal_cta_threads_rest_permission',
		'args'                => $id,
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
		'pct_not_pending'     => 409,
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
	$ready   = 'ready' === $state['status'];
	if ( $ready && function_exists( 'personal_cta_threads_source' ) ) {
		$source     = personal_cta_threads_source( $post_id );
		$saved_hash = (string) personal_cta_threads_meta( $post_id, 'source_hash' );
		if ( is_wp_error( $source ) || '' === $saved_hash || ! hash_equals( $saved_hash, $source['hash'] ) ) {
			personal_cta_threads_set_meta( $post_id, 'verifier_state', 'not_run' );
			personal_cta_threads_set_state( $post_id, 'failed', 'source_changed', '원문이 변경됐습니다. 저장한 뒤 Threads 문구를 다시 생성하세요.' );
			$state = personal_cta_threads_state( $post_id );
			$ready = false;
		}
	}
	$text    = $ready ? (string) $state['text'] : '';
	$payload = '' === $text ? null : personal_cta_threads_payload_text( $post_id, $text );

	if ( ! $ready ) {
		$state['text']        = '';
		$state['ai_original'] = '';
		$state['length']      = 0;
	}
	$state['copy_text'] = '';
	$state['poll']      = personal_cta_threads_is_working( $state['status'] );
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
 * Reduces a stored model response to the safe fields needed for admin diagnostics.
 *
 * @param mixed $copy Stored model response.
 * @return array<string, string>|null
 */
function personal_cta_threads_diagnostic_copy( $copy ) {
	$text = is_array( $copy ) && isset( $copy['text'] ) && is_string( $copy['text'] ) ? trim( $copy['text'] ) : '';
	if ( '' === $text ) {
		return null;
	}
	$hook_id = '';
	if ( isset( $copy['hook_id'] ) && is_string( $copy['hook_id'] ) ) {
		$hook_id = $copy['hook_id'];
	} elseif ( isset( $copy['hook_angle_id'] ) && is_string( $copy['hook_angle_id'] ) ) {
		$hook_id = $copy['hook_angle_id'];
	}

	return array(
		'text'          => $text,
		'hook_angle_id' => is_array( $copy ) && isset( $copy['hook_angle_id'] ) && is_string( $copy['hook_angle_id'] ) ? $copy['hook_angle_id'] : '',
		'structure_id'  => is_array( $copy ) && isset( $copy['structure_id'] ) && is_string( $copy['structure_id'] ) ? $copy['structure_id'] : '',
		'hook_id'       => $hook_id,
	);
}

/**
 * Reduces a list to non-empty scalar strings for diagnostics.
 *
 * @param mixed $items Stored list.
 * @return array<int, string>
 */
function personal_cta_threads_diagnostic_string_list( $items ) {
	$output = array();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
			$output[] = trim( (string) $item );
		}
	}

	return array_values( array_unique( $output ) );
}

/**
 * Returns only the safe strategy fields needed to inspect a generation.
 *
 * @param mixed $strategy Stored strategy.
 * @return array<string, mixed>|null
 */
function personal_cta_threads_diagnostic_strategy( $strategy ) {
	if ( ! is_array( $strategy ) || empty( $strategy ) ) {
		return null;
	}

	$output = array();
	foreach ( array( 'core_tension', 'reader_assumption', 'contrast', 'best_reveal', 'secondary_value' ) as $key ) {
		$value = isset( $strategy[ $key ] ) ? $strategy[ $key ] : '';
		$output[ $key ] = array(
			'text'     => is_array( $value ) && isset( $value['text'] ) && is_scalar( $value['text'] )
				? trim( (string) $value['text'] )
				: ( is_scalar( $value ) ? trim( (string) $value ) : '' ),
			'fact_ids' => personal_cta_threads_diagnostic_string_list( is_array( $value ) && isset( $value['fact_ids'] ) ? $value['fact_ids'] : array() ),
		);
	}
	$output['boring_fact_ids']  = personal_cta_threads_diagnostic_string_list( isset( $strategy['boring_fact_ids'] ) ? $strategy['boring_fact_ids'] : array() );
	$output['structures']        = array();
	$output['selected_hook_ids'] = array();
	foreach ( isset( $strategy['writer_plans'] ) && is_array( $strategy['writer_plans'] ) ? $strategy['writer_plans'] : array() as $plan ) {
		if ( ! is_array( $plan ) ) {
			continue;
		}
		$safe = array(
			'writer_id'    => isset( $plan['writer_id'] ) && is_scalar( $plan['writer_id'] ) ? trim( (string) $plan['writer_id'] ) : '',
			'structure_id' => isset( $plan['structure_id'] ) && is_scalar( $plan['structure_id'] ) ? trim( (string) $plan['structure_id'] ) : '',
			'hook_id'      => isset( $plan['hook_id'] ) && is_scalar( $plan['hook_id'] ) ? trim( (string) $plan['hook_id'] ) : '',
		);
		if ( ! empty( array_filter( $safe ) ) ) {
			$output['structures'][] = $safe;
			$output['selected_hook_ids'][] = $safe['hook_id'];
		}
	}
	$output['selected_hook_ids'] = personal_cta_threads_diagnostic_string_list( $output['selected_hook_ids'] );

	$output['hooks'] = array();
	foreach ( isset( $strategy['hooks'] ) && is_array( $strategy['hooks'] ) ? $strategy['hooks'] : array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$safe = array();
		foreach ( array( 'id', 'text' ) as $key ) {
			if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) {
				$safe[ $key ] = trim( (string) $item[ $key ] );
			}
		}
		$safe['fact_ids'] = personal_cta_threads_diagnostic_string_list( isset( $item['fact_ids'] ) ? $item['fact_ids'] : array() );
		if ( ! empty( array_filter( $safe ) ) ) {
			$output['hooks'][] = $safe;
		}
	}

	return $output;
}

/**
 * Returns saved writer/editor checkpoints without exposing source or API data.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function personal_cta_threads_admin_diagnostics( $post_id ) {
	$state           = personal_cta_threads_state( $post_id );
	$composer        = personal_cta_threads_diagnostic_copy( personal_cta_threads_meta( $post_id, 'composer_result', array() ) );
	$composer_repair = personal_cta_threads_diagnostic_copy( personal_cta_threads_meta( $post_id, 'composer_repair_result', array() ) );
	$fact_map        = personal_cta_threads_meta( $post_id, 'fact_map', array() );
	$stored          = personal_cta_threads_meta( $post_id, 'drafts', array() );
	$drafts          = array();
	$order    = array_unique( array_merge( array( 'A', 'B', 'C', 'H1', 'H2', 'H3' ), is_array( $stored ) ? array_keys( $stored ) : array() ) );
	foreach ( $order as $id ) {
		$draft = is_array( $stored ) && isset( $stored[ $id ] ) ? personal_cta_threads_diagnostic_copy( $stored[ $id ] ) : null;
		if ( is_array( $draft ) ) {
			$draft['id'] = (string) $id;
			$drafts[]    = $draft;
		}
	}

	$safe_fact_map = null;
	if ( is_array( $fact_map ) && ! empty( $fact_map ) ) {
		$safe_fact_map = array(
			'topic'            => isset( $fact_map['topic'] ) && is_scalar( $fact_map['topic'] ) ? trim( (string) $fact_map['topic'] ) : '',
			'reader_situation' => isset( $fact_map['reader_situation'] ) && is_scalar( $fact_map['reader_situation'] )
				? trim( (string) $fact_map['reader_situation'] )
				: ( isset( $fact_map['reader_problem'] ) && is_scalar( $fact_map['reader_problem'] ) ? trim( (string) $fact_map['reader_problem'] ) : '' ),
			'facts'            => array(),
		);
		foreach ( isset( $fact_map['facts'] ) && is_array( $fact_map['facts'] ) ? $fact_map['facts'] : array() as $fact ) {
			if ( ! is_array( $fact ) ) {
				continue;
			}
			$safe_fact_map['facts'][] = array(
				'id'        => isset( $fact['id'] ) && is_scalar( $fact['id'] ) ? trim( (string) $fact['id'] ) : '',
				'subject'   => isset( $fact['subject'] ) && is_scalar( $fact['subject'] ) ? trim( (string) $fact['subject'] ) : '',
				'statement' => isset( $fact['statement'] ) && is_scalar( $fact['statement'] )
					? trim( (string) $fact['statement'] )
					: ( isset( $fact['claim'] ) && is_scalar( $fact['claim'] ) ? trim( (string) $fact['claim'] ) : '' ),
			);
		}
	}

	$editor_raw = personal_cta_threads_meta( $post_id, 'editor_result', array() );
	$safe_editor = personal_cta_threads_diagnostic_copy( $editor_raw );

	$quality      = personal_cta_threads_meta( $post_id, 'final_quality_result', array() );
	$safe_quality = null;
	if ( is_array( $quality ) && ! empty( $quality ) ) {
		$quality_copy = isset( $quality['copy'] ) ? $quality['copy'] : $quality;
		if ( ! is_string( $quality_copy ) && ( ! is_array( $quality_copy ) || empty( $quality_copy['text'] ) ) ) {
			$quality_copy = $editor_raw;
		}
		if ( is_string( $quality_copy ) ) {
			$quality_copy = array( 'text' => $quality_copy );
		}
		$safe_quality = array(
			'decision' => isset( $quality['decision'] ) && is_scalar( $quality['decision'] ) ? trim( (string) $quality['decision'] ) : '',
			'issues'   => personal_cta_threads_diagnostic_string_list( isset( $quality['issues'] ) ? $quality['issues'] : array() ),
			'copy'     => null,
		);
		$safe_quality_copy = personal_cta_threads_diagnostic_copy( $quality_copy );
		if ( is_array( $safe_quality_copy ) ) {
			$safe_quality['copy'] = array( 'text' => $safe_quality_copy['text'] );
		}
	}

	$verifier      = personal_cta_threads_meta( $post_id, 'verifier_result', array() );
	$verifier      = is_array( $verifier ) ? $verifier : array();
	$verifier_state = (string) personal_cta_threads_meta( $post_id, 'verifier_state', 'not_run' );
	$safe_verifier = null;
	if ( ! empty( $verifier ) || 'not_run' !== $verifier_state ) {
		$safe_verifier = array(
			'state'    => sanitize_key( $verifier_state ),
			'decision' => isset( $verifier['decision'] ) && is_scalar( $verifier['decision'] ) ? trim( (string) $verifier['decision'] ) : '',
			'issues'   => personal_cta_threads_diagnostic_string_list( isset( $verifier['issues'] ) ? $verifier['issues'] : array() ),
			'checks'   => array(),
		);
		foreach ( isset( $verifier['checks'] ) && is_array( $verifier['checks'] ) ? array_slice( $verifier['checks'], 0, 12 ) : array() as $check ) {
			if ( ! is_array( $check ) ) {
				continue;
			}
			$unit_id = isset( $check['unit_id'] ) && is_scalar( $check['unit_id'] ) ? trim( (string) $check['unit_id'] ) : '';
			$safe_verifier['checks'][] = array(
				'unit_id'    => preg_match( '/^T[0-9]{3}$/', $unit_id ) ? $unit_id : '',
				'claim'      => isset( $check['claim'] ) && is_scalar( $check['claim'] ) ? sanitize_textarea_field( (string) $check['claim'] ) : '',
				'verdict'    => isset( $check['verdict'] ) && is_scalar( $check['verdict'] ) ? sanitize_key( (string) $check['verdict'] ) : '',
				'reason'     => isset( $check['reason'] ) && is_scalar( $check['reason'] ) ? sanitize_textarea_field( (string) $check['reason'] ) : '',
				'fact_ids'   => personal_cta_threads_diagnostic_string_list( isset( $check['fact_ids'] ) ? $check['fact_ids'] : array() ),
				'source_ids' => personal_cta_threads_diagnostic_string_list( isset( $check['evidence_ids'] ) ? $check['evidence_ids'] : array() ),
			);
		}
	}

	$final = null;
	if ( 'ready' === $state['status'] ) {
		$text = trim( (string) personal_cta_threads_meta( $post_id, 'final_text', '' ) );
		$final = '' === $text ? null : array( 'text' => $text );
	}

	return array(
		'status'          => $state['status'],
		'stage'           => $state['stage'],
		'composer'        => $composer,
		'composer_repair' => $composer_repair,
		'fact_map'        => $safe_fact_map,
		'strategy'        => personal_cta_threads_diagnostic_strategy( personal_cta_threads_meta( $post_id, 'strategy', array() ) ),
		'drafts'          => $drafts,
		'editor_raw'      => $safe_editor,
		'editor'          => $safe_editor,
		'final_quality'   => $safe_quality,
		'repair'          => personal_cta_threads_diagnostic_copy( personal_cta_threads_meta( $post_id, 'repair_result', array() ) ),
		'verifier'        => $safe_verifier,
		'final'           => $final,
	);
}

/**
 * Returns the administrator-only generation checkpoints.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function personal_cta_threads_rest_diagnostics( $request ) {
	return new WP_REST_Response( personal_cta_threads_admin_diagnostics( absint( $request['id'] ) ), 200 );
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
	if ( personal_cta_threads_is_working( $status ) ) {
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

	personal_cta_threads_kick_cron();

	return new WP_REST_Response( personal_cta_threads_admin_state( $post_id ), 202 );
}

/**
 * Requeues a stalled generation without starting a new one.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function personal_cta_threads_rest_resume( $request ) {
	$post_id = absint( $request['id'] );
	$ready   = personal_cta_threads_rest_ready( $post_id );
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}

	$result = personal_cta_threads_resume( $post_id );
	if ( is_wp_error( $result ) ) {
		return personal_cta_threads_rest_error( $result, 409 );
	}

	personal_cta_threads_kick_cron();

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
