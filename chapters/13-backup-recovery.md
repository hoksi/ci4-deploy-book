# 13장. 백업과 복구

## 지금까지 해온 것도 사실은 백업이었다

5장에서 DB 마이그레이션을 할 때마다 `.bak.<타임스탬프>`로 원본을 복사해뒀던 걸 기억하시나요? 그게 이미 백업의 가장 기본 형태입니다. 이번 장에서는 그걸 "작업 직전에 한 번"이 아니라 **정기적으로, 자동으로** 하는 법을 다룹니다.

## DB 백업 자동화

### SQLite

파일 하나라서 가장 간단합니다. cron으로 주기적으로 복사하고, 오래된 백업은 정리합니다.

```bash
#!/bin/bash
# backup_sqlite.sh
BACKUP_DIR=~/backups
TIMESTAMP=$(date +%Y%m%d%H%M%S)

mkdir -p "$BACKUP_DIR"
cp writable/database.db "$BACKUP_DIR/database.db.$TIMESTAMP"

# 30일 넘은 백업은 삭제
find "$BACKUP_DIR" -name "database.db.*" -mtime +30 -delete
```

```cron
0 3 * * * /home/your_id/backup_sqlite.sh
```

php-cli가 막혀 있어도 `cp`, `find` 같은 기본 셸 명령은 대부분 허용되므로(3장), 이 스크립트는 공유 호스팅에서도 그대로 동작합니다. cron 자체가 안 되는 호스팅이라면(3장의 패턴 C), 호스팅사가 제공하는 자체 cron UI로 같은 명령을 등록하세요.

### MySQL

```bash
#!/bin/bash
# backup_mysql.sh
mysqldump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" | gzip > ~/backups/db.$(date +%Y%m%d%H%M%S).sql.gz

find ~/backups -name "db.*.sql.gz" -mtime +30 -delete
```

비밀번호를 스크립트에 평문으로 박아두지 마세요. 환경변수나 `~/.my.cnf`(권한 600)에 분리해두는 게 안전합니다.

```ini
# ~/.my.cnf (chmod 600)
[client]
user=your_db_user
password=your_db_password
```

```bash
mysqldump "$DB_NAME" | gzip > ~/backups/db.$(date +%Y%m%d%H%M%S).sql.gz   # .my.cnf가 자동으로 인증 처리
```

## 파일 백업도 잊지 마세요

DB뿐 아니라 `writable/uploads/` 같은, 사용자가 올린 파일도 백업 대상입니다. DB에는 "파일이 있다"는 기록만 있고 실제 파일은 디스크에 있으므로, DB만 백업하고 파일을 빠뜨리면 복구했을 때 "레코드는 있는데 파일이 없는" 상태가 됩니다.

```bash
tar czf ~/backups/uploads.$(date +%Y%m%d%H%M%S).tar.gz writable/uploads/
```

## 오프사이트 백업 — 같은 서버에만 두지 않기

백업 파일을 백업 **대상과 같은 서버**에만 두면, 서버 자체에 문제가 생겼을 때(디스크 장애, 해킹으로 전체 삭제 등) 백업까지 같이 사라집니다. 최소한 하나는 **다른 곳**에 둬야 합니다.

```bash
# 예: rclone으로 S3 호환 스토리지에 업로드
rclone copy ~/backups/db.$(date +%Y%m%d)*.sql.gz remote:my-backup-bucket/db/
```

또는 8장에서 설정한 GitHub Actions에서, 배포와는 별개로 **정기적으로(cron 트리거) 백업을 가져와 Actions 아티팩트나 외부 스토리지에 저장**하는 워크플로우를 둘 수도 있습니다.

```yaml
# .github/workflows/backup.yml
on:
  schedule:
    - cron: '0 18 * * *'   # 매일 UTC 18시(KST 03시)

jobs:
  backup:
    runs-on: ubuntu-latest
    steps:
      - name: Fetch backup from server
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_SSH_KEY }}
          script: ./backup_sqlite.sh
```

## 백업은 복구해봐야 진짜 백업이다

가장 흔한 함정은 **"백업은 하고 있는데 한 번도 복구해본 적이 없는"** 상태입니다. 백업 파일이 손상됐거나, 복구 절차 자체를 몰라서 정작 사고가 났을 때 당황하는 경우가 많습니다. 분기에 한 번 정도는 실제로 복구를 연습해보는 걸 권장합니다.

```bash
# SQLite 복구 리허설 — 로컬 환경에서
cp ~/backups/database.db.20261001030000 ./test_restore.db
sqlite3 test_restore.db "SELECT COUNT(*) FROM posts;"   # 데이터가 정상인지 확인
```

```bash
# MySQL 복구 리허설
gunzip < ~/backups/db.20261001030000.sql.gz | mysql -u root -p test_restore_db
```

이 리허설에서 중요한 건 "명령이 에러 없이 끝났는가"가 아니라 "**복구된 데이터가 실제로 쓸만한가**"를 확인하는 것입니다.

## 체크리스트

- [ ] DB 백업이 cron(또는 호스팅사 자체 스케줄러)으로 자동 실행되는가
- [ ] 업로드 파일 등 DB 외 데이터도 백업 대상에 포함되어 있는가
- [ ] 백업이 원본 서버가 아닌 다른 곳(오프사이트)에도 저장되는가
- [ ] 최근에 실제로 복구를 리허설해본 적이 있는가

다음 장에서 이 책 전체를 정리합니다.
