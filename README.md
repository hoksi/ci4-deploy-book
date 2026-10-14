# CodeIgniter4 배포와 운영 심화

[1권 — CodeIgniter4로 만드는 AI 웹 서비스](https://github.com/hoksi/ci4-ai-book)의 13장(배포하기)을 한 권으로 확장한 책입니다. "일단 로컬에서 동작하게 만들기"는 끝났다는 전제로, **실제로 서비스를 올리고, 안정적으로 운영하는 법**만 다룹니다.

저렴한 공유 호스팅의 제약(php-cli 차단, 고정된 문서 루트)부터 VPS/클라우드로 확장할 때 필요한 것(Docker, CI/CD, 모니터링)까지, 규모가 커지는 순서대로 다룹니다.

## 대상 독자

- CI4 앱은 로컬에서 돌아가는데, "그래서 이걸 어떻게 세상에 내놓지?"가 막막한 분
- 공유 호스팅의 이런저런 제약 때문에 배포가 생각보다 어려웠던 분
- 서비스가 커지면서 로깅/캐싱/보안을 어떻게 챙겨야 할지 궁금한 분

## 목차

### 1부 — 배포 전에 알아야 할 것

| 챕터 | 내용 |
|---|---|
| [1장. 호스팅 유형과 이 책의 범위](chapters/01-hosting-types.md) | 공유 호스팅 vs VPS/클라우드, 규모별 접근 |
| [2장. CI4 운영 환경 설정 기초](chapters/02-production-config.md) | .env, CI_ENVIRONMENT, 프로덕션 기본값 |

### 2부 — 제약된 공유 호스팅에 배포하기

| 챕터 | 내용 |
|---|---|
| [3장. php-cli가 막힌 환경 진단하기](chapters/03-diagnose-restricted-hosting.md) | 뭐가 되고 안 되는지 먼저 확인하는 법 |
| [4장. Git 기반 배포 워크플로우](chapters/04-git-deploy-workflow.md) | 서버를 git 작업 복사본으로 쓰기 |
| [5장. DB 마이그레이션을 CLI 없이 처리하기](chapters/05-migrate-without-cli.md) | SQLite/MySQL 각각의 전략 |
| [6장. 캐시와 OPcache 트러블슈팅](chapters/06-cache-troubleshooting.md) | "코드를 고쳤는데 반영이 안 돼요" 미스터리 |

### 3부 — VPS/클라우드로 확장하기

| 챕터 | 내용 |
|---|---|
| [7장. Docker로 패키징하기](chapters/07-docker-packaging.md) | CI4 앱을 컨테이너로 |
| [8장. GitHub Actions CI/CD 파이프라인](chapters/08-github-actions-cicd.md) | 테스트 → 빌드 → 배포 자동화 |
| [9장. Nginx 리버스 프록시와 HTTPS](chapters/09-nginx-https.md) | Let's Encrypt, 프록시 버퍼링 설정 |

### 4부 — 운영 안정성 확보하기

| 챕터 | 내용 |
|---|---|
| [10장. 로깅과 모니터링](chapters/10-logging-monitoring.md) | CI4 로그, 외부 모니터링/알림 연동 |
| [11장. 캐싱 전략](chapters/11-caching-strategy.md) | 파일/Redis 캐시, 언제 무엇을 캐시할지 |
| [12장. 보안 강화와 의존성 관리](chapters/12-security-dependencies.md) | Composer 취약점 점검, 보안 헤더, Rate limiting |
| [13장. 백업과 복구](chapters/13-backup-recovery.md) | DB/파일 백업 자동화, 롤백 전략 |
| [14장. 마무리](chapters/14-wrapup.md) | 정리와 체크리스트 |

## 진행 상태

1~14장 전체 집필 완료.

## 라이선스

- **책 본문**: [CC BY-NC-SA 4.0](LICENSE)
- **코드**(`examples/` 등): [MIT License](examples/LICENSE) — 1권과 동일한 이중 라이선스 방침
