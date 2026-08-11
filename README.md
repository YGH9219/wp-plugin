# Personal CTA Blocks

WordPress용 펄스 CTA 블록과 Threads 내보내기 기능을 한 플러그인으로 제공합니다.

- 글 편집기에서 `/ㅂㅌ`으로 반응형 CTA 버튼 추가
- 발행된 글의 관리자바에서 `Threads로 내보내기`
- 원문 사실 분석 → 독립 초안 3개 → 편집장 재작성
- 미리보기·직접 수정·재생성·복사·수동 게시
- 선택적인 최초 발행 자동 게시와 독립 근거 검증
- Meta 게시 ID, 최종 문안, 원문 hash를 저장해 중복 게시 방지

Threads 기능은 기본적으로 꺼져 있으며, 일반 방문자 요청에는 관리자 스크립트나 외부 API 호출을 추가하지 않습니다.

## 요구 사항

- WordPress 6.3 이상
- PHP 7.4 이상
- OpenAI API 키
- Threads API 사용 사례가 설정된 Meta 앱과 Threads 계정
- 자동 게시를 사용할 경우 안정적으로 실행되는 WP-Cron

## 비밀값 설정

비밀값은 플러그인 설정이나 Git 저장소가 아니라 `wp-config.php` 또는 서버 환경변수에 둡니다.

```php
define( 'PERSONAL_CTA_OPENAI_API_KEY', '...');
define( 'PERSONAL_CTA_THREADS_APP_ID', '...');
define( 'PERSONAL_CTA_THREADS_APP_SECRET', '...');
define( 'PERSONAL_CTA_THREADS_MASTER_KEY', '64자리 이상의 무작위 문자열');
```

Meta OAuth 대신 이미 발급한 계정 토큰을 직접 관리하는 환경에서는 다음 두 값도 사용할 수 있습니다.

```php
define( 'PERSONAL_CTA_THREADS_USER_ID', '...');
define( 'PERSONAL_CTA_THREADS_ACCESS_TOKEN', '...');
```

OpenAI 환경변수는 `OPENAI_API_KEY`, Meta 환경변수는 위 상수와 같은 이름을 사용합니다. 키와 토큰은 관리자 HTML, JavaScript, REST 응답, 오류 로그에 출력하지 않습니다.

## 설정과 사용

1. 플러그인을 설치하고 활성화합니다.
2. WordPress 관리자에서 `설정 → Threads 내보내기`를 엽니다.
3. Threads 기능을 켜고 링크 포함 방식, UTM, 자동 게시 여부를 저장합니다.
4. Meta 앱을 사용하는 경우 설정 화면에 표시된 OAuth 리디렉션 URL을 Meta 앱에 등록한 뒤 계정을 연결합니다.
5. 발행된 글을 로그인한 관리자 상태로 열고 관리자바의 `Threads로 내보내기`를 누릅니다.
6. AI 초안을 미리 보고 필요하면 고친 뒤 게시합니다. `생성 후 게시`를 선택하면 근거 검증을 통과한 문안만 게시됩니다.

모델은 URL을 만들지 않습니다. 공개 글 링크와 선택적인 UTM은 게시 직전에 PHP가 결정적으로 붙입니다. 수동으로 고친 문안은 기존 검증 결과를 무효화하며, 스타일 예시로 고정한 문안만 이후 생성 요청에 사용됩니다.

## 자동 게시와 Cron

자동 게시를 켜면 글의 최초 `draft/future → publish` 전환만 큐에 넣습니다. 이미 발행된 글을 수정하거나 다시 발행 상태로 바꿔도 자동으로 중복 게시하지 않습니다.

방문량이 적은 운영 서버에서는 시스템 Cron으로 WordPress의 due event를 1분마다 실행하는 구성이 안정적입니다.

```cron
* * * * * cd /var/www/wordpress && wp cron event run --due-now --quiet
```

경로는 실제 WordPress 설치 경로로 바꿉니다. 시스템 Cron이 정상 동작하는 것을 확인한 뒤에만 WordPress 자체 Cron 비활성화를 고려하세요.

Meta 게시 요청의 결과가 불명확한 timeout에서는 같은 요청을 자동 재전송하지 않습니다. 먼저 컨테이너와 최근 게시물을 조회하고, 확정할 수 없으면 `확인 필요` 상태로 멈춰 관리자가 복구하도록 합니다.

## 테스트와 빌드

```powershell
& .\tests\verify-pulse-button.ps1

$mount = "type=bind,source=$PWD,target=/app,readonly"
docker run --rm --mount $mount -w /app php:7.4-cli sh -lc "find . -path ./dist -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 php -l && php tests/verify-github-updater.php && php tests/verify-threads.php"

& .\scripts\build-plugin.ps1
```

빌드 ZIP은 `personal-cta-blocks.php`, `blocks/`, `includes/`, `assets/`만 포함합니다. `tests/`, 문서, 비밀 파일은 포함하지 않습니다.

## 다음 버전 배포

1. `personal-cta-blocks.php`, `blocks/pulse-button/block.json`, `blocks/pulse-button/editor.asset.php` 버전을 같은 값으로 올립니다.
2. `main`에 커밋·푸시하고 CI를 확인합니다.
3. 같은 버전의 태그를 푸시합니다. 예: `git tag v0.2.0` 후 `git push origin v0.2.0`.

태그가 푸시되면 GitHub Actions가 설치 가능한 `personal-cta-blocks-{version}.zip`을 만들고 GitHub Release에 올립니다. 플러그인 폴더명과 메인 파일명은 기존 자동 업데이트 호환성을 위해 변경하지 않습니다.
