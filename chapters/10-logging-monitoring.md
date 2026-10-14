# 10장. 로깅과 모니터링

여기서부터 4부입니다. 어떤 환경(공유 호스팅이든 Docker든)에 배포했든 상관없이, 운영 중인 서비스에는 똑같이 필요한 것들을 다룹니다. 먼저 **문제가 생겼을 때 알 수 있는 방법**부터입니다.

## 로그 레벨을 운영에 맞게 조절한다

CI4의 `app/Config/Logger.php`는 환경에 따라 기본 임계값이 자동으로 달라집니다.

```php
public $threshold = (ENVIRONMENT === 'production') ? 4 : 9;
```

숫자가 **낮을수록 더 심각한 로그만** 남습니다 (1=Emergency \~ 9=All). 운영에서 기본값인 4(Runtime Errors 이상)는 합리적인 출발점입니다. 이보다 낮추면(예: Critical 이상인 3) 모니터링이 둔감해지고, 반대로 9(All)로 운영에서 그대로 두면 info/debug 로그까지 전부 쌓여서 **며칠 안에 로그 파일이 수백 MB로 불어나고, 정작 중요한 에러를 찾기 어려워집니다.**

```bash
# 로그 파일 크기를 주기적으로 확인하는 습관
du -sh writable/logs/
```

## 로그만 쌓아두면 아무 소용 없다 — 알림까지 연결하기

로그 파일은 "누가 열어보기 전까지" 조용히 쌓이기만 합니다. 운영 중인 서비스라면 **심각한 에러가 나면 사람에게 바로 알려주는** 장치가 필요합니다. CI4는 커스텀 로그 핸들러를 만들 수 있게 해주므로, Slack(또는 Discord) 웹훅으로 critical 이상 로그를 바로 쏘는 핸들러를 직접 만들 수 있습니다.

```php
<?php
// app/Libraries/SlackLogHandler.php

namespace App\Libraries;

use CodeIgniter\Log\Handlers\BaseHandler;
use CodeIgniter\Log\Handlers\HandlerInterface;

class SlackLogHandler extends BaseHandler implements HandlerInterface
{
    public function handle($level, $message): bool
    {
        $webhookUrl = env('SLACK_LOG_WEBHOOK_URL');
        if (empty($webhookUrl)) {
            return true;   // 웹훅 미설정 시 조용히 무시 — 로그 자체는 FileHandler가 이미 처리
        }

        $payload = json_encode([
            'text' => sprintf("[%s] %s\n%s", strtoupper($level), date('Y-m-d H:i:s'), $message),
        ]);

        // 로깅 중 네트워크 호출이 느려지거나 실패해도 요청 자체는 막지 않는다
        try {
            \Config\Services::curlrequest()->post($webhookUrl, [
                'body'    => $payload,
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 3,
            ]);
        } catch (\Throwable) {
            // 알림 실패는 조용히 넘어간다 — 알림 시스템 장애가 서비스 장애로 번지면 안 됨
        }

        return true;
    }
}
```

```php
// app/Config/Logger.php
public array $handlers = [
    FileHandler::class => [
        'handles' => ['critical', 'alert', 'emergency', 'debug', 'error', 'info', 'notice', 'warning'],
    ],
    \App\Libraries\SlackLogHandler::class => [
        'handles' => ['critical', 'alert', 'emergency'],   // 정말 심각한 것만 알림으로
    ],
];
```

**모든 로그 레벨을 알림으로 보내지 마세요.** `info`/`notice`까지 Slack으로 쏘면 금방 알림 피로(알림이 너무 많아서 결국 아무도 안 보게 되는 상태)가 옵니다. critical 이상만 알림으로, 나머지는 파일 로그로 충분합니다.

## 헬스체크 엔드포인트 만들기

"서비스가 살아있는가"를 외부에서 주기적으로 확인할 수 있는 엔드포인트를 하나 두면, 외부 모니터링 서비스(UptimeRobot, Better Uptime 등 무료 티어가 있는 곳들)와 바로 연결할 수 있습니다.

```php
// app/Controllers/Health.php
namespace App\Controllers;

class Health extends BaseController
{
    public function index()
    {
        $dbOk = true;
        try {
            db_connect()->query('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        $status = $dbOk ? 200 : 503;

        return $this->response->setStatusCode($status)->setJSON([
            'status' => $dbOk ? 'ok' : 'degraded',
            'db'     => $dbOk,
            'time'   => date('c'),
        ]);
    }
}
```

```php
$routes->get('health', 'Health::index');
```

외부 모니터링 서비스에 이 URL을 등록해두면, DB 연결이 끊기거나 서버가 응답을 멈추는 순간 바로 알림을 받을 수 있습니다. 6장에서 다룬 "뭔가 이상한데 원인을 모르겠다" 상황을, 사용자가 신고하기 전에 먼저 알아차리게 해주는 안전망입니다.

## 체크리스트

- [ ] 운영 로그 임계값이 9(All)로 방치되어 있지 않은가
- [ ] 로그 파일 크기를 주기적으로 확인하는 습관(또는 자동 로테이션)이 있는가
- [ ] critical 이상의 로그가 실제로 사람에게 알림이 가는가 (Slack 웹훅 테스트를 직접 한 번 트리거해봤는가)
- [ ] 외부 모니터링 서비스가 헬스체크 엔드포인트를 주기적으로 확인하고 있는가

다음 장에서는 서비스 성능을 높이기 위한 캐싱 전략을 다룹니다.
