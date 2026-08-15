# Personal CTA Blocks

WordPress용 펄스 CTA 블록과 AI Threads 문구 생성·자동 게시 기능을 한 플러그인으로 제공합니다.

- 글 편집기에서 `/ㅂㅌ`으로 반응형 CTA 버튼 추가
- CTA 버튼별 새 탭, `nofollow`, `sponsored` 링크 설정
- 글 편집기 상단의 `Threads 문구` 아이콘에서 문구 생성
- 저장된 원문 전체와 관리자가 등록한 합격 예시를 한 번에 읽는 AI Composer
- 정상 생성은 OpenAI 호출 1회, 500자 초과일 때만 한 번 축약
- 문체 취향이 달라도 사용할 수 있는 문구는 버리지 않고 관리자에게 표시
- Threads 계정 연결 시 정보글 문구 자동 게시, 미연결 시 기존 복사 방식 유지
- 매일 일상글 5개를 한 번에 생성해 07:00~24:00 사이 불규칙 예약 게시
- 최근 일상글 20개의 주제·구조·첫 문장·끝맺음을 다음 생성에 반영
- 원문 링크와 선택적인 UTM을 복사 문구 끝에 자동 추가

Threads 기능과 일상글 자동 게시는 기본적으로 꺼져 있으며, 일반 방문자에게는 패널·스크립트·외부 API 호출이 노출되지 않습니다. Meta 계정을 연결하지 않으면 어떠한 자동 게시도 하지 않습니다.

## 요구 사항

- WordPress 6.3 이상
- PHP 7.4 이상
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

`설정 → Threads 문구`에서 Threads App ID와 App Secret을 저장한 뒤 `Meta에서 Threads 계정 연결`을 누릅니다. Meta 앱에는 설정 화면에 표시된 OAuth 리디렉션 URI를 한 글자도 바꾸지 않고 등록해야 합니다. 이미 장기 액세스 토큰과 Threads 사용자 ID가 있다면 같은 화면의 직접 연결 입력란도 사용할 수 있습니다.

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

$mount = "type=bind,source=$PWD,target=/app,readonly"
docker run --rm --mount $mount -w /app php:7.4-cli sh -lc "find . -path ./dist -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 php -l && php tests/verify-github-updater.php && php tests/verify-threads.php"

& .\scripts\build-plugin.ps1
```

빌드 ZIP은 `personal-cta-blocks.php`, `blocks/`, `includes/`, `assets/`만 포함합니다. `tests/`, 문서, 비밀 파일은 포함하지 않습니다.

## 다음 버전 배포

1. `personal-cta-blocks.php`, `blocks/pulse-button/block.json`, `blocks/pulse-button/editor.asset.php` 버전을 같은 값으로 올립니다.
2. `main`에 커밋·푸시하고 CI를 확인합니다.
3. 같은 버전의 태그를 푸시합니다. 예: `git tag v0.7.0` 후 `git push origin v0.7.0`.

태그가 푸시되면 GitHub Actions가 설치 가능한 `personal-cta-blocks-{version}.zip`을 만들고 GitHub Release에 올립니다. 플러그인 폴더명과 메인 파일명은 기존 자동 업데이트 호환성을 위해 변경하지 않습니다.
