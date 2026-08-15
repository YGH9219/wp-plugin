<?php
/**
 * Daily-life Threads generation and scheduling.
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_THREADS_DAILY_PROMPT_VERSION', '1.0' );
define( 'PERSONAL_CTA_THREADS_DAILY_STATE_OPTION', 'personal_cta_threads_daily_state' );
define( 'PERSONAL_CTA_THREADS_DAILY_HISTORY_OPTION', 'personal_cta_threads_daily_history' );
define( 'PERSONAL_CTA_THREADS_DAILY_PLANNER_HOOK', 'personal_cta_threads_daily_planner' );
define( 'PERSONAL_CTA_THREADS_DAILY_PUBLISH_HOOK', 'personal_cta_threads_daily_publish' );

/** Returns the supported daily-post structure identifiers. */
function personal_cta_threads_daily_structures() {
	return array( 'observation_interpretation', 'observation_question', 'criteria_change', 'compare', 'small_rule', 'question_thought', 'three_items', 'redefine', 'opposite_view', 'hypothetical', 'narrowing', 'memo_expand' );
}

/** Returns supported opening identifiers. */
function personal_cta_threads_daily_openings() {
	return array( 'micro_observation', 'contrast', 'question', 'criterion', 'opinion', 'small_friction', 'choice', 'redefinition', 'relatable_moment', 'reverse_observation', 'short_statement', 'small_question' );
}

/** Returns supported ending identifiers. */
function personal_cta_threads_daily_endings() {
	return array( 'open_question', 'choice_question', 'afterglow', 'return_to_start', 'short_criterion', 'unfinished_thought', 'people_differ', 'self_question', 'daily_humor', 'quiet_twist', 'statement', 'recall_experience' );
}

/** Strict schema for one daily batch. */
function personal_cta_threads_daily_schema( $count ) {
	$count = max( 1, min( 5, (int) $count ) );
	$item  = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'text', 'topic', 'structure', 'opening_type', 'ending_type', 'used_personal_fact' ),
		'properties'           => array(
			'text'               => array( 'type' => 'string' ),
			'topic'              => array( 'type' => 'string' ),
			'structure'          => array( 'type' => 'string', 'enum' => personal_cta_threads_daily_structures() ),
			'opening_type'       => array( 'type' => 'string', 'enum' => personal_cta_threads_daily_openings() ),
			'ending_type'        => array( 'type' => 'string', 'enum' => personal_cta_threads_daily_endings() ),
			'used_personal_fact' => array( 'type' => 'boolean' ),
		),
	);

	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'posts' ),
		'properties'           => array(
			'posts' => array( 'type' => 'array', 'minItems' => $count, 'maxItems' => $count, 'items' => $item ),
		),
	);
}

