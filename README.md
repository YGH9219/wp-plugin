# Personal CTA Blocks

WordPress용 펄스 CTA 블록, AI 브랜드·본문 이미지, AI Threads 문구 생성·자동 게시 기능을 한 플러그인으로 제공합니다.

- 글 편집기에서 `/ㅂㅌ`으로 반응형 CTA 버튼 추가
- CTA 버튼별 새 탭, `nofollow`, `sponsored` 링크 설정
- 여행허브 청록색 기본 CTA와 버튼별 배경색·글자색 선택
- 기본 갤러리 블록의 `옆으로 넘기는 사진` 스타일: 모바일 스와이프, 이전/다음 버튼, 방향키·Home·End 지원
- 글 하단에 같은 카테고리의 최신 글을 썸네일·제목으로 최대 4개 자동 표시
- 대표이미지 또는 수동 AI 배경을 소셜·Google 검색용 4종 브랜드 JPG로 자동 생성
- Rank Math의 첫 번째 포커스 키워드로 비어 있는 대표이미지 ALT와 소셜 이미지 ALT 자동 보완
- 두 줄 문구·이미지 초점·사이트 로고·브랜드 색상 설정
- Rank Math의 Facebook·Threads·X 이미지에 자동 연결
- Gutenberg의 H2/H3를 골라 1200×800 정보성 본문 이미지를 생성하고 바로 아래에 삽입
- 글 편집기 상단의 `Threads 문구` 아이콘에서 문구 생성
- 저장된 원문 전체와 관리자가 등록한 합격 예시를 한 번에 읽는 AI Composer
- 정상 생성은 OpenAI 호출 1회, 500자 초과일 때만 한 번 축약
- 문체 취향이 달라도 사용할 수 있는 문구는 버리지 않고 관리자에게 표시
- Threads 계정 연결 시 정보글 문구 자동 게시, 미연결 시 기존 복사 방식 유지
- 매일 일상글 5개를 한 번에 생성해 07:00~24:00 사이 불규칙 예약 게시
- 최근 일상글 20개의 주제·구조·첫 문장·끝맺음을 다음 생성에 반영
- 원문 링크와 선택적인 UTM을 복사 문구 끝에 자동 추가

Threads 기능과 일상글 자동 게시는 기본적으로 꺼져 있으며, 일반 방문자에게는 패널·스크립트·외부 API 호출이 노출되지 않습니다. Meta 계정을 연결하지 않으면 어떠한 자동 게시도 하지 않습니다.

## 숙소 사진 넘기기

편집기에서 **갤러리** 블록을 넣고 미디어 라이브러리에서 실제 숙소 사진을 여러 장 선택한 뒤, 블록 스타일에서 **옆으로 넘기는 사진**을 고릅니다. 기존 숙소 카드 그룹 안의 사진 한 장을 이 갤러리로 바꾸면 설명과 예약 버튼은 그대로 유지됩니다. 사진 순서·ALT·짧은 캡션은 워드프레스 기본 갤러리에서 편집합니다. 권장 사진은 외관, 객실, 부대시설 순서의 3~5장이며 객실 종류·시설 이용 조건을 보장하지 않는 사진에는 해당 장면을 정확히 설명합니다.

자동 재생이나 외부 슬라이더 의존성이 없습니다. 두 장 이상인 해당 스타일에만 방문자 스크립트를 불러오며, JavaScript가 꺼져도 가로 스크롤로 사진을 볼 수 있습니다. 플러그인을 꺼도 원래 갤러리와 이미지가 보존됩니다.

0.9.5부터 PC 폭(769px 이상)에서는 이전·다음 버튼이 사진 좌우 중앙에 겹쳐 표시되고, 현재 장수는 사진 오른쪽 아래에 나옵니다. 모바일은 사진 아래 조작부와 스와이프를 유지합니다. 기존 버튼의 CSS만 바꾸며 사진·ALT·캡션·예약 링크나 방문자 JavaScript를 추가하지 않습니다.

## 함께 보면 좋은 글

0.9.6부터 일반 글 하단에 **함께 보면 좋은 글**을 자동 표시합니다. 현재 글과 카테고리가 하나 이상 겹치는 공개 글을 최신순으로 최대 4개 보여주며, 현재 글·비밀번호 글·비공개·임시 글은 제외합니다. 같은 카테고리의 글이 적으면 있는 만큼만 표시하고, 한 개도 없으면 영역을 숨깁니다. 페이지·목록·피드·REST 응답에는 추가하지 않으며 저장된 본문을 변경하지 않습니다.

