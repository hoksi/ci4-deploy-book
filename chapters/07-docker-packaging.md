# 7장. Docker로 패키징하기

여기서부터 3부입니다. 2부에서 다룬 제약(php-cli 차단 등)은 **컨테이너로 패키징하는 순간 대부분 사라집니다** — 컨테이너 안에서는 내가 설치한 PHP, 내가 켠 확장, 내가 원하는 CLI 전부 내 마음대로입니다. 대신 "컨테이너를 어떻게 만들고 돌릴 것인가"라는 새로운 작업이 생깁니다.

## Dockerfile: 멀티스테이지로 가볍게

composer 의존성을 설치하는 단계와 실제로 앱을 실행하는 단계를 분리하면, 최종 이미지에 개발 도구(composer 자체, 테스트용 패키지)가 남지 않아 이미지가 가벼워집니다.

```dockerfile
# Dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

FROM php:8.3-fpm AS runtime
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
        libicu-dev libzip-dev zlib1g-dev libpng-dev \
    && docker-php-ext-install intl mbstring zip gd pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

RUN chown -R www-data:www-data writable
EXPOSE 9000
CMD ["php-fpm"]
```

- `intl`, `mbstring`: 한글/다국어 문자열 처리에 필수
- `pdo_mysql`: MySQL을 쓴다면 (SQLite만 쓴다면 `pdo_sqlite`로 교체 — 보통 php 베이스 이미지에 기본 포함되어 있는지 확인)
- `zip`, `gd`: 파일 업로드/이미지 처리 예제를 쓴다면 필요

`.dockerignore`로 불필요한 파일이 이미지에 들어가지 않게 막습니다.

```
.git
.env
vendor
writable/cache/*
writable/logs/*
writable/session/*
tests
```

**`.env`를 반드시 제외하세요** — 이미지에 비밀값이 박혀버리면, 그 이미지를 받는 모든 사람이 운영 비밀키를 갖게 됩니다. 환경변수는 컨테이너 실행 시점에 주입합니다(아래 `docker-compose.yml` 참고).

## docker-compose로 로컬에서 운영과 동일하게 재현

PHP-FPM만으로는 HTTP 요청을 직접 못 받으므로, 앞단에 Nginx를 하나 더 둡니다. 9장에서 운영용 Nginx 설정을 더 자세히 다루지만, 여기서는 로컬 재현용 최소 구성만 봅니다.

```yaml
# docker-compose.yml
services:
  app:
    build: .
    volumes:
      - ./writable:/var/www/html/writable
    environment:
      - CI_ENVIRONMENT=production
      - database.default.hostname=db
      - database.default.database=app
      - database.default.username=app
      - database.default.password=${DB_PASSWORD}

  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./www:/var/www/html/www:ro
    depends_on:
      - app

  db:
    image: mysql:8
    environment:
      - MYSQL_DATABASE=app
      - MYSQL_USER=app
      - MYSQL_PASSWORD=${DB_PASSWORD}
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

비밀값(`DB_PASSWORD` 등)은 `.env` 파일이 아니라 **compose가 읽는 별도의 `.env`**(docker-compose 전용, 같은 이름이라 혼동하기 쉬우니 주의)나 `docker secret`, 혹은 배포 플랫폼의 환경변수 설정 화면으로 주입합니다. 이미지 자체에는 값이 들어가지 않습니다.

## 문서 루트 주의사항

1권에서 다뤘듯 이 책이 다루는 예시 앱은 문서 루트가 `public/`이 아니라 `www/`로 바뀌어 있습니다. Nginx 설정의 `root` 지시어(9장에서 자세히 다룹니다)와 Dockerfile의 `WORKDIR`/볼륨 경로가 실제 프로젝트 구조와 일치하는지 반드시 확인하세요. 기본 CI4 구조(`public/`)를 그대로 쓴다면 위 예시에서 `www`를 `public`으로 바꾸면 됩니다.

## 빌드와 실행

```bash
docker compose build
docker compose up -d
docker compose exec app php spark migrate --all
```

컨테이너 안에서는 `php spark`가 **당연히 됩니다** — 이게 2부와 가장 큰 차이입니다. 마이그레이션도, 캐시 클리어도, 전부 평범하게 CLI로 실행하면 됩니다.

## 체크리스트

- [ ] `.env`가 이미지에 포함되지 않고 `.dockerignore`에 들어있는가
- [ ] 멀티스테이지 빌드로 최종 이미지에 불필요한 빌드 도구가 안 남아있는가
- [ ] 문서 루트 경로가 Nginx 설정과 Dockerfile 양쪽에서 실제 프로젝트 구조와 일치하는가
- [ ] 로컬에서 `docker compose up`으로 전체 스택(app+web+db)이 뜨는지 확인했는가

다음 장에서는 이 이미지를 빌드하고 배포하는 과정을 GitHub Actions로 자동화합니다.