/** Stable daily-life writing instructions. */
function personal_cta_threads_daily_prompt() {
	return <<<'PROMPT'
너는 한국어 Threads용 짧은 일상글 에디터다. 광고·정보요약·자기계발 문구가 아니라 실제 사람이 평소 생각하다가 휴대폰으로 짧게 적은 글처럼 쓴다.

# 사실성 경계
입력 JSON만 작성자의 실제 사실 근거로 사용한다. today_facts가 비어 있으면 방문 장소, 먹은 음식, 산 물건, 만난 사람, 실제 대화, 직장 사건, 오늘 있었던 일, 날씨, 여행, 병원, 건강 상태, 수입·손실, 제품 사용 경험을 작성자가 겪은 것처럼 만들지 않는다. 생각·의견·관찰·취향·가정형 상황·공감 질문·생활 기준은 쓸 수 있다. 입력에 없는 세부사항을 보충하지 않는다.

뉴스·정책·법률·세금·금융·투자·건강·의학 사실, 연구·전문가 권위, 계산되지 않은 절약·효과를 만들지 않는다. 정치, 혐오, 선정적 논쟁은 다루지 않는다.

# 글 품질
- 글마다 핵심 생각 하나만 담고 80~260자를 우선하며 절대 500자를 넘지 않는다.
- 자연스러움, 구체성, 공감, 짧고 다른 리듬, 새로움 순으로 중요하다.
- 평범한 말과 자연스러운 반말·혼잣말을 쓴다. 짧은 문장과 중간 문장을 섞고 의미가 바뀌는 곳에서만 줄바꿈한다.
- 40대의 현실적이고 차분한 관찰자 관점이다. 무리하지 않고 오래 가는 생활, 일에서 덜 소모되는 기준, 편한 관계, 덜 관리하는 소비, 휴식, 달라진 취향을 자연스럽게 다룰 수 있지만 세대를 단정하지 않는다.
- 첫 문장은 작은 관찰, 의외의 생각, 질문, 대비, 달라진 기준, 사소한 불편 중 하나로 다음 문장을 읽을 이유를 만든다. 자극적 경고·정답 선언·기사 제목은 금지한다.
- 마지막은 작은 질문, 선택, 짧은 관찰, 생활 유머, 조용한 반전, 결론 없는 여운 중 하나다. 모든 글을 질문이나 '~인 것 같다'로 끝내지 않는다.
- 이모지는 꼭 필요할 때만 0~2개. URL, 해시태그, 좋아요·팔로우·댓글 요청은 쓰지 않는다.
- '이거 모르면 손해', '당장 바꾸세요', '반드시 기억하세요', '딱 세 가지', 공공기관 안내문, 블로그식 인사, 전문가 행세를 금지한다.

# 다양성
recent_posts를 읽고 핵심 주제·주장·첫 문장 골격·structure·리듬·ending_type·특징 표현을 반복하지 않는다. 직전 글과 같은 structure를 쓰지 않고 최근 5개의 opening_type을 가능한 한 순환한다. 표현만 바꾼 같은 생각도 중복이다.

한 번에 여러 글을 만들면 모든 글의 소재와 관점을 다르게 하고 structure와 opening_type을 서로 다르게 한다. 일/생활, 관계, 소비·물건, 감정·휴식, 집·시간처럼 결을 섞는다.

# 좋은 기준 예시
1) 답장이 늦는 것보다 뭐라고 답해야 할지 계속 생각하는 게 더 피곤할 때가 있다. 편한 관계는 잠깐 늦어도 이상하지 않은 관계에 가까운 것 같다.
2) 물건을 살지 말지 오래 고민될 때는 가격보다 둘 자리를 먼저 생각해보는 것도 괜찮다. 사는 건 한 번인데 둘 곳을 고민하는 건 그다음부터니까.
3) 쉬는 날까지 알차게 보내야 한다는 생각은 꽤 피곤하다. 아무 일정 없는 시간이 꼭 낭비는 아닐 수 있다.
4) 애매하게 남은 20분은 이상하게 쓰기 어렵다. 뭔가 시작하기엔 짧고 그냥 있기엔 길다. 이런 시간은 다들 어떻게 쓰는지 궁금하다.
5) 가까운 사이라고 모든 시간을 같이 써야 가까운 건 아닐 거다. 각자 조용히 있을 시간이 있어야 같이 있는 시간도 덜 지칠 때가 있다.

출력 전에 허구의 경험, 위험한 사실 주장, 최근 의미 중복, 같은 구조·첫 문장, 광고·강의 말투, 핵심 두 개 이상, 500자 초과를 스스로 제거한다. 반드시 제공된 JSON Schema만 출력한다.
PROMPT;
}

/** Returns the last twenty published daily posts. */
function personal_cta_threads_daily_history() {
	$history = get_option( PERSONAL_CTA_THREADS_DAILY_HISTORY_OPTION, array() );

	return is_array( $history ) ? array_slice( array_values( $history ), -20 ) : array();
}