가로형 16:9 썸네일 아래에 제목을 배치하고, PC에서는 결과 수에 따라 최대 4열, 모바일(600px 이하)에서는 2열로 표시합니다. 대표이미지가 없으면 본문의 첫 이미지를 사용하고, 이미지가 전혀 없으면 대체 영역을 표시합니다. 글꼴·글자색은 테마를 따르고 제목 옆 포인트는 기존 브랜드 색상을 사용합니다. 키보드 초점 표시와 동작 줄이기를 지원하며 방문자 JavaScript·외부 API·추가 설정은 필요하지 않습니다. 다른 테마나 플러그인에서도 관련 글을 출력한다면 해당 기능은 별도로 꺼야 중복 노출을 피할 수 있습니다.

디자인 참고: [Jetpack Related Posts](https://jetpack.com/support/related-posts/), [Related Posts Thumbnails](https://wordpress.org/plugins/related-posts-thumbnails/), [Contextual Related Posts](https://wordpress.org/plugins/contextual-related-posts/).

## 요구 사항

- WordPress 6.3 이상
- PHP 7.4 이상
- FreeType 포함 GD 또는 Imagick 이미지 확장
- OpenAI API 키
- 자동 게시 사용 시 Meta Threads App과 `threads_basic`, `threads_content_publish` 권한
- 생성 작업을 이어서 실행할 수 있는 WP-Cron

## OpenAI API 키 설정

`설정 → Threads 문구`에서 API 키를 직접 입력해 저장할 수 있습니다. 저장된 키는 다시 표시하지 않으며 WordPress 보안 키로 암호화해 비자동로드 옵션에 보관합니다. `wp-config.php` 또는 서버 환경변수 설정이 있으면 그 값이 우선합니다.

서버 설정으로 관리하려면 다음처럼 둘 수도 있습니다.

```php
define( 'PERSONAL_CTA_OPENAI_API_KEY', '...');
```

`OPENAI_API_KEY`도 `wp-config.php` 상수 또는 환경변수로 사용할 수 있습니다. 키는 관리자 HTML, JavaScript, REST 응답, 오류 로그에 출력하지 않습니다.

## Threads 계정 연결과 자동 게시

`설정 → Threads 문구`에서 Threads App ID와 App Secret을 저장한 뒤 `Meta에서 Threads 계정 연결`을 누릅니다. Meta 앱에는 설정 화면에 표시된 리디렉션·제거·삭제 콜백 URI를 각각 한 글자도 바꾸지 않고 등록해야 합니다. 제거·삭제 요청은 Meta의 `signed_request` 서명을 확인한 뒤 계정 연결과 예약 데이터를 정리합니다. 이미 장기 액세스 토큰과 Threads 사용자 ID가 있다면 같은 화면의 직접 연결 입력란도 사용할 수 있습니다.

App Secret과 액세스 토큰은 다시 표시하지 않으며 WordPress 보안 키로 AES-256-GCM 암호화해 비자동로드 옵션에 저장합니다. 예전 버전의 `PERSONAL_CTA_THREADS_MASTER_KEY`로 저장된 계정 토큰은 처음 사용할 때 현재 암호화 방식으로 자동 이전합니다.

서버 설정으로 관리하려면 다음 상수 또는 같은 이름의 환경변수를 사용할 수 있습니다.

```php
define( 'PERSONAL_CTA_THREADS_APP_ID', '...');
define( 'PERSONAL_CTA_THREADS_APP_SECRET', '...');
define( 'PERSONAL_CTA_THREADS_USER_ID', '...');
define( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN', '...');
```

- 계정 연결됨: 정보글 문구 생성이 끝나면 링크·UTM을 포함해 Threads에 자동 게시합니다.
- 계정 미연결: 생성 문구를 편집기 패널에서 복사해 직접 게시합니다.
- 게시 응답이 불명확한 네트워크 오류는 중복 게시를 막기 위해 자동 재시도하지 않습니다.

## 설정과 정보글 사용

1. 플러그인을 설치하고 활성화합니다.
2. WordPress 관리자에서 `설정 → Threads 문구`를 엽니다.
3. 기능을 켜고 OpenAI API 키, 원문 링크와 UTM 사용 여부를 저장합니다.
4. `스타일 예시`에 원하는 말투와 구성으로 잘 나온 Threads 본문을 3~5개 넣습니다. 서로 다른 주제의 예시를 쓰고 URL은 빼세요.
5. 일반 글을 발행한 뒤 PC에서 해당 글의 Gutenberg 편집 화면을 엽니다.
6. 상단 `게시/업데이트` 근처의 `Threads 문구` 아이콘을 눌러 전용 패널을 열고 `문구 만들기`를 누릅니다.
7. 정상 생성은 Composer API 호출 1회를 사용합니다. 결과가 500자를 넘을 때만 같은 원문을 바탕으로 한 번 더 줄입니다.
8. 결과가 기대와 다르면 패널 아래의 `생성 단계 진단 (관리자 전용)`에서 Composer 원본, 길이 보정 결과와 최종 문구를 비교합니다.
9. Threads 계정이 연결돼 있으면 자동 게시 결과와 링크가 표시됩니다. 연결돼 있지 않으면 `복사`해 직접 업로드합니다.

상단 아이콘, 패널과 REST 요청은 해당 글을 편집할 수 있는 `manage_options` 권한 사용자에게만 제공됩니다.

진단 화면은 원문 전문, 근거 인용문, API 응답 ID와 키를 표시하지 않습니다. 생성 중에는 저장된 단계만 2.5초 간격으로 다시 조회합니다.

모델은 URL을 만들지 않습니다. 링크 포함을 켜면 PHP가 공개 글 링크와 선택적인 UTM을 문구 끝에 붙이고, 이 링크 길이까지 Threads 500자 제한에 포함해 계산합니다.

## 브랜드 썸네일

`설정 → 브랜드 썸네일`에서 기능·로고·브랜드 색상을 정합니다. 별도 로고를 고르지 않으면 현재 테마의 사이트 로고를 사용합니다. 글 편집 화면의 `브랜드 썸네일` 패널에서는 최대 두 줄 문구, 이미지 초점과 ALT를 정합니다. ALT는 Rank Math의 첫 번째 포커스 키워드를 기본값으로 사용하며 직접 작성한 값이 항상 우선합니다.

### CTA 버튼 색상

0.9.3부터 새 설치의 브랜드 색상과 CTA 배경은 여행허브 청록색 `#0F766E`, 글자는 흰색입니다. `설정 → 브랜드 썸네일 → 브랜드 색상`을 바꾸면 별도 색상을 지정하지 않은 CTA와 새로 만드는 브랜드 썸네일에 적용됩니다. 기존에 저장한 브랜드 색상은 업데이트해도 유지하며, 이미 생성한 이미지 파일을 일괄 재생성하지 않습니다.

버튼을 선택한 뒤 오른쪽 블록 설정의 `버튼 색상`에서 배경색·글자색을 각각 고를 수 있습니다. 설정·스타일 탭이 나뉜 편집기에서는 스타일 탭에 표시됩니다. 패널의 점 세 개 옵션 메뉴에서 해당 색상을 초기화하면 사이트 브랜드 색상·흰색 글자로 돌아갑니다. 충분히 대비되는 두 색상을 사용하세요. 색상 정보는 기존 블록에 선택 속성으로 저장하고, 링크·광고 속성은 유지합니다. 펄스·마우스 오버 그림자에도 선택한 배경색을 적용하며 방문자용 JavaScript는 추가하지 않습니다.

대표이미지를 저장하면 원본은 건드리지 않고 업로드 폴더의 `personal-cta-social` 디렉터리에 JPG 4종을 새로 만듭니다. 1200×630은 로고와 최대 두 줄 문구가 있는 Facebook·Threads·X용이고, 1200×675·1200×900·1200×1200은 글자와 로고가 없는 Google·Article용입니다. `AI 배경 생성`은 설정된 OpenAI 키로 글 내용에 맞는 글자 없는 배경을 한 번 생성한 뒤 이 4종으로 가공하고, 완성된 1200×630 이미지를 미디어 라이브러리에 등록해 대표이미지로 설정합니다. 저장할 때 자동으로 OpenAI를 호출하지 않으므로 버튼을 누른 경우에만 이미지 API 비용이 발생합니다.

Rank Math가 활성화되어 있으면 1200×630 JPG를 Facebook·Threads용 Open Graph 이미지와 X 이미지로 사용하고, Article 스키마의 `image`에는 1:1·4:3·16:9 JPG를 연결합니다. 글의 Rank Math 소셜 탭에서 이미지를 직접 지정한 경우에는 직접 지정한 소셜 이미지가 우선합니다.

AI 배경은 `gpt-image-2`의 1920×1008 JPG로 생성합니다. 공급자 응답이 실패하거나 차단되면 기존 대표이미지는 그대로 남으며 오류만 표시합니다. 이미지 생성에는 계정 상태에 따라 조직 인증이나 사용 가능한 결제 한도가 필요할 수 있습니다. 공식 동작과 제한은 [OpenAI 이미지 생성 문서](https://developers.openai.com/api/docs/guides/image-generation)를 참고하세요.

## 본문 AI 이미지

Gutenberg 오른쪽 문서 설정의 `본문 AI 이미지`에서 H2/H3 소제목을 최대 5개 고른 뒤 `선택 N개 생성`을 누릅니다. 플러그인은 글 제목, 해당 소제목과 다음 소제목 전까지의 문맥을 사용해 글자·로고·워터마크·가짜 UI가 없는 1200×800 정보성 JPG를 한 장씩 만들고, 미디어 라이브러리에 등록한 뒤 각 소제목 바로 아래에 일반 `이미지` 블록으로 넣습니다. 기존에 직접 넣은 이미지는 교체하지 않습니다.

같은 제목·소제목·문맥으로 다시 실행하면 기존 미디어를 재사용하므로 API 비용이 다시 들지 않습니다. 다른 결과가 필요할 때만 `같은 내용도 새 이미지로 다시 만들기`를 켭니다. ALT는 소제목에서 자연스럽게 만들며 키워드를 반복해서 넣지 않습니다. 생성 결과를 확인한 뒤 글의 `업데이트` 또는 `저장`을 눌러 본문 변경을 확정합니다.

미디어 원본은 서비스 호환성이 높은 JPG로 보관합니다. Converter for Media 같은 이미지 변환 플러그인이 활성화되어 있으면 그 플러그인이 방문자 브라우저에 WebP/AVIF 파생 이미지를 전달하도록 맡기므로, 이 기능이 별도로 AVIF 파일을 만들지는 않습니다.

## 일상글 하루 5개

Threads 계정 연결 후 `일상글 자동 게시 → 하루 5개`를 켜면 다음 게시 가능일의 문구 5개를 OpenAI 요청 한 번으로 생성합니다. 개인 메모가 없는 기본 모드에서는 실제 방문·구매·대화·직장 사건을 지어내지 않고, 40대의 차분한 생활 관찰과 생각을 작성합니다.

다섯 문구는 서로 다른 주제·구조·첫 문장으로 만들고 07:00~24:00 사이 넓은 시간대에 무작위로 배치합니다. WP-Cron이 자정 이후 늦게 실행돼도 07시 전에는 게시하지 않고 다시 미룹니다. 게시된 최근 20개 문구와 메타데이터는 중복 방지 입력으로만 저장하며 API 키나 원문 비밀은 포함하지 않습니다.

## 생성 작업과 Cron

정보글 문구 생성은 OpenAI Composer 호출 한 번으로 끝나며, 500자 초과 시에만 축약 호출을 다음 WP-Cron 작업으로 이어서 실행합니다. 일상글은 하루 5개를 한 요청으로 생성하고 각 게시 시각을 WP-Cron에 예약합니다. 방문량이 적은 운영 서버에서는 시스템 Cron으로 WordPress의 due event를 1분마다 실행하는 구성이 안정적입니다.

```cron
* * * * * cd /var/www/wordpress && wp cron event run --due-now --quiet
```

경로는 실제 WordPress 설치 경로로 바꿉니다. 시스템 Cron이 정상 동작하는 것을 확인한 뒤에만 WordPress 자체 Cron 비활성화를 고려하세요.

## 테스트와 빌드

```powershell
& .\tests\verify-pulse-button.ps1
node .\tests\verify-pulse-button-editor.cjs

$mount = "type=bind,source=$PWD,target=/app,readonly"
docker run --rm --mount $mount -w /app php:7.4-cli sh -lc "find . -path ./dist -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 php -l && php tests/verify-github-updater.php && php tests/verify-pulse-button-render.php && php tests/verify-social-thumbnail.php && php tests/verify-inline-images.php && php tests/verify-threads.php && php tests/verify-photo-gallery.php && php tests/verify-related-posts.php"

& .\scripts\build-plugin.ps1
```

빌드 ZIP은 `personal-cta-blocks.php`, `blocks/`, `includes/`, `assets/`만 포함합니다. `tests/`, 문서, 비밀 파일은 포함하지 않습니다.

## 다음 버전 배포

1. `personal-cta-blocks.php`, `blocks/pulse-button/block.json`, `blocks/pulse-button/editor.asset.php` 버전을 같은 값으로 올립니다.
2. `main`에 커밋·푸시하고 CI를 확인합니다.
3. 같은 버전의 태그를 푸시합니다. 예: `git tag v0.7.1` 후 `git push origin v0.7.1`.

태그가 푸시되면 GitHub Actions가 설치 가능한 `personal-cta-blocks-{version}.zip`을 만들고 GitHub Release에 올립니다. 플러그인 폴더명과 메인 파일명은 기존 자동 업데이트 호환성을 위해 변경하지 않습니다.
