# 9장. Nginx 리버스 프록시와 HTTPS

## 기본 Nginx 설정

PHP-FPM은 HTTP를 직접 받지 못합니다. Nginx가 요청을 받아서 정적 파일은 직접 서빙하고, PHP 요청은 FPM으로 넘기는 구조가 가장 흔합니다.

```nginx
# /etc/nginx/sites-available/your-app.conf
server {
    listen 80;
    server_name your-domain.com;

    root /var/www/html/www;   # 이 책 예시 앱은 문서 루트가 public/이 아니라 www/
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;   # docker-compose 서비스명, 또는 유닉스 소켓 경로
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;   # .env, .git 등 숨김 파일 접근 차단
    }
}
```

`root`는 실제 프로젝트의 문서 루트와 정확히 일치해야 합니다. 기본 CI4 구조(`public/`)를 그대로 쓴다면 `www`를 `public`으로 바꾸세요.

`location ~ /\.` 블록을 빼먹지 마세요 — `.env`나 `.git` 같은 숨김 디렉터리가 웹에서 직접 접근 가능한 사고를 막는, 작지만 중요한 설정입니다.

## 리버스 프록시 버퍼링 — SSE를 쓴다면 반드시 확인

1권 10장에서 Groq 응답을 실시간 스트리밍(SSE)으로 보여주는 기능을 만들었다면, **Nginx의 기본 버퍼링 설정이 이 기능을 완전히 무력화시킬 수 있습니다.** Nginx는 기본적으로 백엔드(PHP-FPM) 응답을 전부 모았다가 한 번에 클라이언트로 내려보내는데, 이러면 스트리밍이 "한 번에 뚝" 떨어지는 것처럼 보여서 스트리밍 효과가 사라집니다.

```nginx
location /examples/chat/stream {
    fastcgi_pass app:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;

    fastcgi_buffering off;      # 핵심 — 이게 꺼져야 스트리밍이 실시간으로 흘러나감
    proxy_buffering off;
    chunked_transfer_encoding off;
}
```

이 설정은 스트리밍 엔드포인트에만 적용하면 됩니다. 사이트 전체에 `fastcgi_buffering off`를 걸면 일반 페이지들의 성능 이점(버퍼링으로 인한 효율)을 불필요하게 잃게 됩니다.

## HTTPS: Let's Encrypt로 무료 인증서 발급

`certbot`을 쓰면 인증서 발급과 Nginx 설정 적용을 거의 자동으로 처리해줍니다.

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

실행하면 certbot이 알아서 위에서 작성한 server 블록을 찾아 `listen 443 ssl;` 설정과 인증서 경로를 추가하고, 80번 포트 요청을 443으로 리다이렉트하는 설정까지 넣어줍니다.

## 인증서 자동 갱신

Let's Encrypt 인증서의 유효기간은 90일입니다. `certbot` 설치 시 자동으로 등록되는 systemd 타이머나 cron 작업이 보통 이미 있지만, 확인해두는 게 좋습니다.

```bash
sudo systemctl status certbot.timer
# 또는
sudo certbot renew --dry-run   # 실제 갱신 없이 갱신 과정만 시뮬레이션
```

갱신이 자동으로 안 되고 있다는 걸 인증서 만료 당일에 알게 되는 것만큼 당황스러운 일도 없습니다. `--dry-run`으로 미리 확인해두세요.

## HTTPS 적용 후 CI4 쪽 설정도 맞추기

2장에서 다룬 값들을 HTTPS에 맞게 켜는 것도 잊지 마세요.

```ini
app.forceGlobalSecureRequests = true
session.cookieSecure = true
```

## 체크리스트

- [ ] `location ~ /\.` 블록으로 숨김 파일 접근이 차단되어 있는가
- [ ] SSE/스트리밍 엔드포인트가 있다면 해당 location에 `fastcgi_buffering off`가 적용되어 있는가 (전체가 아니라 해당 경로만)
- [ ] `certbot renew --dry-run`으로 자동 갱신이 정상 동작하는지 확인했는가
- [ ] HTTPS 적용 후 `app.forceGlobalSecureRequests`, `session.cookieSecure`를 켰는가

3부를 여기서 마칩니다. 4부에서는 어떤 환경에 배포하든 공통으로 필요한 운영 안정성 — 로깅, 캐싱, 보안, 백업을 다룹니다.