/** Validates the deliberately small application-level daily contract. */
function personal_cta_threads_validate_daily_posts( $data, $count, $recent = array() ) {
	$count = max( 1, min( 5, (int) $count ) );
	if ( ! is_array( $data ) || ! isset( $data['posts'] ) || ! is_array( $data['posts'] ) || $count !== count( $data['posts'] ) ) {
		return new WP_Error( 'pct_daily_invalid', '일상글 생성 개수가 올바르지 않습니다.' );
	}
	$recent_texts = array();
	foreach ( $recent as $item ) {
		if ( is_array( $item ) && isset( $item['text'] ) ) {
			$recent_texts[] = preg_replace( '/\s+/u', ' ', trim( (string) $item['text'] ) );
		}
	}
	$texts = array();
	$structures = array();
	$openings = array();
	$clean = array();
	foreach ( $data['posts'] as $post ) {
		if ( ! is_array( $post ) ) {
			return new WP_Error( 'pct_daily_invalid', '일상글 형식이 올바르지 않습니다.' );
		}
		$text = isset( $post['text'] ) && is_scalar( $post['text'] ) ? trim( (string) $post['text'] ) : '';
		$topic = isset( $post['topic'] ) && is_scalar( $post['topic'] ) ? trim( (string) $post['topic'] ) : '';
		$structure = isset( $post['structure'] ) ? (string) $post['structure'] : '';
		$opening = isset( $post['opening_type'] ) ? (string) $post['opening_type'] : '';
		$ending = isset( $post['ending_type'] ) ? (string) $post['ending_type'] : '';
		$normalized = preg_replace( '/\s+/u', ' ', $text );
		if ( '' === $text || '' === $topic || personal_cta_threads_character_length( $text ) > 500 || preg_match( '#(?:https?://|www\.)#i', $text ) || preg_match( '/(?:^|\s)#[\p{L}\p{N}_]+/u', $text ) ) {
			return new WP_Error( 'pct_daily_invalid', '일상글에 빈 내용, URL, 해시태그 또는 500자 초과가 있습니다.' );
		}
		if ( ! in_array( $structure, personal_cta_threads_daily_structures(), true ) || ! in_array( $opening, personal_cta_threads_daily_openings(), true ) || ! in_array( $ending, personal_cta_threads_daily_endings(), true ) || ! empty( $post['used_personal_fact'] ) ) {
			return new WP_Error( 'pct_daily_invalid', '일상글 분류 또는 개인 사실 사용 표시가 올바르지 않습니다.' );
		}
		if ( in_array( $normalized, $texts, true ) || in_array( $normalized, $recent_texts, true ) ) {
			return new WP_Error( 'pct_daily_duplicate', '최근 일상글과 완전히 같은 문구가 생성되었습니다.' );
		}
		$texts[]      = $normalized;
		$structures[] = $structure;
		$openings[]   = $opening;
		$clean[]      = array(
			'text' => $text, 'topic' => sanitize_text_field( $topic ), 'structure' => $structure,
			'opening_type' => $opening, 'ending_type' => $ending, 'used_personal_fact' => false,
		);
	}
	if ( count( array_unique( $structures ) ) !== $count || count( array_unique( $openings ) ) !== $count ) {
		return new WP_Error( 'pct_daily_duplicate_structure', '하루 일상글의 구조와 첫 문장이 충분히 다르지 않습니다.' );
	}

	return $clean;
}

