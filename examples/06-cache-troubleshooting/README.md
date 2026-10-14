# 6장 예제 — OPcache/캐시 리셋 스크립트

[6장 본문](../../chapters/06-cache-troubleshooting.md)에서 다루는, php-cli가 막힌 환경에서 캐시를 초기화하기 위한 1회성 스크립트 두 개입니다. 실제로 로컬 CI4 프로젝트에 올려서 동작을 확인했습니다.

## 파일 설명

| 파일 | 역할 |
|---|---|
| `opcache_reset.php` | PHP OPcache를 초기화 (`opcache_reset()`) — CI4와 무관하게 순수 PHP만으로 동작 |
| `cache_clear.php` | CI4 자체 캐시를 초기화 (`cache()->clean()`) — `CodeIgniter\Boot::bootWorker()`로 라우팅 없이 프레임워크 서비스만 부팅 |

## 사용법 (공통)

1. 파일을 프로젝트 문서 루트(`index.php`와 같은 위치)에 복사한다
2. 파일 안의 `$TOKEN = 'CHANGE_ME_TO_A_RANDOM_VALUE';`를 추측 불가능한 값으로 바꾼다

   ```bash
   php -r "echo bin2hex(random_bytes(24));"
   ```

3. `https://your-domain.com/opcache_reset.php?token=<위 값>` 으로 접속해 확인한다
4. **확인 즉시 서버에서 파일을 삭제한다**

## 왜 토큰 체크가 필요한가

이 스크립트들은 인증 없이 그냥 올려두면 **누구나 접근해서 캐시를 마음대로 초기화시킬 수 있는 통로**가 됩니다. 토큰 체크 자체도 완벽한 보안은 아니지만(URL에 토큰이 남을 수 있음), 적어도 무작위로 접근하는 봇/스캐너로부터는 보호됩니다. 가장 안전한 건 **확인 후 바로 삭제하는 것**이고, 토큰은 그 사이의 최소한의 안전장치입니다.

## `cache_clear.php`의 경로 주의사항

`require FCPATH . '../app/Config/Paths.php';` 부분은 **프로젝트 구조에 따라 경로를 조정해야 합니다.** 이 책의 예시 앱처럼 문서 루트가 `www/`인 경우와 기본 CI4 구조(`public/`)인 경우 상대 경로가 다를 수 있습니다. 실제 `index.php`가 이 파일을 어떻게 로드하는지 참고해서 같은 경로를 쓰면 됩니다.

## 원본 출처

이 책을 위해 새로 작성하고 로컬 CI4 프로젝트에서 직접 동작 확인했습니다.
