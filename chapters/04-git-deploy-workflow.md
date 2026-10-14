# 4장. Git 기반 배포 워크플로우

## 서버를 git 작업 복사본으로 만들기

3장에서 `git`이 된다는 걸 확인했다면, 가장 간단한 배포 방식은 **서버 디렉터리 자체를 저장소의 clone으로 만들어두는 것**입니다. 최초 1회만 설정하면 그 뒤로는 `git pull` 한 줄로 배포가 끝납니다.

```bash
ssh your_id@your-host.com
cd ~/www-root   # 문서 루트 또는 그 상위 디렉터리
git clone https://github.com/your-id/your-repo.git .
```

비공개 저장소라면 매번 아이디/비밀번호를 치지 않도록 자격 증명을 저장해둡니다.

```bash
git config credential.helper store
git pull   # 이때 한 번만 물어보고, 이후로는 .git-credentials에 저장되어 자동 인증
```

`.git-credentials` 파일은 평문으로 토큰을 담고 있으니, 반드시 서버 쪽 홈 디렉터리 권한(다른 사용자가 못 읽게)을 확인하세요. 가능하면 비밀번호 대신 **개인 접근 토큰**(PAT)이나, 저장소 하나에만 읽기 권한을 주는 **Deploy Key**를 쓰는 게 더 안전합니다.

## 배포는 항상 --ff-only로

```bash
git fetch origin
git pull --ff-only origin main
```

`--ff-only`는 "fast-forward로 안 되면 그냥 실패하라"는 뜻입니다. 이게 왜 중요하냐면:

- 서버에서 누군가(또는 과거의 나 자신)가 급하게 파일을 직접 고쳐놓고 커밋하지 않은 경우, 일반 `git pull`은 자동 병합을 시도하다가 충돌을 일으키거나 예상 못 한 결과를 만듭니다.
- `--ff-only`는 이럴 때 그냥 에러를 내고 멈춥니다. **운영 서버 한복판에서 병합 충돌이 나는 것보다, 배포가 실패하고 멈추는 쪽이 훨씬 안전합니다.**

배포가 실패했다면 원인(서버 쪽 untracked 변경사항, 브랜치 불일치 등)을 먼저 파악하고 해결한 뒤 다시 시도하세요. `git merge`나 `git reset --hard`로 강제로 밀어붙이는 건 최후의 수단입니다.

## 브랜치 전략

작은 프로젝트라면 `main` 브랜치 하나만 운영에 배포하는 것으로 충분합니다. 좀 더 신중하게 가려면:

```
feature/* → dev (PR로 머지, 개발 중 통합 테스트)
dev → main (PR로 머지, 이게 배포 대상)
```

서버는 항상 `main`만 추적하고, `dev`는 서버에 영향이 없습니다. 이렇게 하면 "지금 머지한 게 바로 운영에 올라가는" 사고를 줄일 수 있습니다 — 운영 배포는 `dev → main` PR을 머지하는, 의식적인 별도 단계가 됩니다.

## composer 패키지가 추가됐다면

서버에서 `composer install`을 실행할 수 있다면(3장에서 확인) 그냥 `git pull` 뒤에 `composer install --no-dev --optimize-autoloader`를 실행하면 됩니다. 문제는 **php-cli 자체가 막혀서 composer도 못 돌리는 경우**입니다. 이때는 `vendor/` 디렉터리를 통째로 동기화해야 합니다.

```bash
# 로컬에서
composer install --no-dev --optimize-autoloader
tar czf vendor.tar.gz vendor/
scp vendor.tar.gz your_id@your-host.com:~/www-root/

# 서버에서 (tar는 php-cli와 무관하게 대부분 허용됨)
tar xzf vendor.tar.gz
rm vendor.tar.gz
```

매번 전체를 올리는 게 느리다면 `rsync`로 변경된 파일만 동기화할 수도 있습니다.

```bash
rsync -avz --delete vendor/ your_id@your-host.com:~/www-root/vendor/
```

**새 composer 패키지가 자주 추가되는 프로젝트라면, 이 과정 자체가 배포를 무겁게 만듭니다.** 가능하면 코어 PHP 확장(지난 책의 `DOMDocument`, `cURL` 사례처럼)만으로 기능을 구현해서 새 패키지 추가 빈도를 낮추는 것도 실전에서 고려할 만한 선택입니다.

## 체크리스트

- [ ] 서버가 git 저장소를 clone해둔 상태이고, 올바른 원격 저장소/브랜치를 추적하는가 (`git remote -v`, `git branch -vv`)
- [ ] 자격 증명이 안전하게(Deploy Key 또는 credential helper) 저장되어 있는가
- [ ] `git pull`에 항상 `--ff-only`를 쓰는 습관이 되어 있는가
- [ ] 새 composer 패키지가 추가됐을 때의 vendor 동기화 절차를 미리 정해뒀는가

다음 장에서는 코드는 배포됐는데 **DB 스키마가 바뀐 경우**, php-cli 없이 마이그레이션을 어떻게 적용하는지 다룹니다.
