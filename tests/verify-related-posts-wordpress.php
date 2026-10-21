<?php
/** Run with wp eval-file only in a disposable WordPress installation. Creates QA posts. */
function related_wp_check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function related_wp_post( $title, $category, $extra = array() ) {
	$id = wp_insert_post( array_merge( array( 'post_title' => $title, 'post_content' => '<p>본문 테스트입니다.</p>', 'post_status' => 'publish', 'post_category' => array( $category ) ), $extra ), true );
	related_wp_check( ! is_wp_error( $id ), 'Could not create QA post' );
	return $id;
}
update_option( 'personal_cta_social_thumbnail', array( 'enabled' => false ) );
$suffix = uniqid();
$category = wp_insert_term( '계정 보안 QA ' . $suffix, 'category' );
$other = wp_insert_term( '다른 카테고리 QA ' . $suffix, 'category' );
related_wp_check( ! is_wp_error( $category ) && ! is_wp_error( $other ), 'Could not create QA categories' );
$category = $category['term_id'];
$other = $other['term_id'];
$main = related_wp_post( '게임 계정 보안, 알아두면 좋은 설정', $category, array( 'post_content' => '<p>계정 설정을 확인한 뒤, 아래 글에서 관련 내용을 더 살펴보세요.</p>' ) );
$titles = array( '로그인 문제가 생겼을 때 확인할 순서', '게임 계정 비밀번호 안전하게 관리하는 방법', '스팀 계정 복구 전에 준비할 정보', '배틀그라운드 계정 보안, 먼저 확인할 설정' );
$expected = array();
foreach ( $titles as $i => $title ) {
	$expected[] = related_wp_post( $title, $category, array( 'post_date' => '2026-01-0' . ( $i + 2 ) . ' 12:00:00' ) );
}
related_wp_post( '오래된 같은 카테고리 글', $category, array( 'post_date' => '2025-01-01 12:00:00' ) );
foreach ( array( 'draft', 'private', 'future', 'trash' ) as $status ) {
	related_wp_post( '표시하면 안 되는 ' . $status, $category, array( 'post_status' => $status, 'post_date' => '2027-01-01 12:00:00' ) );
}
related_wp_post( '비밀번호 글', $category, array( 'post_password' => 'qa-only' ) );
related_wp_post( '다른 카테고리 글', $other );
related_wp_post( '같은 카테고리 페이지', $category, array( 'post_type' => 'page' ) );
$source = dirname( __DIR__ ) . '/design/thumbnail-preview-16x9.jpg';
if ( ! is_readable( $source ) ) { $source = __DIR__ . '/fixtures/ai-background.jpg'; }
$upload = wp_upload_bits( 'related-qa.jpg', null, file_get_contents( $source ) );
related_wp_check( ! $upload['error'], 'Could not upload QA thumbnail' );
$attachment = wp_insert_attachment( array( 'post_title' => 'QA thumbnail', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $upload['file'], 0, true );
related_wp_check( ! is_wp_error( $attachment ), 'Could not create QA attachment' );
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['file'] ) );
set_post_thumbnail( $expected[3], $attachment );
wp_update_post( array( 'ID' => $expected[2], 'post_content' => '<img src="javascript:alert(1)"><img src="' . esc_url( $upload['url'] ) . '">' ) );
set_post_thumbnail( $expected[1], $attachment );
update_option( 'sticky_posts', array( $expected[0] ) );
query_posts( array( 'p' => $main, 'post_type' => 'post' ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
related_wp_check( have_posts(), 'Main query must have a post' );
the_post();
$original = get_the_content();
$html = apply_filters( 'the_content', $original );
related_wp_check( 1 === substr_count( $html, 'class="pct-related"' ) && 4 === substr_count( $html, '<li class="pct-related__item"' ), 'Exactly four related posts' );
$position = -1;
foreach ( array_reverse( $expected ) as $id ) {
	$found = strpos( $html, 'href="' . esc_url( get_permalink( $id ) ) . '"' );
	related_wp_check( false !== $found && $found > $position, 'Category selection and latest-first order' );
	$position = $found;
}
related_wp_check( false === strpos( $html, '표시하면 안 되는' ) && false === strpos( $html, 'javascript:' ) && false === strpos( $html, '오래된 같은 카테고리 글' ), 'Exclude non-public posts, older overflow and unsafe image URLs' );
related_wp_check( 3 === substr_count( $html, 'loading="lazy"' ) && false !== strpos( $html, 'pct-related__placeholder' ), 'Real image parser and fallback' );
$metadata = wp_get_attachment_metadata( $attachment );
if ( ! empty( $metadata['sizes'] ) ) {
	related_wp_check( false !== strpos( $html, 'srcset=' ), 'Responsive featured image when resized variants exist' );
}
related_wp_check( $main === get_the_ID() && $original === get_post_field( 'post_content', $main ), 'Main post and stored content unchanged' );
wp_update_post( array( 'ID' => $expected[0], 'post_status' => 'draft' ) );
$older = get_posts( array( 'title' => '오래된 같은 카테고리 글', 'category' => $category, 'numberposts' => 1 ) );
wp_delete_post( $older[0]->ID, true );
related_wp_check( 3 === substr_count( apply_filters( 'the_content', $original ), '<li class="pct-related__item"' ), 'Three available results' );
wp_update_post( array( 'ID' => $expected[0], 'post_status' => 'publish' ) );
$isolated = wp_insert_term( '추천 없는 QA ' . $suffix, 'category' );
wp_set_post_categories( $main, array( $isolated['term_id'] ) );
related_wp_check( false === strpos( apply_filters( 'the_content', $original ), 'class="pct-related"' ), 'Hide empty recommendations' );
wp_set_post_categories( $main, array( $category ) );
echo "WordPress database selection, latest ordering, 3/4/empty results, image APIs and unchanged content passed.\n";
echo 'QA post: ' . get_permalink( $main ) . "\n";