/** Generates one complete daily batch in a single Responses API request. */
function personal_cta_threads_generate_daily_posts( $count ) {
	$count   = max( 1, min( 5, (int) $count ) );
	$recent  = personal_cta_threads_daily_history();
	$context = array(
		'current_datetime' => wp_date( DATE_ATOM ),
		'timezone'         => wp_timezone_string(),
		'allowed_topics'   => array( '생활 습관', '회사와 일', '시간 사용', '인간관계', '소비와 물건', '집안일', '휴식', '감정', '가족', '나이에 따라 달라진 기준', '귀찮음을 줄이는 선택', '소소한 취향' ),
		'forbidden_topics' => array( '정치', '확인되지 않은 뉴스', '투자 추천', '의학적 조언', '질병 진단', '법률 조언', '정책 사실 단정', '혐오', '선정적 논쟁' ),
		'author_profile'   => array(
			'age_band' => '40대', 'personality' => array( '현실적', '차분함', '관찰을 좋아함', '과장하지 않음', '사람을 쉽게 단정하지 않음' ),
			'speech_level' => '자연스러운 반말 또는 혼잣말', 'humor' => '아주 가끔 가벼운 생활 유머',
		),
		'today_facts'      => array(),
		'recent_posts'     => $recent,
		'generation_request' => array( 'count' => $count, 'topic' => 'auto', 'structure' => 'auto', 'max_chars_per_post' => 500, 'emoji_max' => 2, 'hashtags' => false ),
	);
	$response = personal_cta_threads_openai_request( 'daily', personal_cta_threads_daily_prompt(), $context, personal_cta_threads_daily_schema( $count ) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$posts = personal_cta_threads_validate_daily_posts( $response['data'], $count, $recent );
	if ( is_wp_error( $posts ) ) {
		return $posts;
	}

	return array( 'posts' => $posts, 'response_id' => (string) $response['response_id'], 'usage' => $response['usage'] );
}

/** Returns a site-timezone date chosen for a complete five-slot day. */
function personal_cta_threads_daily_target_date() {
	$timezone = wp_timezone();
	$now      = new DateTimeImmutable( 'now', $timezone );

	return $now->format( 'H:i' ) < '07:00' ? $now->format( 'Y-m-d' ) : $now->modify( '+1 day' )->format( 'Y-m-d' );
}

/** Creates non-mechanical timestamps spread through 07:00-24:00. */
function personal_cta_threads_daily_times( $date, $count ) {
	$count     = max( 1, min( 5, (int) $count ) );
	$timezone  = wp_timezone();
	$start     = new DateTimeImmutable( $date . ' 07:10:00', $timezone );
	$total     = 990;
	$slot      = (int) floor( $total / $count );
	$timestamps = array();
	for ( $index = 0; $index < $count; $index++ ) {
		$low  = $index * $slot + 15;
		$high = min( $total - 1, ( $index + 1 ) * $slot - 15 );
		$timestamps[] = $start->modify( '+' . random_int( $low, max( $low, $high ) ) . ' minutes' )->getTimestamp();
	}

	return $timestamps;
}

/** Saves one state array without autoloading it. */
function personal_cta_threads_save_daily_state( $state ) {
	if ( false === get_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, false ) ) {
		return add_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, $state, '', false );
	}

	return update_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, $state, false );
}

/** Builds and schedules one daily batch. */
function personal_cta_threads_plan_daily_posts() {
	$settings = personal_cta_threads_settings();
	$account  = personal_cta_threads_account();
	if ( empty( $settings['daily_enabled'] ) || empty( $account['connected'] ) ) {
		return new WP_Error( 'pct_daily_disabled', '일상글 자동 게시 또는 Threads 계정 연결이 설정되지 않았습니다.' );
	}
	$date  = personal_cta_threads_daily_target_date();
	$state = get_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, array() );
	if ( is_array( $state ) && $date === ( $state['date'] ?? '' ) && ! empty( $state['items'] ) ) {
		return $state;
	}
	$count  = max( 1, min( 5, (int) $settings['daily_count'] ) );
	$result = personal_cta_threads_generate_daily_posts( $count );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$times = personal_cta_threads_daily_times( $date, $count );
	$items = array();
	foreach ( $result['posts'] as $index => $post ) {
		$id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'pct_daily_', true );
		$item = array_merge( $post, array(
			'id' => $id, 'scheduled_at' => $times[ $index ], 'status' => 'scheduled', 'creation_id' => '',
			'remote_id' => '', 'remote_url' => '', 'published_at' => 0, 'last_error' => '',
		) );
		$scheduled = wp_schedule_single_event( $times[ $index ], PERSONAL_CTA_THREADS_DAILY_PUBLISH_HOOK, array( $id ), true );
		if ( false === $scheduled || is_wp_error( $scheduled ) ) {
			$item['status']     = 'failed';
			$item['last_error'] = '일상글 게시 시간을 예약하지 못했습니다.';
		}
		$items[] = $item;
	}
	$state = array( 'date' => $date, 'generated_at' => time(), 'response_id' => $result['response_id'], 'usage' => $result['usage'], 'items' => $items, 'last_error' => '' );
	personal_cta_threads_save_daily_state( $state );

	return $state;
}

