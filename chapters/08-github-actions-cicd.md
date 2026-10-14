# 8장. GitHub Actions CI/CD 파이프라인

지금까지는 "배포"를 사람이 직접 트리거했습니다(`git push` 후 서버에서 `git pull`). 이번 장에서는 **테스트 통과 → 이미지 빌드 → 배포**까지를 코드 푸시 한 번으로 자동화합니다.

## CI: 테스트를 먼저 자동화한다

배포 자동화보다 먼저 할 일은 **"머지되기 전에 테스트가 통과하는가"를 자동으로 확인하는 것**입니다. PR을 열 때마다 돌아가도록 설정합니다.

```yaml
# .github/workflows/ci.yml
name: CI

on:
  pull_request:
    branches: [main, dev]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: intl, mbstring, sqlite3, curl, gd
          coverage: none

      - name: Composer install
        run: composer install --no-progress --prefer-dist

      - name: Copy test env
        run: cp env .env

      - name: Run PHPUnit
        run: vendor/bin/phpunit

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --memory-limit=512M
```

이 워크플로우가 실패하면 PR 자체를 머지하지 못하게 GitHub 저장소 설정(Branch protection rules)에서 "이 체크가 통과해야 머지 가능"으로 걸어두세요. 그래야 CI가 장식이 아니라 실제로 지켜지는 규칙이 됩니다.

## CD: main에 머지되면 자동 배포

### 패턴 A — SSH로 직접 git pull (2부에서 다룬 방식의 자동화 버전)

서버에 php-cli와 git이 있다면, 배포 자체는 2부에서 손으로 하던 `git pull`을 SSH로 원격 실행하는 것뿐입니다.

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_SSH_KEY }}
          script: |
            cd ~/www-root
            git pull --ff-only origin main
            composer install --no-dev --optimize-autoloader
            php spark migrate --all
            php spark cache:clear
```

### 패턴 B — Docker 이미지를 빌드해서 레지스트리에 푸시

7장에서 만든 Dockerfile을 쓴다면, 이미지를 빌드해서 레지스트리(GitHub Container Registry, Docker Hub 등)에 올리고 서버는 그 이미지를 받아 재시작하기만 하면 됩니다.

```yaml
jobs:
  build-and-push:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          push: true
          tags: ghcr.io/${{ github.repository }}:latest

      - name: Deploy (pull & restart on server)
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_SSH_KEY }}
          script: |
            docker compose pull
            docker compose up -d
            docker compose exec -T app php spark migrate --all
```

어느 패턴을 쓸지는 결국 7장에서 컨테이너화를 했는지 여부로 갈립니다. 컨테이너를 안 쓴다면 패턴 A로 충분하고, 굳이 Docker를 도입할 필요는 없습니다.

## 시크릿 관리

위 두 워크플로우 모두 `secrets.DEPLOY_HOST`, `secrets.DEPLOY_SSH_KEY` 같은 값을 쓰는데, 이건 **절대 워크플로우 파일에 직접 적지 않습니다.** GitHub 저장소의 **Settings → Secrets and variables → Actions**에서 등록하면, 로그에도 자동으로 마스킹되어 찍힙니다.

- `DEPLOY_SSH_KEY`: 배포 전용으로 새로 생성한 SSH 키 쌍을 쓰세요. 로컬 개발용 개인 키를 그대로 등록하지 마세요 — 만약 저장소 권한이 잘못 설정되어 유출되면, 배포 서버뿐 아니라 그 키로 접근 가능한 다른 곳까지 위험해집니다.
- 서버 쪽 `authorized_keys`에는 이 배포 전용 공개키만 추가하고, 가능하면 `command=` 옵션으로 실행 가능한 명령을 제한하는 것도 고려할 만합니다.

## 체크리스트

- [ ] PR마다 테스트가 자동으로 돌고, 실패 시 머지가 막히는가 (Branch protection rules 확인)
- [ ] 배포용 SSH 키가 로컬 개인 키와 별도로 발급되어 있는가
- [ ] 모든 민감값이 워크플로우 파일이 아니라 GitHub Secrets에 등록되어 있는가
- [ ] 배포 워크플로우에 마이그레이션 실행 단계가 빠지지 않았는가

다음 장에서는 이렇게 배포된 앱 앞에 Nginx를 두고 HTTPS를 적용하는 법을 다룹니다.
