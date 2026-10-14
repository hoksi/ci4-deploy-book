# 2장. CI4 운영 환경 설정 기초

## CI_ENVIRONMENT가 바꾸는 것들

`.env`의 `CI_ENVIRONMENT` 값(`development`/`testing`/`production`)에 따라 CI4는 `app/Config/Boot/{environment}.php` 파일을 로드합니다. 로컬 개발 중엔 거의 신경 쓸 일이 없지만, 운영에 올릴 때는 이 차이를 정확히 알아야 합니다.

```php
// app/Config/Boot/development.php
error_reporting(E_ALL);
ini_set('display_errors', '1');          // 에러를 화면에 그대로 띄움
defined('SHOW_DEBUG_BACKTRACE') || define('SHOW_DEBUG_BACKTRACE', true);
defined('CI_DEBUG') || define('CI_DEBUG', true);
```

```php
// app/Config/Boot/production.php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');          // 에러를 화면에 안 띄움 — 대신 일반화된 에러 페이지
defined('CI_DEBUG') || define('CI_DEBUG', false);
```

`display_errors`가 꺼지면 스택 트레이스, 파일 경로, 변수 값 같은 민감한 정보가 사용자에게 노출되지 않습니다. **운영 서버에서 `CI_ENVIRONMENT`가 `production`으로 설정되어 있는지는 배포 체크리스트에서 가장 먼저 확인해야 할 항목입니다.** 개발 모드로 그대로 운영에 올라가면, 에러 화면 하나가 DB 접속 정보나 내부 파일 경로를 그대로 보여주는 보안 사고가 됩니다.

```ini
CI_ENVIRONMENT = production
```

## 디버그바는 자동으로 꺼진다 — 하지만 확인은 해야 한다

CI4의 디버그바(화면 하단에 뜨는 쿼리/시간 정보 패널)는 `CI_DEBUG`가 `false`면 자동으로 비활성화됩니다. `production.php`가 이미 이걸 처리하므로 따로 끌 필요는 없지만, **배포 후 반드시 눈으로 확인하세요** — `.env`가 잘못 복사되어 `CI_ENVIRONMENT`가 여전히 `development`로 남아있는 채로 배포되는 실수는 생각보다 자주 일어납니다.

## .env 운영값 체크리스트

```ini
#--------------------------------------------------------------------
CI_ENVIRONMENT = production

#--------------------------------------------------------------------
app.baseURL = 'https://your-domain.com'          # 끝에 슬래시 없이
app.forceGlobalSecureRequests = true             # HTTP 요청을 HTTPS로 강제 리다이렉트

#--------------------------------------------------------------------
# 세션 쿠키는 HTTPS로만 전송
session.cookieSecure = true

#--------------------------------------------------------------------
# 운영에서는 에러/경고 이상만 로그에 남기기 (너무 상세하면 로그가 금방 꽉 참)
logger.threshold = 4
```

- `app.baseURL`: 운영 도메인과 정확히 일치해야 합니다. 로컬 개발용 URL(`http://localhost:8080`)이 그대로 남아있으면 리다이렉트·링크가 전부 깨집니다.
- `app.forceGlobalSecureRequests`: HTTPS를 쓰는 서비스라면 켜두세요. 9장에서 HTTPS를 실제로 설정하는 법을 다룹니다.
- `session.cookieSecure`: HTTPS가 아닌 연결에서는 세션 쿠키 자체를 보내지 않게 합니다. HTTPS 없이 이 값을 켜면 로그인 자체가 안 되니, HTTPS 설정과 함께 켜야 합니다.
- `logger.threshold`: 레벨 숫자가 낮을수록 더 심각한 로그만 남습니다. 운영에서 `debug`/`info` 레벨까지 전부 남기면 로그 파일이 금방 비대해지고, 정작 중요한 에러를 찾기 어려워집니다. (10장에서 로깅 전략을 더 다룹니다.)

## 암호화 키는 운영과 로컬이 달라야 한다

```ini
encryption.key = hex2bin(...)
```

`php spark key:generate`로 생성하는 이 값은 세션/암호화에 쓰이는 비밀키입니다. **로컬 개발용 키를 운영에 그대로 가져다 쓰지 마세요** — 로컬 저장소에 커밋된 적이 있다면(설령 지금은 `.gitignore`로 막아뒀어도, 과거 커밋 기록에 남아있을 수 있습니다) 그 키는 이미 유출된 것으로 간주하고 운영용으로 새로 생성해야 합니다.

## 체크리스트

- [ ] 운영 서버의 `CI_ENVIRONMENT`가 정확히 `production`인가 (배포 직후 디버그바가 안 보이는지 직접 확인)
- [ ] `app.baseURL`이 실제 운영 도메인과 정확히 일치하는가
- [ ] `encryption.key`가 로컬 개발용과 다른 값인가
- [ ] `.env` 파일 자체가 git에 커밋되지 않았는가 (`git log --all -- .env`로 과거 이력까지 확인)

다음 장부터는 2부입니다 — 가장 제약이 많은 환경인 공유 호스팅부터 다룹니다.