/** Adds a published item to the bounded recent history. */
function personal_cta_threads_remember_daily_post( $item ) {
	$history = personal_cta_threads_daily_history();
	$history[] = array(
		'id' => (string) $item['id'], 'published_at' => (int) $item['published_at'], 'text' => (string) $item['text'],
		'topic' => (string) $item['topic'], 'structure' => (string) $item['structure'], 'opening_type' => (string) $item['opening_type'], 'ending_type' => (string) $item['ending_type'],
	);
	$history = array_slice( $history, -20 );
	if ( false === get_option( PERSONAL_CTA_THREADS_DAILY_HISTORY_OPTION, false ) ) {
		add_option( PERSONAL_CTA_THREADS_DAILY_HISTORY_OPTION, $history, '', false );
	} else {
		update_option( PERSONAL_CTA_THREADS_DAILY_HISTORY_OPTION, $history, false );
	}
}

/** Publishes one scheduled daily item using a persisted two-step checkpoint. */
function personal_cta_threads_publish_daily_post( $item_id ) {
	$state = get_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, array() );
	if ( ! is_array( $state ) || empty( $state['items'] ) || ! is_array( $state['items'] ) ) {
		return;
	}
	foreach ( $state['items'] as $index => $item ) {
		if ( ! is_array( $item ) || (string) ( $item['id'] ?? '' ) !== (string) $item_id || 'published' === ( $item['status'] ?? '' ) ) {
			continue;
		}
		$settings = personal_cta_threads_settings();
		if ( empty( $settings['daily_enabled'] ) ) {
			$state['items'][ $index ]['status']     = 'paused';
			$state['items'][ $index ]['last_error'] = '일상글 자동 게시 설정이 꺼져 있습니다.';
			personal_cta_threads_save_daily_state( $state );
			return;
		}
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		if ( (int) $now->format( 'H' ) < 7 ) {
			$next = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' 07:' . sprintf( '%02d', random_int( 10, 35 ) ) . ':00', wp_timezone() );
			$state['items'][ $index ]['scheduled_at'] = $next->getTimestamp();
			$state['items'][ $index ]['status']       = 'scheduled';
			personal_cta_threads_save_daily_state( $state );
			wp_schedule_single_event( $next->getTimestamp(), PERSONAL_CTA_THREADS_DAILY_PUBLISH_HOOK, array( (string) $item_id ), true );
			return;
		}
		if ( empty( personal_cta_threads_account()['connected'] ) ) {
			$state['items'][ $index ]['status'] = 'paused';
			$state['items'][ $index ]['last_error'] = 'Threads 계정 연결을 확인하세요.';
			personal_cta_threads_save_daily_state( $state );
			return;
		}
		if ( empty( $item['creation_id'] ) ) {
			$result = personal_cta_threads_create_container( (string) $item['text'] );
			if ( is_wp_error( $result ) ) {
				$state['items'][ $index ]['status'] = 'failed';
				$state['items'][ $index ]['last_error'] = sanitize_text_field( $result->get_error_message() );
				personal_cta_threads_save_daily_state( $state );
				return;
			}
			$state['items'][ $index ]['creation_id'] = (string) $result['id'];
			$state['items'][ $index ]['status'] = 'container_ready';
			personal_cta_threads_save_daily_state( $state );
			wp_schedule_single_event( time() + 15, PERSONAL_CTA_THREADS_DAILY_PUBLISH_HOOK, array( (string) $item_id ), true );
			return;
		}
		$result = personal_cta_threads_publish_container( (string) $item['creation_id'] );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$state['items'][ $index ]['status'] = is_array( $data ) && ! empty( $data['ambiguous'] ) ? 'uncertain' : 'failed';
			$state['items'][ $index ]['last_error'] = sanitize_text_field( $result->get_error_message() );
			personal_cta_threads_save_daily_state( $state );
			return;
		}
		$state['items'][ $index ]['remote_id']    = (string) $result['id'];
		$state['items'][ $index ]['remote_url']   = personal_cta_threads_media_url( (string) $result['id'] );
		$state['items'][ $index ]['published_at'] = time();
		$state['items'][ $index ]['status']       = 'published';
		$state['items'][ $index ]['last_error']   = '';
		personal_cta_threads_save_daily_state( $state );
		personal_cta_threads_remember_daily_post( $state['items'][ $index ] );
		return;
	}
}
add_action( PERSONAL_CTA_THREADS_DAILY_PUBLISH_HOOK, 'personal_cta_threads_publish_daily_post', 10, 1 );

