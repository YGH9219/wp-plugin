# Personal CTA Blocks

WordPress용 펄스 CTA 블록과 AI Threads 문구 생성 기능을 한 플러그인으로 제공합니다.

- 글 편집기에서 `/ㅂㅌ`으로 반응형 CTA 버튼 추가
- 글 편집기 상단의 `Threads 문구` 아이콘에서 문구 생성
- 원자 사실 추출 → 콘텐츠 전략·Hook Lab → 서로 다른 구조의 Writer 3명 → 편집장 재작성
- 최종 문체·전환력 심사와 독립 원문 사실 검증을 통과한 문구만 노출
- Writer와 편집장은 SEO 원문 대신 검증된 FACT MAP만 사용
- 문구 재생성·복사 후 Threads에 직접 업로드
- 원문 링크와 선택적인 UTM을 복사 문구 끝에 자동 추가

Threads 기능은 기본적으로 꺼져 있으며, 일반 방문자에게는 패널·스크립트·외부 API 호출이 노출되지 않습니다. Meta API, Threads 토큰, 자동 게시 기능은 사용하지 않습니다.

## 요구 사항

- WordPress 6.3 이상
- PHP 7.4 이상
- OpenAI API 키
- 생성 작업을 이어서 실행할 수 있는 WP-Cron

## OpenAI API 키 설정

`설정 → Threads 문구`에서 API 키를 직접 입력해 저장할 수 있습니다. 저장된 키는 다시 표시하지 않으며 WordPress 보안 키로 암호화해 비자동로드 옵션에 보관합니다. `wp-config.php` 또는 서버 환경변수 설정이 있으면 그 값이 우선합니다.

서버 설정으로 관리하려면 다음처럼 둘 수도 있습니다.

```php
define( 'PERSONAL_CTA_OPENAI_API_KEY', '...');
```

`OPENAI_API_KEY`도 `wp-config.php` 상수 또는 환경변수로 사용할 수 있습니다. 키는 관리자 HTML, JavaScript, REST 응답, 오류 로그에 출력하지 않습니다.

## 설정과 사용

1. 플러그인을 설치하고 활성화합니다.
2. WordPress 관리자에서 `설정 → Threads 문구`를 엽니다.
3. 기능을 켜고 OpenAI API 키, 복사할 원문 링크와 UTM 사용 여부를 저장합니다.
4. 일반 글을 발행한 뒤 PC에서 해당 글의 Gutenberg 편집 화면을 엽니다.
5. 상단 `게시/업데이트` 근처의 `Threads 문구` 아이콘을 눌러 전용 패널을 열고 `문구 만들기`를 누릅니다.
6. 생성 중에는 8단계 진행 상태와 마지막 서버 활동을 확인합니다. 정상 새 생성은 최대 8회의 단계별 API 호출을 사용하며, 오류·보정까지 포함한 한 작업의 안전 상한은 10회입니다.
7. 결과가 기대와 다르면 패널 아래의 `생성 단계 진단 (관리자 전용)`을 열어 FACT MAP, 전략·Hook, Writer 초안 3개, 편집장, 최종 품질, 사실 검증 결과를 비교합니다.
8. 준비된 문구를 `복사`해 Threads에 직접 붙여넣고 업로드합니다.

상단 아이콘, 패널과 REST 요청은 해당 글을 편집할 수 있는 `manage_options` 권한 사용자에게만 제공됩니다.

진단 화면은 원문 전문, 근거 인용문, API 응답 ID와 키를 표시하지 않습니다. 생성 중에는 저장된 단계만 2.5초 간격으로 다시 조회합니다.

모델은 URL을 만들지 않습니다. 링크 포함을 켜면 PHP가 공개 글 링크와 선택적인 UTM을 문구 끝에 붙이고, 이 링크 길이까지 Threads 500자 제한에 포함해 계산합니다.

## 생성 작업과 Cron

문구 생성은 한 번에 여러 OpenAI 호출을 하지 않고, 짧은 단계로 나누어 WP-Cron으로 이어서 실행합니다. 방문량이 적은 운영 서버에서는 시스템 Cron으로 WordPress의 due event를 1분마다 실행하는 구성이 안정적입니다.

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
3. 같은 버전의 태그를 푸시합니다. 예: `git tag v0.5.2` 후 `git push origin v0.5.2`.

태그가 푸시되면 GitHub Actions가 설치 가능한 `personal-cta-blocks-{version}.zip`을 만들고 GitHub Release에 올립니다. 플러그인 폴더명과 메인 파일명은 기존 자동 업데이트 호환성을 위해 변경하지 않습니다.
