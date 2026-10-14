# 12장. 보안 강화와 의존성 관리

## 내가 쓴 코드가 아니라, 내가 쓴 패키지가 뚫린다

실무에서 발생하는 보안 사고의 상당수는 직접 짠 코드가 아니라 **의존성(composer 패키지)의 알려진 취약점**에서 시작됩니다. 내 코드는 완벽해도, `composer.json`에 박아둔 패키지 버전이 몇 달 전에 고정된 채로 방치되어 있다면 그 자체가 위험입니다.

```bash
composer audit
```

이 명령 하나로 현재 설치된 패키지 중 알려진 보안 취약점(CVE)이 있는 게 있는지 바로 확인할 수 있습니다. CI/CD 파이프라인(8장)에 이 명령을 추가해두면, 새로운 취약점이 발견됐을 때 빌드가 실패하도록 만들 수 있습니다.

```yaml
# .github/workflows/ci.yml 에 추가
- name: Security audit
  run: composer audit
```

## GitHub Dependabot으로 자동 알림 받기

저장소에 Dependabot을 켜두면, 쓰고 있는 패키지에 새 취약점이 보고될 때마다 GitHub이 알아서 알려주고, 패치 버전으로 올리는 PR까지 자동으로 만들어줍니다.

```yaml
# .github/dependabot.yml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
```

보안 패치 PR이 올라오면 **바로 머지하지 말고 CI(8장)가 통과하는지 먼저 확인하세요.** 패치 버전이라도 간혹 동작이 미묘하게 달라지는 경우가 있습니다.

```bash
gh api repos/your-org/your-repo/dependabot/alerts
```

이 명령으로 현재 열려 있는 보안 경고 목록을 터미널에서 바로 확인할 수도 있습니다.

## 보안 헤더 설정

응답 헤더 몇 개만 추가해도 흔한 공격 벡터 일부를 막을 수 있습니다. CI4 필터로 전역 적용합니다.

```php
// app/Filters/SecurityHeaders.php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        // HTTPS가 적용된 뒤에만 추가 (9장)
        $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
```

```php
// app/Config/Filters.php
public array $globals = [
    'after' => ['secureheaders'],
];
```

- `X-Content-Type-Options: nosniff`: 브라우저가 응답의 MIME 타입을 임의로 추측하지 못하게 막습니다.
- `X-Frame-Options: DENY`: 내 사이트가 다른 사이트의 `<iframe>` 안에 몰래 삽입되는 클릭재킹 공격을 막습니다.
- `Strict-Transport-Security`: 한 번 HTTPS로 접속한 브라우저가 이후로는 HTTP로 접속하지 않게 강제합니다. **HTTPS가 확실히 적용된 뒤에만 켜세요** — 아직 HTTPS가 불안정한 상태에서 이 헤더를 켜면, 브라우저가 한동안 HTTP 접속 자체를 거부할 수 있습니다.

## Rate Limiting을 전체 API에 일관되게 적용

1권 11장에서 AI 호출 엔드포인트에 `Throttler` 필터를 적용했습니다. 이 패턴을 **공개된 쓰기 작업 전부**(글쓰기, 댓글, 회원가입, 로그인 시도)에 일관되게 적용하는 게 운영 단계에서는 중요합니다.

```php
$routes->post('auth/login', 'Auth::login', ['filter' => 'write-throttle:5,60']);     // 1분에 5회 — 무차별 대입 방어
$routes->post('board/store', 'Board::store', ['filter' => 'write-throttle:3,60']);
```

로그인처럼 **공격 대상이 되기 쉬운 엔드포인트**일수록 더 엄격한 제한을 걸어야 합니다.

## 체크리스트

- [ ] `composer audit`을 CI 파이프라인에 넣어뒀는가
- [ ] Dependabot이 활성화되어 있고, 올라오는 PR을 주기적으로 확인하는가
- [ ] 보안 헤더 필터가 전역으로 적용되어 있는가 (HSTS는 HTTPS 안정화 이후에)
- [ ] 로그인, 회원가입처럼 공격 대상이 되기 쉬운 엔드포인트에 더 엄격한 Rate limiting이 걸려 있는가

다음 장에서는 사고가 나도 복구할 수 있도록, 백업과 복구 전략을 다룹니다.