/** Runs the daily planner and retries one transient/invalid batch once. */
function personal_cta_threads_run_daily_planner() {
	$result = personal_cta_threads_plan_daily_posts();
	if ( is_wp_error( $result ) ) {
		$state = get_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		$attempts = (int) ( $state['planner_attempts'] ?? 0 ) + 1;
		$state['planner_attempts'] = $attempts;
		$state['last_error'] = sanitize_text_field( $result->get_error_message() );
		personal_cta_threads_save_daily_state( $state );
		if ( $attempts < 2 ) {
			wp_schedule_single_event( time() + 600, PERSONAL_CTA_THREADS_DAILY_PLANNER_HOOK, array(), true );
		}
	}
	personal_cta_threads_ensure_daily_planner();
}
add_action( PERSONAL_CTA_THREADS_DAILY_PLANNER_HOOK, 'personal_cta_threads_run_daily_planner' );

/** Ensures a planner run exists without publishing between midnight and 07:00. */
function personal_cta_threads_ensure_daily_planner() {
	$settings = personal_cta_threads_settings();
	if ( empty( $settings['daily_enabled'] ) || empty( personal_cta_threads_account()['connected'] ) || wp_next_scheduled( PERSONAL_CTA_THREADS_DAILY_PLANNER_HOOK ) ) {
		return;
	}
	$timezone = wp_timezone();
	$now      = new DateTimeImmutable( 'now', $timezone );
	$today    = get_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, array() );
	$target   = personal_cta_threads_daily_target_date();
	if ( ! is_array( $today ) || $target !== ( $today['date'] ?? '' ) || empty( $today['items'] ) ) {
		wp_schedule_single_event( time() + 60, PERSONAL_CTA_THREADS_DAILY_PLANNER_HOOK, array(), true );
		return;
	}
	$next = new DateTimeImmutable( $now->modify( '+1 day' )->format( 'Y-m-d' ) . ' 00:10:00', $timezone );
	wp_schedule_single_event( $next->getTimestamp(), PERSONAL_CTA_THREADS_DAILY_PLANNER_HOOK, array(), true );
}
add_action( 'init', 'personal_cta_threads_ensure_daily_planner' );

/** Returns browser-safe daily automation summary. */
function personal_cta_threads_daily_summary() {
	$state = get_option( PERSONAL_CTA_THREADS_DAILY_STATE_OPTION, array() );
	$items = is_array( $state ) && isset( $state['items'] ) && is_array( $state['items'] ) ? $state['items'] : array();
	$published = 0;
	$next = 0;
	foreach ( $items as $item ) {
		if ( 'published' === ( $item['status'] ?? '' ) ) {
			$published++;
		} elseif ( 'scheduled' === ( $item['status'] ?? '' ) && (int) ( $item['scheduled_at'] ?? 0 ) > time() && ( 0 === $next || (int) $item['scheduled_at'] < $next ) ) {
			$next = (int) $item['scheduled_at'];
		}
	}

	return array( 'date' => is_array( $state ) ? (string) ( $state['date'] ?? '' ) : '', 'total' => count( $items ), 'published' => $published, 'next' => $next, 'last_error' => is_array( $state ) ? (string) ( $state['last_error'] ?? '' ) : '' );
}
